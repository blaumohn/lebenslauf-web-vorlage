# ## Private Ansicht einrichten
#
# Die private Vollansicht öffnet sich per URL-Token, ohne Login.
#
# ```bash
token="$(cli token dev add demo)"
curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
  | grep -q '</html>'
# ```
