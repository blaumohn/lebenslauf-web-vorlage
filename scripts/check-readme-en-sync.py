import re
import sys
from pathlib import Path

import tree_sitter_bash
from plumbum import local
from tree_sitter import Language, Node, Parser

DE_DIR = Path("readme-scripts")
EN_DIR = DE_DIR / "en"
STRIP_PREFIX = re.compile(r"^# ?")
FENCE_MARKER = "```"
BASH_PARSER = Parser(Language(tree_sitter_bash.language()))


def main() -> int:
    problems = (
        filename_parity_problems()
        + staged_change_symmetry_problems()
        + command_parity_problems()
    )
    if not problems:
        return 0
    print_problems(problems)
    return 1


def filename_parity_problems() -> list[str]:
    de_names = section_names(DE_DIR)
    en_names = section_names(EN_DIR)
    missing_in_en = de_names - en_names
    orphaned_in_en = en_names - de_names

    added = (f"{DE_DIR / n} hat keine Entsprechung in {EN_DIR}/" for n in missing_in_en)
    orphaned = (
        f"{EN_DIR / n} ist verwaist (keine Entsprechung in {DE_DIR}/)"
        for n in orphaned_in_en
    )
    return sorted(added) + sorted(orphaned)


def section_names(directory: Path) -> set[str]:
    return {path.name for path in directory.glob("*.sh")}


def staged_change_symmetry_problems() -> list[str]:
    staged = set(staged_files())
    de_changed = [path for path in staged if is_de_section(path)]
    unsynced = [path for path in de_changed if en_counterpart(path) not in staged]
    return sorted(
        f"{path} geändert, aber {en_counterpart(path)} nicht mit committet"
        for path in unsynced
    )


def command_parity_problems() -> list[str]:
    de_paths = sorted(DE_DIR.glob("*.sh"))
    section_pairs = [(de_path, EN_DIR / de_path.name) for de_path in de_paths]
    existing_pairs = [(de, en) for de, en in section_pairs if en.exists()]
    mismatched_pairs = [(de, en) for de, en in existing_pairs if commands_differ(de, en)]
    return [f"{de} und {en}: Befehle weichen voneinander ab" for de, en in mismatched_pairs]


def commands_differ(de_path: Path, en_path: Path) -> bool:
    return extract_commands(de_path.read_text()) != extract_commands(en_path.read_text())


def extract_commands(text: str) -> list[str]:
    commands = []
    for block in fenced_blocks(render_body(text)):
        cleaned = strip_bash_comments(block)
        commands.extend(line.rstrip() for line in cleaned.splitlines() if line.strip())
    return commands


def render_body(text: str) -> str:
    lines = text.splitlines()
    return "\n".join(STRIP_PREFIX.sub("", line) for line in lines) + "\n"


def fenced_blocks(body: str) -> list[str]:
    lines = body.splitlines()
    fence_lines = [i for i, line in enumerate(lines) if line.strip().startswith(FENCE_MARKER)]
    fence_pairs = zip(fence_lines[0::2], fence_lines[1::2])
    return ["\n".join(lines[start + 1 : end]) for start, end in fence_pairs]


def strip_bash_comments(code: str) -> str:
    data = code.encode()
    tree = BASH_PARSER.parse(data)
    comments = (node for node in walk(tree.root_node) if node.type == "comment")
    pieces = []
    cursor = 0
    for node in sorted(comments, key=lambda n: n.start_byte):
        pieces.append(data[cursor : node.start_byte])
        cursor = node.end_byte
    pieces.append(data[cursor:])
    return b"".join(pieces).decode()


def walk(node: Node):
    yield node
    for child in node.children:
        yield from walk(child)


def staged_files() -> list[str]:
    output = local["git"]["diff", "--cached", "--name-only"]()
    return output.splitlines()


def is_de_section(path: str) -> bool:
    de_prefix = f"{DE_DIR}/"
    en_prefix = f"{EN_DIR}/"
    return path.startswith(de_prefix) and path.endswith(".sh") and not path.startswith(en_prefix)


def en_counterpart(path: str) -> str:
    return f"{EN_DIR}/{Path(path).name}"


def print_problems(problems: list[str]) -> None:
    print("readme-scripts/en Sync-Probleme:", file=sys.stderr)
    for problem in problems:
        print(f"  - {problem}", file=sys.stderr)


if __name__ == "__main__":
    sys.exit(main())
