<?php
// Version
require_once(dirname(__DIR__) . '/system/version.php');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Installs
if (!defined('DIR_APPLICATION')) {
	header('Location: ../install/index.php');
	exit();
}

require_once(DIR_SYSTEM . 'helper/update_gate.php');

$route = isset($_GET['route']) && is_string($_GET['route']) ? $_GET['route'] : '';

if (oc_update_gate_active(DIR_STORAGE) && !oc_update_gate_admin_route_allowed($route)) {
	http_response_code(503);
	header('Content-Type: text/plain; charset=utf-8');
	header('Retry-After: 60');
	echo 'OpenCore update recovery is in progress.';
	exit();
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Framework
require_once(DIR_SYSTEM . 'framework.php');
