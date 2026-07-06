# ## Set up private view
#
# The private full view opens via a URL token, without login.
#
# ```bash
token="$(cli token dev add demo)"
curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
  | grep -q '</html>'
# ```
