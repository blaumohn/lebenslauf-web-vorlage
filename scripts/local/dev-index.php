<?php

function dev_request_path(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    return rawurldecode($path);
}

function dev_public_file(string $path): string
{
    return app_root_dir() . '/public' . $path;
}

function app_root_dir(): string
{
    $root = getenv('APP_ROOT_DIR');
    if ($root !== false && $root !== '') {
        return $root;
    }
    return dirname(__DIR__, 2);
}

function app_vendor_dir(): string
{
    $vendor = getenv('APP_VENDOR_DIR');
    if ($vendor !== false && $vendor !== '') {
        return $vendor;
    }
    return app_root_dir() . '/vendor';
}

$path = dev_request_path();
$file = dev_public_file($path);

if ($path !== '/' && !str_contains($path, '..') && is_file($file)) {
    return false;
}

define('APP_ROOT_DIR', app_root_dir());
define('APP_VENDOR_DIR', app_vendor_dir());

require APP_ROOT_DIR . '/src/Http/bootstrap.php';
