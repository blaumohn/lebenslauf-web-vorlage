# ## Prod deploy: after the push
#
# *(Flow not yet anchored in CI.)*
#
# <!-- TODO(Part 1b): post-deploy — rework the existing smoke/QA from bin/ci
#      so its check command lives here. Checks the content sentinel mocked in
#      section 4 (proof that the push actually delivered the prepared
#      content). -->
#
# After the push, GitHub Actions deploys automatically (two-tree slot switch —
# [why two fixed trees](https://ysdani.com/blog/zwei-baeume-statt-symlink-flip),
# [atomic switch](https://ysdani.com/blog/atomarer-htaccess-switch)).
# The content uploaded in "before the push" appears on the prod site — check:
#
# ```bash
# curl --fail --silent "https://<prod-domain>/" | grep -q '<inhalt-marker>'
# ```
