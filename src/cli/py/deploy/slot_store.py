import configparser
import json
import re
from pathlib import Path

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.slots import VALID_SLOTS, SlotEntry, SlotMap
from cli.py.deploy.vendor_sentinel import VendorSentinel

HTACCESS_FILE = ".htaccess"
INDEX_PHP_PATH = "public/index.php"

_PATTERNS_FILE = (
    Path(__file__).parent.parent.parent.parent
    / "resources/slot-patterns.json"
)


def _load_slot_patterns():
    data = json.loads(_PATTERNS_FILE.read_text())
    return re.compile(data["app"]), re.compile(data["vendor"])


_APP_SLOT_RE, _VENDOR_SLOT_RE = _load_slot_patterns()


# deploy: Format von generate() kommt aus SlotSwitchCommand.php.
# erzeugt und von current_slot_map() per Regex gelesen.
class HtaccessSlotFile:
    @staticmethod
    def read_slot(content):
        match = _APP_SLOT_RE.search(content)
        if not match:
            raise ValueError(
                "Kein aktiver app-a/app-b-Slot in .htaccess gefunden"
            )
        return match.group(1)

    @staticmethod
    def generate(app):
        return (
            f"RewriteEngine On\n"
            f"RewriteCond %{{DOCUMENT_ROOT}}/app-{app}/public/"
            f"%{{REQUEST_URI}} -f\n"
            f"RewriteRule ^(.*)$ /app-{app}/public/$1 [L]\n"
            f"RewriteRule ^ /app-{app}/public/index.php [L,QSA]\n"
        )


class SlotStore:
    def __init__(self, client):
        self._client = client

    def current_slot_map(self) -> SlotMap | None:
        content = self._client.read_file(HTACCESS_FILE)
        if not content:
            if self._any_slot_dir_exists():
                raise DeployConflictError(
                    "Kein .htaccess, aber Slot-Verzeichnisse vorhanden"
                )
            return None
        return self._slot_map_from_htaccess(content)

    def require_current_slot_map(self) -> SlotMap:
        slot_map = self.current_slot_map()
        if slot_map is None:
            raise RuntimeError("Kein aktiver Slot")
        return slot_map

    def _slot_map_from_htaccess(self, content: str) -> SlotMap:
        app = self._app_slot_from_htaccess(content)
        vendor = self.vendor_slot_for_app_slot(app)
        if vendor is None:
            raise DeployConflictError(
                f"index.php in app-{app}/public/ fehlt oder enthält"
                " keinen Vendor-Slot"
            )
        return SlotMap(
            SlotEntry("app", app),
            SlotEntry("vendor", vendor),
        )

    def _app_slot_from_htaccess(self, content: str) -> str:
        try:
            return HtaccessSlotFile.read_slot(content)
        except ValueError as error:
            raise DeployConflictError(
                ".htaccess vorhanden, aber kein gültiger "
                "App-Slot erkennbar"
            ) from error

    def activate_slot_map(self, slot_map: SlotMap) -> None:
        htaccess = HtaccessSlotFile.generate(slot_map.app.label)
        self._client.put_text(HTACCESS_FILE, htaccess)

    def _any_slot_dir_exists(self) -> bool:
        app_dirs = [f"app-{slot}" for slot in VALID_SLOTS]
        vendor_dirs = [f"vendor-{slot}" for slot in VALID_SLOTS]
        slot_dirs = app_dirs + vendor_dirs
        return any(self._client.dir_exists(path) for path in slot_dirs)

    def vendor_slot_for_app_slot(self, app_slot: str) -> str | None:
        content = self._client.read_file(
            f"app-{app_slot}/{INDEX_PHP_PATH}"
        )
        if not content:
            return None
        match = _VENDOR_SLOT_RE.search(content)
        return match.group(1) if match else None

    def run_id_for_app_slot(self, app_slot: str) -> str:
        content = self._client.read_file(f"app-{app_slot}/.deploy-run")
        return content.strip() if content else ""

    def vendor_checksum_for_slot(self, vendor_slot: str) -> str | None:
        content = self._client.read_file(f"vendor-{vendor_slot}/.meta")
        if not content:
            return None
        try:
            sentinel = VendorSentinel.from_text(content)
            return sentinel.vendor_checksum
        except (OSError, KeyError, configparser.Error):
            return None
