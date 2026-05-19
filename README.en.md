# CV Web Template (PHP)

[Deutsch](README.md) | English

[Assess the project](#assess-the-project) · [Quickstart](#quickstart) · [Overview](#overview) · [Set up private view](#set-up-private-view) · [Technical highlights](#technical-highlights)

---

## Assess the project
<small>*Full section: [Assess the project](https://docs.template.ysdani.com/en/getting-started/project-profile/)*</small>

> PHP site starter for personal websites on shared hosting —
> developed and operated as an own project.
> Public view with redacted contact details,
> token-gated private full view without login,
> complete dev and CI/CD pipeline.
>
> **Demo:** [preview.ysdani.com](https://preview.ysdani.com)
>
> The project was built as concrete delivery evidence: from requirements
> through architecture decisions to production deployment.
> The public documentation makes decisions, processes and
> quality records transparently traceable.

---

## Quickstart
<small>*Full section: [Quickstart](https://docs.template.ysdani.com/en/getting-started/quickstart/)*</small>

> ```bash
> composer install
> php bin/cli setup dev --with-sample-content
> php bin/cli build dev
> composer run dev
> ```
>
> `--with-sample-content` creates sample data without overwriting existing data.
> `composer run dev` compiles the runtime configuration and starts the development server.

---

## Overview
<small>*Full section: [Overview](https://docs.template.ysdani.com/en/getting-started/overview/)*</small>

> A production-ready PHP site starter for shared hosting: public view with
> redacted contact details, token-gated private full view without login,
> i18n CV content in YAML, and complete dev and CI/CD pipelines.
>
> - **Public view** — CV with redacted contact details
> - **Private view** — full view via URL token (`/cv?token=…`), no login required
> - **i18n YAML content** — CV data in German and English
> - **Ready-made pipelines** — local development with Docker, CI checks and SFTP deployment
> - **Security layer** — rate limiting, CAPTCHA and IP-salt rotation for the contact form

---

## Set up private view
<small>*Full section: [Set up private view](https://docs.template.ysdani.com/en/getting-started/private-view/)*</small>

> The private view shows the full CV via URL token — no login, no account.
> Set up after `composer run dev`:
>
> 1. **Generate token** — `php bin/cli token rotate preview`
> 2. **Create `.local/preview.yaml`** — set `APP_ROOT_URL` and deployment values.
>    Secure with `chmod 600`: only trusted users may read the file.
> 3. **Set GitHub Secrets/Vars** — required fields from `src/resources/pipeline-config/manifest.yaml`,
>    section `pipelines.preview`, in the GitHub environment `preview`.
> 4. **Check CI locally** — `composer run ci:preview`
> 5. **Open** — `https://domain/cv?token=<token>`

---

## Technical highlights
<small>*Full section: [Technical highlights](https://docs.template.ysdani.com/en/getting-started/technical-highlights/)*</small>

> - **Two-tree deploy** — atomic deployment without downtime on shared hosting,
>   no blue-green infrastructure required (vendor-a/b + app-a/b)
> - **Pipeline spec** — language-neutral configuration across PHP, Python and shell boundaries,
>   unidirectional data flow, machine-readable YAML specification
> - **Pipeline phase model** — the app knows its own runtime state (setup, build, runtime, deploy);
>   separates CLI from HTTP runtime complexity

---
