# ## Quickstart
#
# Show the career-profile template demo locally.
#
# ```bash
git clone "$REPLACE_WITH_REPOSITORY_URL" lebenslauf-web-vorlage
cd lebenslauf-web-vorlage
PATH="$PWD/bin:$PATH"  # Note: alternatively use `php bin/cli ...`.
composer install
cli setup dev --with-sample-content
cli build dev
cli start dev > /tmp/lebenslauf-dev-server.log 2>&1 &
# ```
#
# The demo then runs on <http://127.0.0.1:8080/>.
