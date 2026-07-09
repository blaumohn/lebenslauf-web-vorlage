import re
import sys
from pathlib import Path
from typing import NamedTuple

REPO_ROOT = Path(__file__).resolve().parent.parent
REPO_URL = "https://github.com/blaumohn/shared-hosting-site-toolkit/blob/dev"
TOC_PLACEHOLDER = "<!-- TOC -->"
STRIP_PREFIX = re.compile(r"^# ?")
FENCE_MARKER = "```"
HEADING_PATTERN = re.compile(r"^## (.+)$")

DE_SWITCHER = (
    '<p align="right">Deutsch | '
    '<a href="README.en.md">English</a></p>'
)
EN_SWITCHER = (
    '<p align="right"><a href="README.md">Deutsch</a> '
    "| English</p>"
)

DE_SCRIPTS_DIR = REPO_ROOT / "readme-scripts"
EN_SCRIPTS_DIR = REPO_ROOT / "readme-scripts" / "en"
README_SCRIPT_USAGE_SOURCES = (
    REPO_ROOT / "tests/ci/readme-dev-user-flow.sh",
)


class Variant(NamedTuple):
    scripts_dir: Path
    usage_scripts_dir: Path
    output_path: Path
    switcher_line: str
    back_link_label: str
    usage_note_label: str


DE_README = REPO_ROOT / "README.md"
EN_README = REPO_ROOT / "README.en.md"

VARIANTS = (
    Variant(
        scripts_dir=DE_SCRIPTS_DIR,
        usage_scripts_dir=DE_SCRIPTS_DIR,
        output_path=DE_README,
        switcher_line=DE_SWITCHER,
        back_link_label="nach oben",
        usage_note_label="Ausgeführt in",
    ),
    Variant(
        scripts_dir=EN_SCRIPTS_DIR,
        usage_scripts_dir=DE_SCRIPTS_DIR,
        output_path=EN_README,
        switcher_line=EN_SWITCHER,
        back_link_label="back to top",
        usage_note_label="Executed in",
    ),
)


def main() -> int:
    check_mode = "--check" in sys.argv[1:]
    results = [
        render_variant(variant, check_mode) for variant in VARIANTS
    ]
    return 0 if all(results) else 1


def render_variant(variant: Variant, check_mode: bool) -> bool:
    generated = build_readme(variant)
    if not check_mode:
        variant.output_path.write_text(generated)
        return True
    return check_variant(variant.output_path, generated)


def check_variant(output_path: Path, generated: str) -> bool:
    if output_path.exists() and output_path.read_text() == generated:
        return True
    print(
        f"{output_path.name} ist veraltet — "
        "gen-readme.py ausführen und committen.",
        file=sys.stderr,
    )
    return False


def build_readme(variant: Variant) -> str:
    body = strip_comment_markers(concatenate_sections(variant))
    toc = build_toc(body)
    body = body.replace(TOC_PLACEHOLDER, toc, 1)
    return insert_switcher(body, variant.switcher_line)


def concatenate_sections(variant: Variant) -> str:
    section_files = sorted(variant.scripts_dir.glob("*.sh"))
    texts = [path.read_text().rstrip("\n") for path in section_files]
    top_slug = find_top_slug(texts)
    sections = [
        annotate_section(text, path, top_slug, variant)
        for text, path in zip(texts, section_files)
    ]
    return "\n\n---\n\n".join(sections) + "\n"


def find_top_slug(texts: list[str]) -> str:
    h1_prefix = "# # "
    h1_lines = [
        t.splitlines()[0] for t in texts if t.startswith(h1_prefix)
    ]
    title = h1_lines[0][len(h1_prefix):].strip()
    return slugify(title)


def annotate_section(
    text: str, path: Path, top_slug: str, variant: Variant
) -> str:
    heading, *rest = text.splitlines()
    if not heading.startswith("# ## "):
        return text
    usage_script = usage_script_for(path, variant)
    relative = usage_script.relative_to(REPO_ROOT).as_posix()
    usage_notes = usage_source_notes(relative, variant.usage_note_label)
    back_link = f"# [{variant.back_link_label}](#{top_slug})"
    return "\n".join([heading] + usage_notes + rest + ["#", back_link])


def usage_script_for(path: Path, variant: Variant) -> Path:
    relative = path.relative_to(variant.scripts_dir)
    return variant.usage_scripts_dir / relative


def usage_source_notes(
    readme_script: str, usage_note_label: str
) -> list[str]:
    sources = usage_sources_for(readme_script)
    return [
        f"# <small>*{usage_note_label} "
        f"[{source}]({REPO_URL}/{source})*</small>"
        for source in sources
    ]


def usage_sources_for(readme_script: str) -> list[str]:
    return [
        source.relative_to(REPO_ROOT).as_posix()
        for source in README_SCRIPT_USAGE_SOURCES
        if readme_script in source.read_text()
    ]


def strip_comment_markers(text: str) -> str:
    lines = text.splitlines()
    stripped = (STRIP_PREFIX.sub("", line) for line in lines)
    return "\n".join(stripped) + "\n"


def insert_switcher(body: str, switcher_line: str) -> str:
    heading, *rest = body.splitlines()
    lines = [switcher_line, "", "---", "", heading] + rest
    return "\n".join(lines) + "\n"


def build_toc(body: str) -> str:
    entries = []
    in_fence = False
    for line in body.splitlines():
        if line.strip().startswith(FENCE_MARKER):
            in_fence = not in_fence
            continue
        if in_fence:
            continue
        match = HEADING_PATTERN.match(line)
        if match:
            title = match.group(1).strip()
            entries.append(f"- [{title}](#{slugify(title)})")
    return "\n".join(entries)


def slugify(title: str) -> str:
    slug = title.strip().lower()
    slug = re.sub(r"[^\w\s-]", "", slug)
    slug = re.sub(r"\s+", "-", slug)
    return slug


if __name__ == "__main__":
    sys.exit(main())
