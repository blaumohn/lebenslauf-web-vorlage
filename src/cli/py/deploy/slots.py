from dataclasses import dataclass

VALID_SLOTS = ("a", "b")


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
    def swap(
        cls,
        active: SlotMap,
        include_vendor: bool,
    ) -> "DeploymentPlan":
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


def other_slot(slot: str) -> str:
    return "b" if slot == "a" else "a"
