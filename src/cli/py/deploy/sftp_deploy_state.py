import configparser
from dataclasses import dataclass

from cli.py.util.structured_text import IniModel


STATE_FILE = ".deploy-state.ini"
VALID_SLOTS = ("a", "b")


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
        vendor = other_slot(active.vendor) if include_vendor else active.vendor
        return cls(active, SlotState(app, vendor))


@dataclass(frozen=True)
class DeployStateDocument(IniModel):
    schema = {
        "state": {
            "app": str,
            "vendor": str,
            "run_id": str,
            "vendor_checksum": str,
        },
    }

    state_app: str
    state_vendor: str
    state_run_id: str
    state_vendor_checksum: str


class DeployState:
    @staticmethod
    def read(client):
        return DeployState.parse(client.read_file(STATE_FILE))

    @staticmethod
    def write(client, state):
        client.put_text(STATE_FILE, DeployState.format(state))

    @staticmethod
    def parse(content):
        try:
            document = DeployStateDocument.from_text(content)
        except (configparser.Error, KeyError):
            return None
        return SlotState.from_values(
            document.state_app,
            document.state_vendor,
            document.state_run_id,
            document.state_vendor_checksum,
        )

    @staticmethod
    def format(state):
        if state is None:
            raise ValueError("Deploy-State fehlt.")
        if state.run_id == "" or state.vendor_checksum == "":
            raise ValueError("Deploy-State unvollständig.")
        return DeployStateDocument(
            state.app,
            state.vendor,
            state.run_id,
            state.vendor_checksum,
        ).to_text()


def other_slot(slot):
    return "b" if slot == "a" else "a"
