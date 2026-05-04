<?php
{{ state_marker }} tree={{ tree }} vendor={{ vendor }}
define('APP_ROOT_DIR', __DIR__ . '/{{ tree }}');
define('APP_VENDOR_DIR', __DIR__ . '/vendor-{{ vendor }}');
require __DIR__ . '/{{ tree }}/public/index.php';
