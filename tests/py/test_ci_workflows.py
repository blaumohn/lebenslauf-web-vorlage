import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
WORKFLOW_DIR = REPO_ROOT / ".github" / "workflows"


class CiWorkflowTest(unittest.TestCase):
    def test_preview_deploy_uses_reusable_workflow(self):
        workflow = read_workflow("preview-deploy.yml")

        self.assertIn("uses: ./.github/workflows/preview-cd.yml", workflow)
        self.assertIn("last-deploy-commit: ${{ github.event.before }}", workflow)
        self.assertNotIn("bin/cd << EOF", workflow)

    def test_admin_uses_reusable_workflow_for_reset_preview(self):
        workflow = read_workflow("admin.yml")

        self.assertIn("if: inputs.command == 'reset-preview'", workflow)
        self.assertIn("uses: ./.github/workflows/preview-cd.yml", workflow)
        self.assertIn('last-deploy-commit: ""', workflow)
        self.assertNotIn("bin/cd << EOF", workflow)

    def test_reusable_preview_cd_exports_pipeline_run_id(self):
        workflow = read_workflow("preview-cd.yml")

        self.assertIn("workflow_call:", workflow)
        self.assertIn("PIPELINE_RUN_ID: ${{ github.run_id }}-${{ github.run_attempt }}", workflow)
        self.assertIn("LAST_DEPLOY_COMMIT: ${{ inputs.last-deploy-commit }}", workflow)
        self.assertIn("bin/cd << EOF", workflow)

    def test_pipeline_uses_named_output_module(self):
        pipeline_lib = read_repo_file("scripts/pipeline_lib.sh")
        pipeline_output = read_repo_file("scripts/pipeline_output.sh")

        self.assertIn(". scripts/pipeline_output.sh", pipeline_lib)
        self.assertIn('run_step "Setup ($PIPELINE)" pipeline_setup "$is_dev"', pipeline_lib)
        self.assertIn("run_step_failed", pipeline_output)
        self.assertIn("GITHUB_STEP_SUMMARY", pipeline_output)

    def test_pipeline_deploy_uses_composer_lock_changed(self):
        pipeline_lib = read_repo_file("scripts/pipeline_lib.sh")

        self.assertIn("composer_lock_changed", pipeline_lib)
        self.assertIn("COMPOSER_LOCK_CHANGED=", pipeline_lib)
        self.assertNotIn("should_include_vendor", pipeline_lib)
        self.assertNotIn("SFTP_INCLUDE_VENDOR", pipeline_lib)

    def test_entrypoints_wrap_dependency_install(self):
        ci_entrypoint = read_repo_file("bin/ci")
        cd_entrypoint = read_repo_file("bin/cd")

        self.assertIn('run_step "Abhängigkeiten installieren" composer install', ci_entrypoint)
        self.assertIn('run_step "Abhängigkeiten installieren" composer install', cd_entrypoint)
        self.assertIn("Kein Deploy: keine Änderungen seit LAST_DEPLOY_COMMIT=", cd_entrypoint)


def read_workflow(name: str) -> str:
    return (WORKFLOW_DIR / name).read_text(encoding="utf-8")


def read_repo_file(path: str) -> str:
    return (REPO_ROOT / path).read_text(encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
