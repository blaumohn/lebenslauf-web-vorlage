RewriteEngine On
RewriteCond %{THE_REQUEST} \s/(?:a|b|vendor-a|vendor-b)(?:/|\s|\?)
RewriteRule ^ - [R=404,L]
RewriteRule ^index\.php$ - [L]
RewriteCond %{DOCUMENT_ROOT}/{{ tree }}/public/$1 -f
RewriteRule ^(.+)$ {{ tree }}/public/$1 [END]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
