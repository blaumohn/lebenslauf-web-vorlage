import os
import signal
import subprocess
import time
from urllib.request import Request, urlopen


def start(clone_path):
    popen_kwargs = {"cwd": clone_path}
    if os.name == "nt":
        popen_kwargs["creationflags"] = subprocess.CREATE_NEW_PROCESS_GROUP
    else:
        popen_kwargs["preexec_fn"] = os.setsid
    cmd = ["composer", "run", "dev"]
    return subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, **popen_kwargs)


def stop(proc):
    if os.name == "nt":
        proc.terminate()
        try:
            proc.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proc.kill()
        return
    try:
        os.killpg(os.getpgid(proc.pid), signal.SIGTERM)
    except ProcessLookupError:
        return
    try:
        proc.wait(timeout=10)
    except subprocess.TimeoutExpired:
        os.killpg(os.getpgid(proc.pid), signal.SIGKILL)


def wait(url, process, retries=20, delay=0.5):
    for _ in range(retries):
        exit_code = process.poll()
        if exit_code is not None:
            stdout, stderr = process.communicate(timeout=2)
            raise RuntimeError(
                "Dev-Server ist beendet.\n"
                f"Exit-Code: {exit_code}\n"
                f"STDOUT:\n{stdout}\n"
                f"STDERR:\n{stderr}"
            )
        try:
            fetch(url)
            return
        except Exception:
            time.sleep(delay)
    raise RuntimeError(f"Server nicht erreichbar: {url}")


def fetch(url):
    req = Request(url, headers={"User-Agent": "lebenslauf-smoke"})
    with urlopen(req, timeout=5) as response:
        body = response.read()
    return body.decode("utf-8", errors="replace")
