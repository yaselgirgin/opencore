<?php
// Version
require_once(dirname(__DIR__) . '/system/version.php');

// Configuration
$config_file = dirname(__DIR__) . '/config.php';

if (is_file($config_file) && filesize($config_file)) {
	try {
		require_once($config_file);
	} catch (\Throwable $e) {
		// An incomplete fresh-install configuration is handled below.
	}
}

// Install
foreach (['HTTP_CATALOG', 'DIR_OPENCART', 'DIR_IMAGE', 'DIR_SYSTEM', 'DIR_STORAGE', 'DIR_CONFIG', 'DIR_CACHE', 'DIR_LOGS', 'DIR_SESSION', 'DIR_UPLOAD', 'DB_DRIVER', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
	if (!defined($constant)) {
		header('Location: ../install/index.php');
		exit();
	}
}

// Admin context
define('APPLICATION', 'Admin');
define('HTTP_SERVER', rtrim(HTTP_CATALOG, '/') . '/' . basename(__DIR__) . '/');
define('DIR_APPLICATION', __DIR__ . '/');
define('DIR_CATALOG', dirname(__DIR__) . '/catalog/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');

// Legacy OpenCart notification endpoint
define('OPENCART_SERVER', 'https://www.opencart.com/');

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Framework
require_once(DIR_SYSTEM . 'framework.php');
