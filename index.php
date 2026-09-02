<?php
// Version
require_once(__DIR__ . '/system/version.php');

// Configuration
$config_file = __DIR__ . '/config.php';

if (is_file($config_file) && filesize($config_file)) {
	try {
		require_once($config_file);
	} catch (\Throwable $e) {
		// An incomplete fresh-install configuration is handled below.
	}
}

// Install
foreach (['DIR_OPENCART', 'DIR_IMAGE', 'DIR_SYSTEM', 'DIR_STORAGE', 'DIR_CONFIG', 'DIR_CACHE', 'DIR_LOGS', 'DIR_SESSION', 'DIR_UPLOAD', 'DB_DRIVER', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
	if (!defined($constant)) {
		header('Location: install/index.php');
		exit();
	}
}

if (!defined('HTTP_APP')) {
	header('Location: install/index.php');
	exit();
}

// App context
define('APPLICATION', 'App');
define('HTTP_SERVER', HTTP_APP);
define('DIR_APPLICATION', __DIR__ . '/app/');
define('DIR_API', __DIR__ . '/api/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Framework
require_once(DIR_SYSTEM . 'framework.php');
