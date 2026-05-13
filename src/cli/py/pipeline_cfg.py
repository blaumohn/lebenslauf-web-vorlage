import json

from cli.py.util.envvar import env


class PipelineCfg:
    def __init__(self, phase: str):
        raw = env("PIPELINE_CFG_JSON").require_nonempty().value()
        data = json.loads(raw)
        if phase not in data:
            raise KeyError(f"Phase {phase!r} fehlt in PIPELINE_CFG_JSON")
        self._section = data[phase]

    def __getitem__(self, key: str):
        return self._section[key]

    def get(self, key: str, default: str = "") -> str:
        return self._section.get(key, default)
