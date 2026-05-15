import logging
import os
import shutil
import subprocess

logger = logging.getLogger(__name__)

def resolve_postcss_cmd():
    local = os.path.join(os.getcwd(), "node_modules", ".bin", "postcss")
    if os.path.exists(local):
        return [local]
    if shutil.which("postcss"):
        return ["postcss"]
    return ["npx", "postcss"]


POSTCSS_CMD = resolve_postcss_cmd()

COMMANDS = [
    POSTCSS_CMD
    + [
        "src/resources/build/assets/css/cv/index.css",
        "-o",
        "public/css/cv.css",
        "--watch",
    ],
    POSTCSS_CMD
    + [
        "src/resources/build/assets/css/site.css",
        "-o",
        "public/css/site.css",
        "--watch",
    ],
]


def start():
    processes = []
    for cmd in COMMANDS:
        logger.info("css watch started: %s", " ".join(cmd))
        processes.append(subprocess.Popen(cmd))
    return processes


def files_fn():
    def _files():
        css_root = os.path.join(os.getcwd(), "src", "resources", "build", "assets", "css")
        files = []
        for root, _dirs, filenames in os.walk(css_root):
            for name in filenames:
                if name.endswith(".css"):
                    files.append(os.path.join(root, name))
        return files

    return _files
