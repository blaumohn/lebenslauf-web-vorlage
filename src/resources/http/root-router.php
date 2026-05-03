<?php

$active = trim(@file_get_contents(__DIR__ . '/active') ?: 'a');
$vendor = trim(@file_get_contents(__DIR__ . '/vendor-active') ?: 'a');

define('APP_VENDOR_DIR', __DIR__ . '/vendor-' . $vendor);

require __DIR__ . '/' . $active . '/public/index.php';
