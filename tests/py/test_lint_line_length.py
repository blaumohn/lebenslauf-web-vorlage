import importlib.util
import unittest
from pathlib import Path
from unittest import mock

REPO_ROOT = Path(__file__).resolve().parents[2]


def load_module(name: str, path: Path):
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Kann Modul nicht laden: {path}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


lint_line_length = load_module(
    "lint_line_length",
    REPO_ROOT / "tools" / "lint-line-length.py",
)


class LintLineLengthTest(unittest.TestCase):
    def test_finds_long_lines(self):
        path = Path("src/example.py")
        text = "x" * 73

        with mock.patch.object(
            lint_line_length,
            "file_lines",
            return_value=[(1, text)],
        ):
            errors = lint_line_length.long_lines(path)

        self.assertEqual(errors, ["src/example.py:1: 73 > 72"])

    def test_skips_generated_and_external_paths(self):
        skipped = [
            Path("vendor/pkg/file.php"),
            Path("node_modules/pkg/index.js"),
            Path("public/css/site.css"),
            Path("composer.lock"),
            Path("tests/py/__pycache__/test.pyc"),
        ]

        for path in skipped:
            with self.subTest(path=path):
                self.assertTrue(lint_line_length.should_skip(path))


if __name__ == "__main__":
    unittest.main()
