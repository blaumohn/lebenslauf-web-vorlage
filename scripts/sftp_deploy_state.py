import configparser
import io
from dataclasses import dataclass


STATE_FILE = ".deploy-state.ini"
VALID_SLOTS = ("a", "b")


class IniConfig(configparser.ConfigParser):
    def to_string(self):
        out = io.StringIO()
        self.write(out)
        return out.getvalue()


@dataclass(frozen=True)
class SlotState:
    tree: str
    vendor: str

    @classmethod
    def from_values(cls, tree, vendor):
        if tree in VALID_SLOTS and vendor in VALID_SLOTS:
            return cls(tree, vendor)
        return None

    @classmethod
    def initial(cls):
        return cls("a", "a")

    def as_tuple(self):
        return (self.tree, self.vendor)


@dataclass(frozen=True)
class DeploymentPlan:
    active: SlotState | None
    target: SlotState

    @classmethod
    def fresh(cls):
        return cls(None, SlotState.initial())

    @classmethod
    def swap(cls, active, include_vendor):
        tree = other_slot(active.tree)
        vendor = other_slot(active.vendor) if include_vendor else active.vendor
        return cls(active, SlotState(tree, vendor))


class DeployState:
    @staticmethod
    def read(client):
        return DeployState.parse(client.read_file(STATE_FILE))

    @staticmethod
    def write(client, state):
        client.put_text(STATE_FILE, DeployState.format(state))

    @staticmethod
    def parse(content):
        parser = configparser.ConfigParser()
        try:
            parser.read_string(content)
        except configparser.Error:
            return None
        if not parser.has_section("state"):
            return None
        tree = parser["state"].get("tree", "")
        vendor = parser["state"].get("vendor", "")
        return SlotState.from_values(tree, vendor)

    @staticmethod
    def format(state):
        if state is None:
            raise ValueError("Deploy-State fehlt.")
        config = IniConfig()
        config["state"] = {
            "tree": state.tree,
            "vendor": state.vendor,
        }
        return config.to_string()

def other_slot(slot):
    return "b" if slot == "a" else "a"
