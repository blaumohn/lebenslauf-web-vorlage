import json
from dataclasses import dataclass


STATE_MARKER = "// deploy-state:"
STATE_FILE = ".deploy-state.json"
ROUTER_FILE = "index.php"
VALID_SLOTS = ("a", "b")


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


class DeployStateFile:
    @staticmethod
    def read(client):
        return DeployStateFile.parse(client.read_file(STATE_FILE))

    @staticmethod
    def write(client, state):
        client.put_bytes(STATE_FILE, DeployStateFile.format(state).encode("utf-8"))

    @staticmethod
    def parse(content):
        try:
            data = json.loads(content)
        except (json.JSONDecodeError, TypeError):
            return None
        if not isinstance(data, dict):
            return None
        return SlotState.from_values(data.get("tree", ""), data.get("vendor", ""))

    @staticmethod
    def format(state):
        data = {"tree": state.tree, "vendor": state.vendor}
        return json.dumps(data, sort_keys=True) + "\n"


class RouterState:
    @staticmethod
    def read(client):
        return RouterState.parse(client.read_file(ROUTER_FILE))

    @staticmethod
    def parse(content):
        for line in content.splitlines():
            state = RouterState.parse_line(line.strip())
            if state is not None:
                return state
        return None

    @staticmethod
    def parse_line(line):
        if not line.startswith(STATE_MARKER):
            return None
        try:
            parts = dict(kv.split("=") for kv in line[len(STATE_MARKER):].split())
        except (ValueError, AttributeError):
            return None
        return SlotState.from_values(parts.get("tree", ""), parts.get("vendor", ""))


def other_slot(slot):
    return "b" if slot == "a" else "a"
