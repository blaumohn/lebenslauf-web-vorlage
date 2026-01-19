# Resume Template (PHP)

[Deutsch](README.md) | [English](#resume-template-php)

Shared-hosting-friendly PHP MVP with Twig, file-based persistence, and no cookies.

## Local start

```bash
composer install
php bin/cli setup dev --create-data-templates
php bin/cli build dev
php bin/cli run dev
```

`run` compiles the runtime env to `var/config/env.php`.

Create `.env.local` before the first run (see `.env.template`).

The same commands are also available as `composer` scripts.

Requirements: PHP >= 8.1, Node.js, Python 3.
Defaults come from `.env` files (see `.env.template`).
If no `.env.local` exists, `php bin/cli setup` asks whether to derive it from `.env.template`.
`php bin/cli setup` runs `npm install`. Add `--create-data-templates` to generate `.local/content.ini` and demo YAML files.
`php bin/cli run` starts the Python dev runner (options: `--build`, `--mail-stdout`).
Note: `setup` creates `.venv` using system Python; other commands prefer `.venv`.

## CLI syntax

```bash
# Lifecycle
php bin/cli setup <profile> [--create-data-templates]
php bin/cli build <profile>
php bin/cli run <profile> [--build] [--demo] [--mail-stdout]

# Content
php bin/cli cv build <profile>
php bin/cli cv upload <profile> <json>

# Security
php bin/cli token rotate <profile> [count]
php bin/cli captcha cleanup
```

## Build + dev (YAML -> JSON -> HTML)

```bash
php bin/cli cv build dev
php bin/cli build dev
```

`cv build` converts YAML to JSON and renders the static HTML via `cv upload`.
If `LEBENSLAUF_DATEN_PFAD` is a directory, all `daten-<profile>.yaml` files are built.
`run --demo` uses the fixture data without touching `.local`.

## Configuration

- Use `.env`/`.env.local` and `.env.<PIPELINE>` variants (see `docs/ENVIRONMENTS.md`).
- Content config lives in `.local/content.ini` (site name, languages, profiles, contact texts).
- Example env values live in `.env.template`.
- Important folders:
  - `var/tmp/` short-lived (CAPTCHA + rate limits)
  - `var/cache/` derived (rendered HTML)
  - `var/state/` important (token whitelist)
- Labels for section titles: `src/resources/labels.json`.
- Multilingual output is controlled via `.local/content.ini`.
- Env rules live in `config/env.manifest.yaml`.

Details on environments and variables: `docs/ENVIRONMENTS.md`.
Deployments use `var/config/env.php` as the compiled runtime env (`php bin/cli env compile`).

Preview build in CI: `composer install --no-dev --optimize-autoloader --no-interaction` + `php bin/cli setup preview` + `php bin/cli build preview` (deploy dir via `bin/ci/preview-copy.sh`).
FTP target path for preview: environment variable `FTP_SERVER_DIR`.
Base path for preview without rewrite: `APP_BASE_PATH` (e.g. `/public`).

## Admin workflows (CLI)

### Upload CV

```bash
php bin/cli cv upload <PROFILE> <JSON_PATH>
```

Creates `var/cache/html/cv-private-<profile>.<lang>.html` per language. If `<PROFILE>` equals the default profile from `.local/content.ini`, it also creates `cv-public.<lang>.html`.
The default language (from `.local/content.ini`) also writes legacy files `cv-private-<profile>.html` and `cv-public.html`.
JSON is validated against `schemas/lebenslauf.schema.json`.
Note: `APP_ENV` must be set (optional via `--app-env <profile>`).

### Rotate tokens

```bash
php bin/cli token rotate <PROFILE> [COUNT]
```

Outputs new tokens once and stores hashes only in `var/state/tokens/<profile>.txt`.

### CAPTCHA cleanup

```bash
php bin/cli captcha cleanup
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

The smoke test clones the repo into a temporary directory, installs dependencies, runs `setup` and `test`, and checks the dev server via `curl`.
Mock data comes from `tests/fixtures/lebenslauf/daten-gueltig.yaml`.

Optional environment variables:
- `CLONE_SOURCE` sets a local source or Git URL (default: local repo).
- `KEEP_SMOKE_CLONE=1` keeps the temporary clone.

## Templates

- Templates use Twig macros instead of includes.
- Base UI building blocks: `src/resources/templates/components/site/lib.html.twig`
- Layout/navigation: `src/resources/templates/components/site/layout.html.twig`
- Form elements: `src/resources/templates/components/site/form.html.twig`
- CV: `src/resources/templates/components/cv/lib.html.twig`, `src/resources/templates/components/cv/sections.html.twig`, `src/resources/templates/components/cv/entry.html.twig`
- CV layout macros: `src/resources/templates/components/cv/view.html.twig`, `src/resources/templates/components/cv/page.html.twig`

## Staging/Pre-release

- Use dummy data.
- Check file permissions for `var/`.
- Test PHP-GD and mail.
