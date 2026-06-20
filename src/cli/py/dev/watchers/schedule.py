import logging
import os

from . import twig

logger = logging.getLogger(__name__)


def schedule_yaml(observer, root_path, data_path, build_fn):
    if data_path and os.path.isdir(data_path):
        patterns = [os.path.join(data_path, "*.yaml")]
        observer.schedule_watch(
            data_path, patterns, lambda: build_fn(root_path)
        )
        logger.info("YAML-Watch aktiv: Verzeichnis")
        return
    logger.info("YAML-Watch deaktiviert (CONTENT_PATH setzen).")


def schedule_twig(observer, root_path, build_fn):
    if not twig.enabled():
        logger.info("Twig-Watch deaktiviert (Vorlagenverzeichnis fehlt).")
        return
    watch_dir = os.path.join(root_path, "src", "resources", "templates")
    patterns = [os.path.join(watch_dir, "**", "*.twig")]
    observer.schedule_watch(
        watch_dir,
        patterns,
        lambda: build_fn(root_path),
        recursive=True,
    )
    print("Twig-Watch aktiv", flush=True)
