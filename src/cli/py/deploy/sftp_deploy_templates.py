from pathlib import Path


RESOURCE_DIR = Path(__file__).resolve().parents[4] / "src/resources/deploy-root"


def resource_path(name):
    return RESOURCE_DIR / name
