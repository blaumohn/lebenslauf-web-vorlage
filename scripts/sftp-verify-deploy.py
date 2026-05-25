import sys

from cli.py.deploy.sftp_deploy_state import SlotStore
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.vendor_sentinel import ComposerInputChecksum
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.util.envvar import env


def main():
    cfg = PipelineCfg("deploy")
    run_id = env("DEPLOY_RUN_ID").require_nonempty().value()
    with SftpClient(cfg) as client:
        store = SlotStore(client)
        slot_map = store.current_slot_map()
        if slot_map is None:
            print(
                "[verify] Kein Deploy-State gefunden",
                file=sys.stderr,
            )
            sys.exit(1)
        stored_run_id = store.run_id_for_app_slot(slot_map.app.label)
        stored_checksum = store.vendor_checksum_for_slot(
            slot_map.vendor.label
        )
        verify_run_id(stored_run_id, run_id)
        verify_checksum(stored_checksum)
    sys.stdout.write(slot_map.vendor.label)


def verify_run_id(stored: str, expected: str) -> None:
    if stored != expected:
        print(
            f"[verify] Run-ID stimmt nicht: "
            f"erwartet={expected!r}, state={stored!r}",
            file=sys.stderr,
        )
        sys.exit(1)


def verify_checksum(stored: str) -> None:
    expected = ComposerInputChecksum.from_repo()
    if stored != expected:
        message = (
            f".meta={stored!r}, "
            f"lokal={expected!r}"
        )
        print(
            f"[verify] Vendor-Checksum stimmt nicht: "
            f"{message}",
            file=sys.stderr,
        )
        sys.exit(1)


if __name__ == "__main__":
    main()
