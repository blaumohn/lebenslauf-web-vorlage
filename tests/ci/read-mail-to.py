from cli.py.pipeline_cfg import PipelineCfg


def main():
    cfg = PipelineCfg("runtime")
    print(cfg["MAIL_TO_EMAIL"], end="")


if __name__ == "__main__":
    main()
