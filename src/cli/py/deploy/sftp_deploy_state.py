import re
from dataclasses import dataclass

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.vendor_sentinel import VendorSentinel

HTACCESS_FILE = ".htaccess"
BOOTSTRAP_PATH = "src/Http/bootstrap.php"
VALID_SLOTS = ("a", "b")

_APP_SLOT_RE = re.compile(r"RewriteRule \^ /app-([ab])/public/index\.php")
_VENDOR_SLOT_RE = re.compile(r"/vendor-([ab])/autoload\.php")


# deploy: Format von generate() wird von DeployState.php toHtaccess()
# erzeugt und von read_slot() per Regex gelesen.
# Änderung → _APP_SLOT_RE anpassen.
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
class SlotState:
    app: str
    vendor: str
    run_id: str = ""
    vendor_checksum: str = ""

    @classmethod
    def from_values(cls, app, vendor, run_id="", vendor_checksum=""):
        if app in VALID_SLOTS and vendor in VALID_SLOTS:
            return cls(app, vendor, run_id, vendor_checksum)
        return None

    @classmethod
    def initial(cls):
        return cls("a", "a")

    @property
    def app_dir(self):
        return f"app-{self.app}"

    @property
    def vendor_dir(self):
        return f"vendor-{self.vendor}"

    def as_tuple(self):
        return (self.app, self.vendor)


@dataclass(frozen=True)
class DeploymentPlan:
    active: SlotState | None
    target: SlotState

    @classmethod
    def fresh(cls):
        return cls(None, SlotState.initial())

    @classmethod
    def swap(cls, active, include_vendor: bool):
        app = other_slot(active.app)
        if include_vendor:
            vendor = other_slot(active.vendor)
        else:
            vendor = active.vendor
        return cls(active, SlotState(app, vendor))


class DeployState:
    @staticmethod
    def read(client):
        content = client.read_file(HTACCESS_FILE)
        if not content:
            return None
        try:
            app = HtaccessSlotFile.read_slot(content)
        except ValueError:
            raise DeployConflictError(
                ".htaccess vorhanden, aber kein gültiger App-Slot erkennbar"
            )
        vendor = DeployState._read_vendor_label(client, app)
        if vendor is None:
            raise DeployConflictError(
                f"bootstrap.php in app-{app}/ fehlt oder enthält keinen Vendor-Slot"
            )
        run_id = DeployState._read_run_id(client, app)
        vendor_checksum = DeployState._read_vendor_checksum(client, vendor)
        return SlotState(app, vendor, run_id, vendor_checksum)

    @staticmethod
    def write(client, state):
        htaccess = HtaccessSlotFile.generate(state.app)
        client.put_text(HTACCESS_FILE, htaccess)

    @staticmethod
    def _read_vendor_label(client, app):
        content = client.read_file(f"app-{app}/{BOOTSTRAP_PATH}")
        if not content:
            return None
        match = _VENDOR_SLOT_RE.search(content)
        return match.group(1) if match else None

    @staticmethod
    def _read_run_id(client, app):
        content = client.read_file(f"app-{app}/.deploy-run")
        return content.strip() if content else ""

    @staticmethod
    def _read_vendor_checksum(client, vendor):
        content = client.read_file(f"vendor-{vendor}/.meta")
        if not content:
            return ""
        sentinel = VendorSentinel.from_text(content)
        return sentinel.vendor_checksum


def other_slot(slot):
    return "b" if slot == "a" else "a"
