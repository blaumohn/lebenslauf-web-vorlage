<?php

function deploy_router_valid_slot($slot)
{
    return in_array($slot, ['a', 'b'], true);
}

function deploy_router_state()
{
    $stateFile = __DIR__ . '/.deploy-state.ini';
    $state = is_file($stateFile) ? parse_ini_file($stateFile, true) : [];
    $state = is_array($state) ? $state : [];
    $section = $state['state'] ?? [];
    $app = deploy_router_valid_slot($section['app'] ?? '') ? $section['app'] : 'a';
    $vendor = deploy_router_valid_slot($section['vendor'] ?? '') ? $section['vendor'] : 'a';
    return [$app, $vendor];
}

function deploy_router_request_path()
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return ltrim(rawurldecode($path), '/');
}

function deploy_router_content_type($path)
{
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return $types[$extension] ?? 'application/octet-stream';
}

function deploy_router_serve_public_file($appRoot)
{
    $path = deploy_router_request_path();
    if ($path === '' || $path === 'index.php' || str_contains($path, '..')) {
        return false;
    }
    $file = $appRoot . '/public/' . $path;
    if (!is_file($file)) {
        return false;
    }
    header('Content-Type: ' . deploy_router_content_type($file));
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

[$app, $vendor] = deploy_router_state();

define('APP_ROOT_DIR', __DIR__ . '/' . $app);
define('APP_VENDOR_DIR', __DIR__ . '/vendor-' . $vendor);

if (deploy_router_serve_public_file(APP_ROOT_DIR)) {
    return;
}

require APP_ROOT_DIR . '/public/index.php';
