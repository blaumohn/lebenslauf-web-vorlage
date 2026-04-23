# Resume Template (PHP)

[Deutsch](README.md) | [English](#resume-template-php)

PHP template for a CV site on shared hosting: public view with redacted
contact details, token-gated private access, build and deployment
workflow included.
It builds on the earlier static [lebenslauf-vorlage](https://github.com/blaumohn/lebenslauf-vorlage) template for content and i18n and extends it with today's dynamic PHP layer.
[Full documentation → docs.template.ysdani.com](https://docs.template.ysdani.com/en/)

## Setup

1. **Load dependencies** — install PHP packages, including the CLI.

   ```bash
   composer install
   ```

2. **Set up project** — create directories, install npm packages and
   Python environment. Requires step 1.

   ```bash
   php bin/cli setup dev
   ```

   Optional, without overwriting existing data:

   ```bash
   php bin/cli setup dev --copy-sample-content
   ```

3. **Build CV** — render sample data into HTML views.

   ```bash
   php bin/cli build dev
   ```

4. **Start** — compile runtime config and start the development server.

   ```bash
   composer run dev
   ```

Own data and configuration (email, SMTP, deployment):
[Documentation → docs.template.ysdani.com](https://docs.template.ysdani.com/en/getting-started/)

## Run CI locally

The local CI runs in Docker with separate entry points for `dev` and
`preview`:

```bash
composer run ci:dev
composer run ci:preview
```

For the preview run, the Compose stack stops automatically when
`ci-preview` finishes and is then brought down so the long-lived helper
service `sftp-server` does not keep the command open.

An optional versioned pre-push hook wires that check into every push:

```bash
sh scripts/install-hooks.sh
```
