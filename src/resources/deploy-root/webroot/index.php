<?php

require __DIR__ . '/deploy-state.php';

function deploy_router_request_path()
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    return ltrim(rawurldecode($path), '/');
}

function deploy_router_log($message)
{
    error_log('[deploy-router] ' . $message);
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
    if ($path === '' || $path === 'index.php') {
        return false;
    }
    if (str_contains($path, '..')) {
        deploy_router_log("Gesperrter Pfad: {$path}");
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

try {
    $stateFile = __DIR__ . '/../.deploy-state.ini';
    $state = DeployRuntimeState::fromIniFile($stateFile);
} catch (Throwable $error) {
    deploy_router_log('Deploy-State ungültig: ' . $error->getMessage());
    http_response_code(500);
    echo 'Deploy-State ungültig.';
    return;
}

define('APP_ROOT_DIR', __DIR__ . '/../' . $state->appDir());
define('APP_VENDOR_DIR', __DIR__ . '/../' . $state->vendorDir());

if (deploy_router_serve_public_file(APP_ROOT_DIR)) {
    return;
}

$bootstrap = APP_ROOT_DIR . '/src/Http/bootstrap.php';
if (!is_file($bootstrap)) {
    deploy_router_log("HTTP-Bootstrap fehlt: {$bootstrap}");
    http_response_code(500);
    echo 'HTTP-Bootstrap fehlt.';
    return;
}

require $bootstrap;
