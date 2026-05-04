import json
from dataclasses import dataclass


STATE_FILE = ".deploy-state.json"
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


class DeployState:
    @staticmethod
    def read(client):
        return DeployState.parse(client.read_file(STATE_FILE))

    @staticmethod
    def write(client, state):
        client.put_bytes(STATE_FILE, DeployState.format(state).encode("utf-8"))

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


def other_slot(slot):
    return "b" if slot == "a" else "a"
