# Resume Template (PHP)

[Deutsch](README.md) | [English](#resume-template-php)

PHP template for a CV site on shared hosting: public view with redacted
contact details, token-gated private access, build and deployment
workflow included.
[Full documentation → docs.template.ysdani.com](https://docs.template.ysdani.com/en/)

## Setup

1. **Load dependencies** — install PHP packages, including the CLI.

   ```bash
   composer install
   ```

2. **Set up project** — create directories, install npm packages and
   Python environment. Requires step 1.

   ```bash
   php bin/cli setup dev --reset-sample-content
   ```

3. **Build CV** — render sample data into HTML views.

   ```bash
   php bin/cli build dev
   ```

4. **Start** — compile runtime config and start the development server.

   ```bash
   php bin/cli run dev
   ```

Own data and configuration (email, SMTP, deployment):
[Documentation → docs.template.ysdani.com](https://docs.template.ysdani.com/en/getting-started/)
