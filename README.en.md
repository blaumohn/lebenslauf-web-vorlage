# Resume Template (PHP)

[Deutsch](README.md) | [English](#resume-template-php)

Shared-hosting-friendly PHP MVP with Twig, file-based persistence, and no
cookies.

This repository contains the application source code.
Public project docs do not live under `docs/` here. They live in GitHub Pages:

- Public docs: <https://docs.template.ysdani.com/en/>
- GitHub Pages repo: <https://github.com/blaumohn/lebenslauf-web-vorlage-docs>
- Source repo: <https://github.com/blaumohn/lebenslauf-web-vorlage>

Until preview deployment is finished, `dev` is the relevant working branch.
The links here stay branch-neutral on purpose.

## Local start

```bash
composer install
php bin/cli setup dev
php bin/cli run dev
```

Optional sample seed without overwriting existing content:

```bash
php bin/cli setup dev --copy-sample-content
```

`run` compiles the runtime config to `var/config/config.php`.

Create `.local/dev-runtime.yaml` before the first run
(see `src/resources/config/dev-runtime.yaml`).

The same commands are also available as `composer` scripts.

Requirements: PHP >= 8.1, Node.js, Python 3.
Defaults come from YAML config files (see `src/resources/config/`).
If no `.local/dev-runtime.yaml` exists, copy the fixture from
`tests/fixtures/dev-runtime.yaml`.
`php bin/cli setup` runs `npm install`.
`php bin/cli run` starts the Python dev runner (option: `--build`).
Note: `setup` creates `.venv` unless `--skip-python` is used.
The sample seed uses the fixed fixture
`src/resources/fixtures/lebenslauf/daten-gueltig.yaml` and copies it to
`.local/lebenslauf/daten-<LEBENSLAUF_PUBLIC_PROFILE>.yaml`. The profile value
must be allowed and set for the setup phase in the pipeline spec; in `dev`, the
setup value comes from `src/resources/config/dev-setup.yaml`. If the target
already exists, setup fails explicitly instead of overwriting local data.

## More docs

- Getting started: <https://docs.template.ysdani.com/en/getting-started/>
- Operations and runbooks: <https://docs.template.ysdani.com/en/operations/>
- Policies and decisions: <https://docs.template.ysdani.com/en/policies/>

## CLI syntax

```bash
# Lifecycle
php bin/cli setup <pipeline>
php bin/cli build <pipeline> [cv|css|upload]
php bin/cli run <pipeline> [--build]
php bin/cli python <pipeline> [--add-path <path>] <script> [args...]

# Content
php bin/cli build <pipeline> cv
php bin/cli build <pipeline> upload <cv-profile> <json>

# Security
php bin/cli token rotate <profile> [count]
php bin/cli captcha <pipeline> [cleanup]
php bin/cli ip-hash reset
```

## CLI model

Phases are executed directly:

```text
cli <phase> <pipeline> [args]
```

Examples:

- `php bin/cli setup dev`
- `php bin/cli build dev cv`
- `php bin/cli run dev`
- `php bin/cli python dev --add-path . tests/py/smoke.py`
- `php bin/cli ip-hash reset`

## Python runner

- Phase: `python`
- Defaults: `src/resources/config/dev-python.yaml`
- Keys: `PYTHON_CMD`, `PYTHON_PATHS` (e.g. `src`)
- Extra import paths via CLI: `--add-path <path>`

## Build + dev (YAML -> JSON -> HTML)

```bash
php bin/cli build dev cv
php bin/cli build dev
```

`build <pipeline> cv` converts YAML to JSON and renders the static HTML via
`build <pipeline> upload`.
If `LEBENSLAUF_DATEN_PFAD` is a directory, all `daten-<profile>.yaml`
files are built.

## Configuration

- Use `src/resources/config/<PIPELINE>-<PHASE>.yaml` and
  `.local/<PIPELINE>-<PHASE>.yaml` (see `docs/ENVIRONMENTS.md`).
- Example values live in `src/resources/config/dev-runtime.yaml`.
- Important folders:
  - `var/tmp/` short-lived (CAPTCHA + rate limits)
  - `var/cache/` derived (rendered HTML)
  - `var/state/` important (token whitelist)
- Labels for section titles: `src/resources/build/labels.json`.
- Page texts (title/contact) live in Twig templates.
- Config rules live in `src/resources/config/config.manifest.yaml`.

Relevant config keys:
- `LEBENSLAUF_PUBLIC_PROFILE` (build; in `dev`, also setup seed)
- `LEBENSLAUF_LANG_DEFAULT`, `LEBENSLAUF_LANGS` (runtime)
- `CONTACT_TO_EMAIL`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` (runtime)
- `MAIL_STDOUT` is the shared runtime switch; in `dev` this leaves only the
  stdout path in the contract
- `SMTP_*` belongs only to the `preview` runtime path in the current target
  state and is documented through `meta.notes` in the manifest

Details on environments and variables: `docs/ENVIRONMENTS.md`.
Deployments use `var/config/config.php` as the compiled runtime config
(`php bin/cli config compile <pipeline>`).

Preview build in CI: `composer install --no-dev --optimize-autoloader
--no-interaction` + `php bin/cli setup preview` + `php bin/cli build preview`
(deploy dir via `bin/ci/preview-copy.sh`).
FTP target path for preview: environment variable `FTP_SERVER_DIR`.
Base path for preview without rewrite: `APP_BASE_PATH` (e.g. `/public`).

## Admin workflows (CLI)

### Upload CV

```bash
php bin/cli build <PIPELINE> upload <CV_PROFILE> <JSON_PATH>
```

Creates `var/cache/html/cv-private-<profile>.<lang>.html` per language.
If `<CV_PROFILE>` equals `LEBENSLAUF_PUBLIC_PROFILE`, it also creates
`cv-public.<lang>.html`.
The default language (from `LEBENSLAUF_LANG_DEFAULT`) also writes legacy
files `cv-private-<profile>.html` and `cv-public.html`.
JSON is validated against
`src/resources/build/schemas/lebenslauf.schema.json`.

### Rotate tokens

```bash
php bin/cli token rotate <TOKEN_PROFILE> [COUNT]
```

Outputs new tokens once and stores hashes only in
`var/state/tokens/<profile>.txt`.

### CAPTCHA cleanup

```bash
php bin/cli captcha <PIPELINE>
```

Deletes expired CAPTCHA files.

## Tests

```bash
composer run test
```

## Smoke tests

```bash
composer run tests:smoke
```

The smoke test clones the repo into a temporary directory, installs
dependencies, runs `setup` and `test`, and checks the dev server via `curl`.
Mock data for the setup seed comes from
`src/resources/fixtures/lebenslauf/daten-gueltig.yaml`.

Optional environment variables:
- `CLONE_SOURCE` sets a local source or Git URL (default: local repo).
- `KEEP_SMOKE_CLONE=1` keeps the temporary clone.

## Templates

- Templates use Twig macros instead of includes.
- Base UI building blocks:
  `src/resources/templates/components/site/lib.html.twig`
- Layout/navigation:
  `src/resources/templates/components/site/layout.html.twig`
- Form elements:
  `src/resources/templates/components/site/form.html.twig`
- CV:
  `src/resources/templates/components/cv/lib.html.twig`,
  `src/resources/templates/components/cv/sections.html.twig`,
  `src/resources/templates/components/cv/entry.html.twig`
- CV layout macros:
  `src/resources/templates/components/cv/view.html.twig`,
  `src/resources/templates/components/cv/page.html.twig`

## Staging/Pre-release

- Use dummy data.
- Check file permissions for `var/`.
- Test PHP-GD and mail.
