<?php

// Router für `php -S` (siehe scripts/web_checks.sh:start_php_server).
// Ohne dieses Skript: https://github.com/php/php-src/issues/12604
// ("Built-in server assumes static content if there's a '.' in the URL").

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
