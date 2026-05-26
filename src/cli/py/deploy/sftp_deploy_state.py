import json
import re
from dataclasses import dataclass
from pathlib import Path

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.vendor_sentinel import VendorSentinel

HTACCESS_FILE = ".htaccess"
BOOTSTRAP_PATH = "src/Http/bootstrap.php"
VALID_SLOTS = ("a", "b")

_PATTERNS_FILE = Path(__file__).parent.parent.parent.parent / "resources/slot-patterns.json"


def _load_slot_patterns():
    data = json.loads(_PATTERNS_FILE.read_text())
    return re.compile(data["app"]), re.compile(data["vendor"])


_APP_SLOT_RE, _VENDOR_SLOT_RE = _load_slot_patterns()


# deploy: Format von generate() wird von SlotSwitchCommand.php toHtaccess()
# erzeugt und von current_slot_map() per Regex gelesen.
# Siehe: https://docs.template.ysdani.com/de/areas/deploy/slot-switch/
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


@dataclass(frozen=True)
class SlotEntry:
    slot_type: str  # "app" | "vendor"
    label: str      # "a" | "b"

    @property
    def dir(self) -> str:
        return f"{self.slot_type}-{self.label}"


@dataclass(frozen=True)
class SlotMap:
    app: SlotEntry
    vendor: SlotEntry

    @classmethod
    def from_labels(cls, *, app: str, vendor: str) -> "SlotMap | None":
        if app in VALID_SLOTS and vendor in VALID_SLOTS:
            return cls(
                SlotEntry("app", app),
                SlotEntry("vendor", vendor),
            )
        return None

    @classmethod
    def initial(cls) -> "SlotMap":
        return cls(SlotEntry("app", "a"), SlotEntry("vendor", "a"))

    def as_tuple(self) -> tuple[str, str]:
        return (self.app.label, self.vendor.label)


@dataclass(frozen=True)
class DeploymentPlan:
    active_slot_map: SlotMap | None
    target_slot_map: SlotMap

    @classmethod
    def fresh(cls) -> "DeploymentPlan":
        return cls(None, SlotMap.initial())

    @classmethod
    def swap(cls, active: SlotMap, include_vendor: bool) -> "DeploymentPlan":
        app = other_slot(active.app.label)
        vendor = (
            other_slot(active.vendor.label)
            if include_vendor
            else active.vendor.label
        )
        return cls(
            active,
            SlotMap(SlotEntry("app", app), SlotEntry("vendor", vendor)),
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
        try:
            app = HtaccessSlotFile.read_slot(content)
        except ValueError:
            raise DeployConflictError(
                ".htaccess vorhanden, aber kein gültiger App-Slot erkennbar"
            )
        vendor = self.vendor_slot_for_app_slot(app)
        if vendor is None:
            raise DeployConflictError(
                f"bootstrap.php in app-{app}/ fehlt oder enthält"
                " keinen Vendor-Slot"
            )
        return SlotMap(SlotEntry("app", app), SlotEntry("vendor", vendor))

    def activate_slot_map(self, slot_map: SlotMap) -> None:
        htaccess = HtaccessSlotFile.generate(slot_map.app.label)
        self._client.put_text(HTACCESS_FILE, htaccess)

    def _any_slot_dir_exists(self) -> bool:
        dirs = [f"app-{s}" for s in VALID_SLOTS] + [f"vendor-{s}" for s in VALID_SLOTS]
        return any(self._client.dir_exists(d) for d in dirs)

    def vendor_slot_for_app_slot(self, app_slot: str) -> str | None:
        content = self._client.read_file(
            f"app-{app_slot}/{BOOTSTRAP_PATH}"
        )
        if not content:
            return None
        match = _VENDOR_SLOT_RE.search(content)
        return match.group(1) if match else None

    def run_id_for_app_slot(self, app_slot: str) -> str:
        content = self._client.read_file(f"app-{app_slot}/.deploy-run")
        return content.strip() if content else ""

    def vendor_checksum_for_slot(self, vendor_slot: str) -> str:
        content = self._client.read_file(f"vendor-{vendor_slot}/.meta")
        if not content:
            return ""
        sentinel = VendorSentinel.from_text(content)
        return sentinel.vendor_checksum


def other_slot(slot: str) -> str:
    return "b" if slot == "a" else "a"
