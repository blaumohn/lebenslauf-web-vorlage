import sys

from cli.py.deploy.sftp_deploy_state import DeployState
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.vendor_sentinel import ComposerInputChecksum
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.util.envvar import env


def main():
    cfg = PipelineCfg("deploy")
    run_id = env("DEPLOY_RUN_ID").require_nonempty().value()
    with SftpClient(cfg) as client:
        state = DeployState.read(client)
        if state is None:
            print(
                "[verify] Kein Deploy-State gefunden",
                file=sys.stderr,
            )
            sys.exit(1)
        verify_run_id(state, run_id)
        verify_checksum(state)
    sys.stdout.write(state.vendor)


def verify_run_id(state, expected):
    if state.run_id != expected:
        print(
            f"[verify] Run-ID stimmt nicht: "
            f"erwartet={expected!r}, state={state.run_id!r}",
            file=sys.stderr,
        )
        sys.exit(1)


def verify_checksum(state):
    expected = ComposerInputChecksum.from_repo()
    if state.vendor_checksum != expected:
        message = (
            f".meta={state.vendor_checksum!r}, "
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
