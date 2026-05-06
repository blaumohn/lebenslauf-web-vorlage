from pathlib import Path


RESOURCE_DIR = Path(__file__).resolve().parents[1] / "src/resources/http"


def resource_path(name):
    return RESOURCE_DIR / name
