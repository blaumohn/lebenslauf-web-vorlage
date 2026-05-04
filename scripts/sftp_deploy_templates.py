from pathlib import Path

from sftp_deploy_state import STATE_MARKER


RESOURCE_DIR = Path(__file__).resolve().parents[1] / "src/resources/deploy/sftp"


def render_router(state):
    return render_template(
        "root-router.php.tpl",
        {
            "state_marker": STATE_MARKER,
            "tree": state.tree,
            "vendor": state.vendor,
        },
    )


def render_entry_htaccess(state):
    return render_template("entry.htaccess.tpl", {"tree": state.tree})


def render_fallback_entry_htaccess():
    return read_resource("entry-fallback.htaccess")


def render_template(name, values):
    content = read_resource(name)
    for key, value in values.items():
        content = content.replace("{{ " + key + " }}", value)
    return content


def read_resource(name):
    return (RESOURCE_DIR / name).read_text(encoding="utf-8")
