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

    def test_reusable_preview_cd_exports_github_run_id(self):
        workflow = read_workflow("preview-cd.yml")

        self.assertIn("workflow_call:", workflow)
        self.assertIn("GITHUB_RUN_ID: ${{ github.run_id }}-${{ github.run_attempt }}", workflow)
        self.assertIn("LAST_DEPLOY_COMMIT: ${{ inputs.last-deploy-commit }}", workflow)
        self.assertIn("bin/cd << EOF", workflow)


def read_workflow(name: str) -> str:
    return (WORKFLOW_DIR / name).read_text(encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
