# CV Web Template (PHP)

[Deutsch](README.md) | English

[Assess the project](#assess-the-project) · [Quickstart](#quickstart) · [Overview](#overview) · [Set up private view](#set-up-private-view) · [Technical highlights](#technical-highlights)

---

## Assess the project
<small>*Docs: [Assess the project](https://docs.template.ysdani.com/en/getting-started/project-profile/)*</small>

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
<small>*[tests/ci/readme-dev-user-flow.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/tests/ci/readme-dev-user-flow.sh#L32-L42)*</small>
<small>*Docs: [Quickstart](https://docs.template.ysdani.com/en/getting-started/quickstart/)*</small>

> ```bash
> git clone "$REPLACE_WITH_REPOSITORY_URL" lebenslauf-web-vorlage
> cd lebenslauf-web-vorlage
> PATH="$PWD/bin:$PATH"  # Hinweis: alternativ `php bin/cli ...` verwenden.
> composer install
> cli setup dev --with-sample-content
> cli build dev
> cli start dev > /tmp/readme-dev-ux-server.log 2>&1 &
> dev_server_pid="$!"
> wait_for_dev_server
> ```

---

## Overview
<small>*Docs: [Overview](https://docs.template.ysdani.com/en/getting-started/overview/)*</small>

> A production-ready PHP site starter for shared hosting: public view with
> redacted contact details, token-gated private full view without login,
> i18n CV content in YAML, and complete dev and CI/CD pipelines.
>
> - **[Public view](https://docs.template.ysdani.com/en/specs/systems/app/)** —
>   CV with redacted contact details.
> - **[Private view](https://docs.template.ysdani.com/en/getting-started/private-view/)** —
>   full view via URL token (`/cv?token=…`), no login required.
> - **[i18n YAML content](https://docs.template.ysdani.com/en/specs/systems/app/)** —
>   CV data in German and English.
> - **[Ready-made pipelines](https://docs.template.ysdani.com/en/areas/cli-build/)** —
>   local development with Docker, CI checks and SFTP deployment.
> - **[Security layer](https://docs.template.ysdani.com/en/areas/http-runtime/)** —
>   rate limiting, CAPTCHA and IP-salt rotation for the contact form.

---

## Set up private view
<small>*[tests/ci/readme-dev-user-flow.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/tests/ci/readme-dev-user-flow.sh#L44-L49)*</small>
<small>*Docs: [Set up private view](https://docs.template.ysdani.com/en/getting-started/private-view/)*</small>

> ```bash
> local token
> token="$(cli token dev rotate default)"
> curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
>   | grep -q '</html>'
> ```

---

## Technical highlights
<small>*Docs: [Technical highlights](https://docs.template.ysdani.com/en/getting-started/technical-highlights/)*</small>

> - **[Two-tree deploy](https://docs.template.ysdani.com/en/jira/issues/J01-135/steps/J01-142/)** —
>   atomic deployment without downtime on shared hosting, no blue-green
>   infrastructure required (vendor-a/b + app-a/b).
> - **[Pipeline spec](https://docs.template.ysdani.com/en/specs/systems/pipeline-spec/)** —
>   language-neutral configuration across PHP, Python and shell boundaries,
>   unidirectional data flow, machine-readable YAML specification.
> - **[Pipeline phase model](https://docs.template.ysdani.com/en/areas/cli-build/)** —
>   the app knows its own runtime state (setup, build, runtime, deploy);
>   separates CLI from HTTP runtime complexity.

---
