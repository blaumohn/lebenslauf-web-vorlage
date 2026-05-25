import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
WORKFLOW_DIR = REPO_ROOT / ".github" / "workflows"


class CiWorkflowTest(unittest.TestCase):
    def test_preview_deploy_uses_reusable_workflow(self):
        workflow = read_workflow("preview-deploy.yml")
        reusable = "uses: ./.github/workflows/preview-cd.yml"
        last_commit = "last-deploy-commit: ${{ github.event.before }}"

        self.assertIn(reusable, workflow)
        self.assertIn(last_commit, workflow)
        self.assertNotIn("bin/cd << EOF", workflow)

    def test_admin_uses_reusable_workflow_for_reset_preview(self):
        workflow = read_workflow("admin.yml")
        reusable = "uses: ./.github/workflows/preview-cd.yml"

        self.assertIn("if: inputs.command == 'reset-preview'", workflow)
        self.assertIn(reusable, workflow)
        self.assertIn('last-deploy-commit: ""', workflow)
        self.assertNotIn("bin/cd << EOF", workflow)

    def test_reusable_preview_cd_exports_pipeline_run_id(self):
        workflow = read_workflow("preview-cd.yml")
        run_id = (
            "PIPELINE_RUN_ID: "
            "${{ github.run_id }}-${{ github.run_attempt }}"
        )

        self.assertIn("workflow_call:", workflow)
        self.assertIn(run_id, workflow)
        self.assertIn(
            "LAST_DEPLOY_COMMIT: ${{ inputs.last-deploy-commit }}",
            workflow,
        )
        self.assertIn("bin/cd << EOF", workflow)

    def test_pipeline_uses_named_output_module(self):
        pipeline_lib = read_repo_file("scripts/pipeline_lib.sh")
        pipeline_output = read_repo_file("scripts/pipeline_output.sh")
        setup_step = (
            'run_step "Setup ($PIPELINE)" '
            'pipeline_setup "$is_dev"'
        )

        self.assertIn(". scripts/pipeline_output.sh", pipeline_lib)
        self.assertIn(setup_step, pipeline_lib)
        self.assertIn("run_step_failed", pipeline_output)
        self.assertIn("GITHUB_STEP_SUMMARY", pipeline_output)

    def test_pipeline_smoke_logs_url_on_curl_error(self):
        pipeline_lib = read_repo_file("scripts/pipeline_lib.sh")
        curl_error_guard = (
            'if ! body="$(curl --fail --silent --show-error "$url")"; '
            "then"
        )
        smoke_request_message = (
            'echo "[smoke] HTTP-Abruf: ${url}" >&2'
        )
        smoke_error_message = (
            'echo "[smoke] HTTP-Abruf fehlgeschlagen: ${url}" >&2'
        )

        self.assertIn(smoke_request_message, pipeline_lib)
        self.assertIn(curl_error_guard, pipeline_lib)
        self.assertIn(smoke_error_message, pipeline_lib)

    def test_pipeline_dev_server_uses_public_docroot(self):
        pipeline_lib = read_repo_file("scripts/pipeline_lib.sh")

        self.assertIn('php -S "0.0.0.0:${port}"', pipeline_lib)
        self.assertIn('-t "$docroot"', pipeline_lib)
        self.assertNotIn("dev-index.php", pipeline_lib)

    def test_entrypoints_wrap_dependency_install(self):
        ci_entrypoint = read_repo_file("bin/ci")
        cd_entrypoint = read_repo_file("bin/cd")
        ci_install_step = 'run_step "Abhängigkeiten installieren" composer install:ci'
        cd_install_step = 'run_step "Abhängigkeiten installieren" composer install:deploy'
        composer_json = read_repo_file("composer.json")
        no_deploy = (
            "Kein Deploy: keine Änderungen seit "
            "LAST_DEPLOY_COMMIT="
        )

        self.assertIn(ci_install_step, ci_entrypoint)
        self.assertIn(cd_install_step, cd_entrypoint)
        self.assertIn('"install:ci": "composer install --optimize-autoloader --no-interaction -q"', composer_json)
        self.assertIn('"install:deploy": "@install:ci --no-dev"', composer_json)
        self.assertIn(no_deploy, cd_entrypoint)

    def test_cd_skips_deploy_if_no_changes(self):
        pipeline_lib = read_repo_file("scripts/pipeline_lib.sh")
        cd_entrypoint = read_repo_file("bin/cd")

        self.assertIn("no_changes_since_deploy()", pipeline_lib)
        self.assertIn("if no_changes_since_deploy;", cd_entrypoint)
        self.assertIn('[[ -z "${LAST_DEPLOY_COMMIT:-}" ]]', pipeline_lib)
        self.assertIn("git diff --name-only", pipeline_lib)


def read_workflow(name: str) -> str:
    return (WORKFLOW_DIR / name).read_text(encoding="utf-8")


def read_repo_file(path: str) -> str:
    return (REPO_ROOT / path).read_text(encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
