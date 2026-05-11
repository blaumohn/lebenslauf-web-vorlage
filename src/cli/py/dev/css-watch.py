import signal
import subprocess
import sys
import time

from cli.py.dev.watchers import css


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


def run_event_loop(processes):
    try:
        while True:
            for proc in processes:
                exit_code = proc.poll()
                if exit_code is not None:
                    terminate_processes(processes, exit_code)
            time.sleep(0.2)
    except KeyboardInterrupt:
        terminate_processes(processes, 0)


def main():
    processes = []

    def handle_signal(_signum, _frame):
        terminate_processes(processes, 0)

    signal.signal(signal.SIGINT, handle_signal)
    signal.signal(signal.SIGTERM, handle_signal)

    processes.extend(css.start())
    run_event_loop(processes)


if __name__ == "__main__":
    main()
