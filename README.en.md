<p align="right"><a href="README.md">Deutsch</a> | English</p>

---

# CV Web Template (PHP)

PHP shared-hosting boilerplate „from `<div>` to DevOps" for small sites —
with a career-profile template as an example: **home page, resume,
contact, blog**.

[ysdani.com](https://ysdani.com) runs this template in production — selected
operations and architecture decisions behind it are documented on the
[blog](https://ysdani.com/blog), each entry linked to the code.
[preview.ysdani.com](https://preview.ysdani.com) is its preview deploy.

**What can it do?**

- [Quickstart](#quickstart)
- [Set up private view](#set-up-private-view)
- [Preview deploy](#preview-deploy)
- [Prod deploy: before the push](#prod-deploy-before-the-push)
- [Prod deploy: after the push](#prod-deploy-after-the-push)
- [Publish content](#publish-content)

---

## Quickstart
<small>*[readme-scripts/en/1-schnellstart.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/en/1-schnellstart.sh)*</small>

Show the career-profile template demo locally.

```bash
git clone "$REPLACE_WITH_REPOSITORY_URL" lebenslauf-web-vorlage
cd lebenslauf-web-vorlage
PATH="$PWD/bin:$PATH"  # Note: alternatively use `php bin/cli ...`.
composer install
cli setup dev --with-sample-content
cli build dev
cli start dev > /tmp/lebenslauf-dev-server.log 2>&1 &
```

The demo then runs on <http://127.0.0.1:8080/>.

[back to top](#cv-web-template-php)

---

## Set up private view
<small>*[readme-scripts/en/2-private-ansicht.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/en/2-private-ansicht.sh)*</small>

The private full view opens via a URL token, without login.

```bash
token="$(cli token dev rotate demo)"
curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
  | grep -q '</html>'
```

[back to top](#cv-web-template-php)

---

## Preview deploy
<small>*[readme-scripts/en/3-preview-deploy.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/en/3-preview-deploy.sh)*</small>

*(Flow not yet anchored in CI.)*

<!-- TODO(Part 1b): anchor pre-deploy in runner.py; set aside .local/preview.yaml
     in the prep wrapper (-> .local/preview.benutzer-config.yaml) so that
     `cli config` reports the real missing vars. -->

Prerequisite: shared PHP hosting with SFTP and an SMTP account (e.g. Mailtrap).

Show missing configuration values (examples/descriptions of the variables:
see [`manifest.yaml`](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/src/resources/pipeline-config/manifest.yaml);
the values themselves belong in the GitHub repo secrets, not here):

```bash
cli config preview show
```

Set the reported values — SMTP, SFTP, `app_url`, among others — as
secrets/parameters in the GitHub repo.

Trigger a deploy: a push to `preview` uses fixtures, no content upload:

```bash
git push <preview>
```

[back to top](#cv-web-template-php)

---

## Prod deploy: before the push
<small>*[readme-scripts/en/4-prod-deploy-vor.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/en/4-prod-deploy-vor.sh)*</small>

*(Flow not yet anchored in CI.)*

<!-- TODO(Part 1b): pre-deploy against sftp-server; verify the exact
     content-sftp-upload call syntax. Harness must mock a visible content
     change (sentinel value) before the upload — "after the push" (5)
     checks exactly this sentinel as proof of a successful deploy.
     The `.local` move trick (handoff section 6) runs in the wrapper,
     BEFORE sourcing this script: set aside `.local/prod.yaml`
     (`.local/prod.benutzer-config.yaml`) so that `cli config prod show`
     below reports the real missing vars as on a first run; copy it back
     afterwards. The `mv` stays a pure harness concern (no real first-time
     user has a file to move) — `cli config prod show` and the `cp` hint
     below are real user guidance and stay visible in the readme script. -->

Unlike the preview deploy, `prod` needs the real credentials not only as
GitHub secrets, but also locally in `.local/prod.yaml` — `content-sftp-upload`
reads the pipeline config from the executing machine. (Acknowledged project
weak point: prod credentials therefore exist twice, locally and in GitHub.)

Show missing configuration values:

```bash
cli config prod show
```

Enter the values in `.local/prod.yaml` — copy your prepared file into place:

```bash
cp <deine-vorbereitete-datei> .local/prod.yaml
```

After that: with real content instead of fixtures.

The content is already local: `cli setup … --with-sample-content` (from the
quickstart) copies the demo content to `CONTENT_PATH`; deviate from that as
needed. Upload the content to the server so the deploy uses it:

```bash
cli python prod --phase build scripts/content-sftp-upload.py
```

Trigger the deploy:

```bash
git push <prod>
```

[back to top](#cv-web-template-php)

---

## Prod deploy: after the push
<small>*[readme-scripts/en/5-prod-deploy-nach.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/en/5-prod-deploy-nach.sh)*</small>

*(Flow not yet anchored in CI.)*

<!-- TODO(Part 1b): post-deploy — rework the existing smoke/QA from bin/ci
     so its check command lives here. Checks the content sentinel mocked in
     section 4 (proof that the push actually delivered the prepared
     content). -->

After the push, GitHub Actions deploys automatically (two-tree slot switch).
The content uploaded in "before the push" appears on the prod site — check:

```bash
curl --fail --silent "https://<prod-domain>/" | grep -q '<inhalt-marker>'
```

[back to top](#cv-web-template-php)

---

## Publish content
<small>*[readme-scripts/en/6-inhalt-veroeffentlichen.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/en/6-inhalt-veroeffentlichen.sh)*</small>

*(Flow not yet anchored in CI.)*

<!-- TODO(Part 1b): post-deploy — mock content change + cli publish + assert
     publish success + smoke test that the change appears on prod. -->

After the prod deploy, change a piece of content in `CONTENT_PATH` and
publish the update — without a full redeploy:

```bash
cli publish prod
```

[back to top](#cv-web-template-php)
