# ## Schnellstart
#
# Lokal die Demo der Karriere-Profil-Vorlage zeigen.
#
# ```bash
git clone "$REPLACE_WITH_REPOSITORY_URL" shared-hosting-site-toolkit
cd shared-hosting-site-toolkit
PATH="$PWD/bin:$PATH"  # Hinweis: alternativ `php bin/cli ...` verwenden.
composer install
cli setup dev --with-sample-content
cli build dev
cli start dev > /tmp/lebenslauf-dev-server.log 2>&1 &
# ```
#
# Die Demo läuft danach auf <http://127.0.0.1:8080/>.
