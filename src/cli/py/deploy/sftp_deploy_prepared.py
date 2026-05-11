import configparser
import io
import os
from datetime import datetime, timezone


PREPARED_DIR = "var/admin/deploy-prepared"


class PreparedDeployStore:
    @staticmethod
    def write(client, state) -> str:
        ref = PreparedDeployStore._ref(state)
        rel_path = f"{PREPARED_DIR}/{ref}.ini"
        client.ensure_dir(PREPARED_DIR)
        client.put_text(rel_path, PreparedDeployStore._format(state))
        return rel_path

    @staticmethod
    def _ref(state) -> str:
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        build_id = os.environ.get("GITHUB_RUN_ID", "local")
        return f"{timestamp}-{build_id}-{state.tree}"

    @staticmethod
    def _format(state) -> str:
        config = configparser.ConfigParser()
        config["prepared"] = {"tree": state.tree, "vendor": state.vendor}
        out = io.StringIO()
        config.write(out)
        return out.getvalue()


