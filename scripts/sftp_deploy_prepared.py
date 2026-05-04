import configparser
import io
import os
from datetime import datetime, timezone


PREPARED_DIR = "var/admin/deploy-prepared"
ADMIN_TASK_DIR = "var/admin/tasks"


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


class AdminTaskStore:
    @staticmethod
    def enqueue_deploy_switch(client, prepared_path: str) -> None:
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        rel_path = f"{ADMIN_TASK_DIR}/{timestamp}-deploy-switch.ini"
        client.ensure_dir(ADMIN_TASK_DIR)
        client.put_text(rel_path, AdminTaskStore._format(prepared_path))

    @staticmethod
    def _format(prepared_path: str) -> str:
        config = configparser.ConfigParser()
        config["task"] = {"type": "deploy_switch", "prepared_state": prepared_path}
        out = io.StringIO()
        config.write(out)
        return out.getvalue()
