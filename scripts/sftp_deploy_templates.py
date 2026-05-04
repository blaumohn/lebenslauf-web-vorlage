from pathlib import Path


RESOURCE_DIR = Path(__file__).resolve().parents[1] / "src/resources/http"


def render_router(state):
    return render_template(
        "index.php.tpl",
        {
            "tree": state.tree,
            "vendor": state.vendor,
        },
    )


def render_entry_htaccess(state):
    return render_template(".htaccess.tpl", {"tree": state.tree})


def render_fallback_entry_htaccess():
    return read_resource(".htaccess-fallback")


def resource_path(name):
    return RESOURCE_DIR / name


def render_template(name, values):
    content = read_resource(name)
    for key, value in values.items():
        content = content.replace("{{ " + key + " }}", value)
    return content


def read_resource(name):
    return resource_path(name).read_text(encoding="utf-8")
