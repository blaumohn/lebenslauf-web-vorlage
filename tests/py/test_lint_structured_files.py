import importlib.util
import tempfile
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]


def load_module(name: str, path: Path):
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Kann Modul nicht laden: {path}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


lint_structured = load_module(
    "lint_structured",
    REPO_ROOT / "tools" / "lint-structured-files.py",
)


class LintStructuredFilesTest(unittest.TestCase):
    def test_json_error_contains_path(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "broken.json"
            path.write_text("{", encoding="utf-8")

            errors = lint_structured.lint_paths([path])

        self.assertEqual(len(errors), 1)
        self.assertIn("broken.json", errors[0])

    def test_valid_toml_and_xml_pass(self):
        with tempfile.TemporaryDirectory() as tmp:
            toml_path = Path(tmp) / "valid.toml"
            xml_path = Path(tmp) / "valid.xml"
            toml_path.write_text("name = \"ok\"\n", encoding="utf-8")
            xml_path.write_text("<root />\n", encoding="utf-8")

            errors = lint_structured.lint_paths([toml_path, xml_path])

        self.assertEqual(errors, [])


if __name__ == "__main__":
    unittest.main()
