import json
import os


def main():
    data = json.loads(os.environ["PIPELINE_CFG_JSON"])
    print(data["runtime"]["MAIL_TO_EMAIL"], end="")


if __name__ == "__main__":
    main()
