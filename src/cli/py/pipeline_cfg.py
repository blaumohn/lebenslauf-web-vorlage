import json
import os


class PipelineCfg:
    def __init__(self, phase: str):
        data = json.loads(os.environ["PIPELINE_CFG_JSON"])
        if phase not in data:
            raise KeyError(f"Phase {phase!r} fehlt in PIPELINE_CFG_JSON")
        self._section = data[phase]

    def __getitem__(self, key: str):
        return self._section[key]

    def get(self, key: str, default: str = "") -> str:
        return self._section.get(key, default)
