#!/usr/bin/env .venv/bin/python3
import argparse
import json
import os
import signal
import subprocess
import sys
import time

from watchers import css, manager, twig, yaml_data


def parse_args():
    parser = argparse.ArgumentParser(description="Run dev server with watchers.")
    parser.add_argument("--build", action="store_true", help="Run cv build before starting dev.")
    parser.add_argument("--demo", action="store_true", help="Use demo fixtures for cv build.")
    parser.add_argument("--mail-stdout", action="store_true", help="Send mail output to stdout.")
    return parser.parse_args()


def run_checked(cmd, process_env, root_path):
    result = subprocess.run(cmd, env=process_env, cwd=root_path)
    if result.returncode != 0:
        sys.exit(result.returncode)


def run_cv_build(process_env, root_path, demo=False):
    build_env = dict(process_env)
    if demo:
        build_env.update(demo_env(root_path))
    run_checked(["php", "bin/cli", "cv", "build"], build_env, root_path)
    write_state(build_state_path(root_path, process_env), build_inputs(root_path, build_env, demo))


def get_config_value(key, process_env, root_path):
    cmd = ["php", "bin/cli", "env", "get", key]
    result = subprocess.run(cmd, capture_output=True, text=True, env=process_env, cwd=root_path)
    if result.returncode != 0:
        return ""
    return result.stdout.strip()


def start_php_server(process_env, root_path):
    return subprocess.Popen(
        ["php", "-S", "127.0.0.1:8080", "-t", "public"],
        env=process_env,
        cwd=root_path,
    )


def terminate_processes(processes, exit_code):
    for proc in processes:
        if proc.poll() is None:
            proc.terminate()
    for proc in processes:
        if proc.poll() is None:
            try:
                proc.wait(timeout=5)
            except subprocess.TimeoutExpired:
                proc.kill()
    sys.exit(exit_code)


def resolve_yaml_inputs(process_env, root_path):
    yaml_path = get_config_value("LEBENSLAUF_YAML_PFAD", process_env, root_path)
    yaml_dir = get_config_value("LEBENSLAUF_DATEN_PFAD", process_env, root_path)
    return resolve_path(root_path, yaml_path), resolve_path(root_path, yaml_dir)


def resolve_path(root_path, value):
    if not value:
        return value
    if os.path.isabs(value):
        return value
    return os.path.join(root_path, value)


def register_yaml_watch(watch_manager, process_env, root_path, demo):
    yaml_path, yaml_dir = resolve_yaml_inputs(process_env, root_path)
    if not (yaml_path or yaml_dir):
        print("yaml watch disabled (set LEBENSLAUF_DATEN_PFAD or LEBENSLAUF_YAML_PFAD).", flush=True)
        return

    watched = yaml_data.files_fn(yaml_path, yaml_dir)()
    print(f"yaml watch enabled: {len(watched)} file(s)", flush=True)
    watch_manager.register(
        "yaml",
        yaml_data.files_fn(yaml_path, yaml_dir),
        lambda: run_cv_build(process_env, root_path, demo),
    )


def register_twig_watch(watch_manager, process_env, root_path, demo):
    if not twig.enabled():
        print("twig watch disabled (missing templates directory).", flush=True)
        return

    watched = twig.files_fn()()
    print(f"twig watch enabled: {len(watched)} file(s)", flush=True)
    watch_manager.register(
        "twig",
        twig.files_fn(),
        lambda: run_cv_build(process_env, root_path, demo),
    )


def register_css_watch(watch_manager):
    watched = css.files_fn()()
    print(f"css watch enabled: {len(watched)} file(s)", flush=True)
    watch_manager.register(
        "css",
        css.files_fn(),
        lambda: print("css change detected; postcss watch rebuild triggered.", flush=True),
    )


def build_runtime_env(args):
    process_env = dict(os.environ)
    if args.mail_stdout:
        process_env["MAIL_STDOUT"] = "1"
    return process_env


def ensure_initial_build(args, process_env, root_path):
    if args.build or build_state_dirty(process_env, root_path, args.demo):
        run_cv_build(process_env, root_path, args.demo)


def register_watchers(watch_manager, process_env, root_path, demo):
    register_env_watch(watch_manager, process_env, root_path)
    register_yaml_watch(watch_manager, process_env, root_path, demo)
    register_twig_watch(watch_manager, process_env, root_path, demo)
    register_css_watch(watch_manager)


def env_files(root_path, process_env):
    pipeline = process_env.get("PIPELINE", "dev")
    phase = "runtime"
    profile = process_env.get("PROFILE", "")
    filenames = [
        ".env",
        ".env.local",
        f".env.{pipeline}",
        f".env.{pipeline}.local",
        f".env.{pipeline}.{phase}",
        f".env.{pipeline}.{phase}.local",
    ]
    if profile:
        filenames.extend(
            [
                f".env.{pipeline}.{profile}",
                f".env.{pipeline}.{profile}.local",
                f".env.{pipeline}.{profile}.{phase}",
                f".env.{pipeline}.{profile}.{phase}.local",
            ]
        )
    filenames.append("config/env.manifest.yaml")
    return [os.path.join(root_path, name) for name in filenames]


def run_env_compile(process_env, root_path):
    run_checked(
        [
            "php",
            "bin/cli",
            "env",
            "compile",
            "--pipeline",
            process_env.get("PIPELINE", "dev"),
            "--phase",
            "runtime",
        ],
        process_env,
        root_path,
    )
    write_state(env_state_path(root_path, process_env), env_files(root_path, process_env))


def register_env_watch(watch_manager, process_env, root_path):
    watched = env_files(root_path, process_env)
    watch_manager.register(
        "env",
        lambda: watched,
        lambda: run_env_compile(process_env, root_path),
    )


def ensure_env_compiled(process_env, root_path):
    if env_state_dirty(process_env, root_path):
        run_env_compile(process_env, root_path)


def demo_env(root_path):
    return {
        "CONTENT_INI_PATH": os.path.join(root_path, "tests", "fixtures", "content.ini"),
        "LEBENSLAUF_YAML_PFAD": os.path.join(
            root_path, "tests", "fixtures", "lebenslauf", "daten-gueltig.yaml"
        ),
        "LEBENSLAUF_DATEN_PFAD": os.path.join(root_path, "tests", "fixtures", "lebenslauf"),
    }


def build_inputs(root_path, process_env, demo):
    if demo:
        yaml_path = demo_env(root_path)["LEBENSLAUF_YAML_PFAD"]
        yaml_dir = demo_env(root_path)["LEBENSLAUF_DATEN_PFAD"]
        content_path = demo_env(root_path)["CONTENT_INI_PATH"]
    else:
        yaml_path, yaml_dir = resolve_yaml_inputs(process_env, root_path)
        content_path = get_config_value("CONTENT_INI_PATH", process_env, root_path)
        if not content_path:
            content_path = os.path.join(root_path, ".local", "content.ini")
        content_path = resolve_path(root_path, content_path)
    files = []
    files.append(content_path)
    files.append(os.path.join(root_path, "src", "resources", "labels.json"))
    files.append(os.path.join(root_path, "schemas", "lebenslauf.schema.json"))
    files.extend(yaml_data.files_fn(yaml_path, yaml_dir)())
    files.extend(twig.files_fn()())
    return unique_paths(files)


def build_state_dirty(process_env, root_path, demo):
    snapshot = snapshot_state(build_inputs(root_path, process_env, demo))
    stored = read_state(build_state_path(root_path, process_env))
    return snapshot != stored


def env_state_dirty(process_env, root_path):
    snapshot = snapshot_state(env_files(root_path, process_env))
    stored = read_state(env_state_path(root_path, process_env))
    return snapshot != stored


def build_state_path(root_path, process_env):
    pipeline = process_env.get("PIPELINE", "dev")
    profile = process_env.get("PROFILE", "")
    suffix = f"-{profile}" if profile else ""
    name = f"{pipeline}{suffix}.json"
    return os.path.join(root_path, "var", "state", "build", name)


def env_state_path(root_path, process_env):
    pipeline = process_env.get("PIPELINE", "dev")
    phase = "runtime"
    profile = process_env.get("PROFILE", "")
    suffix = f"-{profile}" if profile else ""
    name = f"{pipeline}-{phase}{suffix}.json"
    return os.path.join(root_path, "var", "state", "env", name)


def snapshot_state(paths):
    snapshot = {}
    for path in unique_paths(paths):
        if not os.path.isfile(path):
            snapshot[path] = {"missing": True}
            continue
        snapshot[path] = {
            "mtime": os.path.getmtime(path),
            "size": os.path.getsize(path),
        }
    return snapshot


def read_state(path):
    if not os.path.isfile(path):
        return {}
    try:
        with open(path, "r", encoding="utf-8") as handle:
            data = json.load(handle)
    except Exception:
        return {}
    return data if isinstance(data, dict) else {}


def write_state(path, paths):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    snapshot = snapshot_state(paths)
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(snapshot, handle, indent=2, ensure_ascii=False)


def unique_paths(paths):
    items = []
    for value in paths:
        if isinstance(value, str) and value:
            items.append(value)
    return sorted(set(items))


def run_event_loop(processes, watch_manager):
    try:
        while True:
            for proc in processes:
                exit_code = proc.poll()
                if exit_code is not None:
                    terminate_processes(processes, exit_code)
            watch_manager.poll()
            time.sleep(0.2)
    except KeyboardInterrupt:
        terminate_processes(processes, 0)


def resolve_root_path():
    return os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))


def setup_processes(process_env, root_path):
    processes = []
    processes.extend(css.start())
    processes.append(start_php_server(process_env, root_path))
    return processes


def register_signal_handlers(processes):
    def handle_signal(_signum, _frame):
        terminate_processes(processes, 0)

    signal.signal(signal.SIGINT, handle_signal)
    signal.signal(signal.SIGTERM, handle_signal)


def main():
    args = parse_args()
    root_path = resolve_root_path()
    os.chdir(root_path)

    process_env = build_runtime_env(args)
    ensure_env_compiled(process_env, root_path)
    ensure_initial_build(args, process_env, root_path)

    watch_manager = manager.WatchManager()
    processes = setup_processes(process_env, root_path)
    register_signal_handlers(processes)

    register_watchers(watch_manager, process_env, root_path, args.demo)
    run_event_loop(processes, watch_manager)


if __name__ == "__main__":
    main()
