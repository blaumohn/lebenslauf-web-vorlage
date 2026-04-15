import argparse
import os
import subprocess
import sys

ROOT_PATH = os.getcwd()
DEV_PIPELINE = "dev"

from cli.py.dev.process_supervisor import ProcessSupervisor
from cli.py.util.run_helpers import run
from cli.py.dev.watchers import css
from cli.py.dev.watchers.file_watcher import FileWatcher
from cli.py.dev.watchers.schedule import schedule_twig, schedule_yaml


def main():
    try:
        args = parse_args()
        root_path = resolve_root_path()
        os.chdir(root_path)

        ensure_initial_build(args, root_path)

        supervisor = ProcessSupervisor()
        supervisor.install_signal_handlers()

        start_php_server(root_path, supervisor)
        file_watcher = setup_watchers(
            supervisor,
            root_path
        )

        exit_code = supervisor.run(file_watcher)
        sys.exit(exit_code)
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)


def parse_args():
    parser = argparse.ArgumentParser(description="Dev-Server mit Watchern starten.")
    parser.add_argument("--build", action="store_true", help="CV-Build vor dem Start ausfuehren.")
    return parser.parse_args()


def resolve_root_path():
    return ROOT_PATH


def ensure_initial_build(args, root_path):
    if args.build:
        run_cv_build(root_path)


def setup_watchers(supervisor, root_path):
    start_css_watch(supervisor)
    file_watcher = FileWatcher()
    yaml_path, yaml_dir = resolve_yaml_inputs(root_path)

    def build_fn(build_root):
        run_cv_build(build_root)

    schedule_yaml(
        file_watcher,
        root_path,
        yaml_path,
        yaml_dir,
        build_fn
    )
    schedule_twig(file_watcher, root_path, build_fn)
    file_watcher.start()
    return file_watcher


def start_php_server(root_path, supervisor):
    cmd = ["php", "-S", "127.0.0.1:8080", "-t", "public"]
    return supervisor.start("php-server", cmd, cwd=root_path)


def start_css_watch(supervisor):
    for index, cmd in enumerate(css.COMMANDS, start=1):
        print("CSS-Watch gestartet:", " ".join(cmd), flush=True)
        supervisor.start(f"css-{index}", cmd)


def resolve_yaml_inputs(root_path):
    yaml_path = get_config_value(
        "LEBENSLAUF_YAML_PFAD", root_path
    )
    yaml_dir = get_config_value(
        "LEBENSLAUF_DATEN_PFAD", root_path
    )
    return resolve_path(root_path, yaml_path), resolve_path(root_path, yaml_dir)


def get_config_value(key, root_path):
    cmd = ["php", "bin/cli", "config", "get", DEV_PIPELINE, key]
    result = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        cwd=root_path,
    )
    if result.returncode != 0:
        return ""
    return result.stdout.strip()


def resolve_path(root_path, value):
    if not value:
        return value
    if os.path.isabs(value):
        return value
    return os.path.join(root_path, value)


def run_cv_build(root_path):
    run(
        ["php", "bin/cli", "build", DEV_PIPELINE, "cv"],
        cwd=root_path,
    )


if __name__ == "__main__":
    main()
