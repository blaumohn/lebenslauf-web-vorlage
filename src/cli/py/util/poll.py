import time


def poll_until(check_fn, timeout_s: int, interval_s: int, label: str = "") -> object:
    deadline = time.monotonic() + timeout_s
    while time.monotonic() < deadline:
        result = check_fn()
        if result is not None:
            return result
        time.sleep(interval_s)
    suffix = f": {label}" if label else ""
    raise TimeoutError(f"Timeout nach {timeout_s}s{suffix}")
