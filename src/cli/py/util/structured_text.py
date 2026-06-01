from __future__ import annotations

import configparser
import io
import json
from pathlib import Path
from typing import Any


class IniDocument:
    def __init__(self, parser: configparser.ConfigParser | None = None):
        self._parser = parser if parser is not None else self._new_parser()

    @staticmethod
    def _new_parser() -> configparser.ConfigParser:
        return configparser.ConfigParser()

    @classmethod
    def from_text(cls, text: str):
        parser = configparser.ConfigParser()
        parser.read_string(text)
        return cls(parser)

    @classmethod
    def from_section(cls, section: str, values: dict[str, str]):
        document = cls()
        document._parser[section] = values
        return document

    def required_values(
        self,
        section: str,
        keys: tuple[str, ...],
    ) -> dict[str, str]:
        values = self._parser[section]
        result = {}
        for key in keys:
            value = values.get(key)
            if value is None or value.strip() == "":
                raise KeyError(f"INI-Wert fehlt: [{section}] {key}")
            result[key] = value.strip()
        return result

    def has_section(self, name: str) -> bool:
        return self._parser.has_section(name)

    def to_text(self) -> str:
        out = io.StringIO()
        self._parser.write(out)
        return out.getvalue()


class IniModel:
    schema: dict[str, dict[str, type]]

    @classmethod
    def from_text(cls, text: str):
        document = IniDocument.from_text(text)
        values = cls._read_values(document)
        return cls(**values)

    @classmethod
    def _read_values(cls, document: IniDocument) -> dict[str, Any]:
        values: dict[str, Any] = {}
        for section, keys in cls.schema.items():
            values.update(cls._read_section(document, section, keys))
        return values

    @classmethod
    def _read_section(
        cls,
        document: IniDocument,
        section: str,
        keys: dict[str, type],
    ) -> dict[str, Any]:
        raw_values = document.required_values(section, tuple(keys))
        return {
            f"{section}_{key}": typ(raw_values[key])
            for key, typ in keys.items()
        }

    def to_text(self) -> str:
        config = configparser.ConfigParser()
        for section, keys in self.schema.items():
            config[section] = self._section_values(section, keys)
        return IniDocument(config).to_text()

    def _section_values(
        self,
        section: str,
        keys: dict[str, type],
    ) -> dict[str, str]:
        return {
            key: str(getattr(self, f"{section}_{key}"))
            for key in keys
        }


class JsonDocument:
    @staticmethod
    def canonical_file_bytes(path: Path) -> bytes:
        data = json.loads(path.read_text(encoding="utf-8"))
        text = json.dumps(
            data,
            ensure_ascii=False,
            sort_keys=True,
            separators=(",", ":"),
        )
        return text.encode("utf-8")

    @staticmethod
    def pretty_text(text: str) -> str:
        data = json.loads(text)
        return json.dumps(
            data,
            ensure_ascii=False,
            indent=2,
            sort_keys=True,
        )
