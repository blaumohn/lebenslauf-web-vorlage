import os
from collections.abc import Collection


class EnvVar:
    def __init__(self, name: str, value: str | None) -> None:
        self._name = name
        self._value = value

    def require_set(self) -> "EnvVar":
        if self._value is None:
            raise RuntimeError(f"{self._name} is required")
        return self

    def require_nonempty(self) -> "EnvVar":
        self.require_set()
        if self._value == "":
            raise RuntimeError(f"{self._name} must not be empty")
        return self

    def require_in(self, allowed: Collection[str]) -> "EnvVar":
        self.require_nonempty()
        if self._value not in allowed:
            allowed_text = ", ".join(sorted(allowed))
            raise RuntimeError(f"{self._name} must be one of: {allowed_text}")
        return self

    def require_bool(self) -> "EnvVar":
        return self.require_in({"true", "false"})

    def to_bool(self) -> bool:
        return self._value == "true"

    def value(self) -> str:
        if self._value is None:
            raise RuntimeError(f"{self._name} is required")
        return self._value


def env(name: str) -> EnvVar:
    return EnvVar(name, os.environ.get(name))
