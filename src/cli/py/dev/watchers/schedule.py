import logging
import os

from . import twig

logger = logging.getLogger(__name__)


def schedule_yaml(observer, root_path, yaml_path, yaml_dir, build_fn):
    if yaml_path:
        watch_dir = os.path.dirname(yaml_path)
        patterns = [yaml_path]
        observer.schedule_watch(
            watch_dir, patterns, lambda: build_fn(root_path)
        )
        logger.info("YAML-Watch aktiv: 1 Datei")
        return
    if yaml_dir and os.path.isdir(yaml_dir):
        patterns = [os.path.join(yaml_dir, "*.yaml"), os.path.join(yaml_dir, "*.yml")]
        observer.schedule_watch(
            yaml_dir, patterns, lambda: build_fn(root_path)
        )
        logger.info("YAML-Watch aktiv: Verzeichnis")
        return
    logger.info("YAML-Watch deaktiviert (LEBENSLAUF_DATEN_PFAD oder LEBENSLAUF_YAML_PFAD setzen).")


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
