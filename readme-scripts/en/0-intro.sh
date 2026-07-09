# # Shared Hosting Site Toolkit
#
# Build, runtime, and deploy scaffold for small PHP shared-hosting sites
# ([why shared hosting and PHP](https://ysdani.com/blog/warum-php-shared-hosting)).
# The template layer (renderers, schemas) carries multilingual content; the
# PHP runtime brings reusable modules such as token management, security
# (rate limiting, CAPTCHA), and task dispatching. The current career-profile
# template — **home page, resume, contact, blog** — is the first concrete
# application on top of it.
#
# Target picture: template repos import the scaffold via Composer — for
# small, lightly dynamic websites such as career profiles or small-business
# catalogues without e-commerce. A new site should then only need
# configuration, content, templates and CSS. For now, scaffold and reference
# template still live in one repository; the roadmap holds the extraction as
# a versioned Composer package and optional modules — a site loads only what
# it needs, keeping the vendor tree small.
#
# Division of labour in the code: PHP owns the HTTP runtime and the public
# `cli`, Python owns build and deploy automation; Bash runs only inside CI
# containers.
#
# [ysdani.com](https://ysdani.com) runs this template in production — selected
# operations and architecture decisions behind it are documented on the
# [blog](https://ysdani.com/blog), each entry linked to the code.
# [preview.ysdani.com](https://preview.ysdani.com) is its preview deploy.
#
# **What can it do?**
#
# <!-- TOC -->
