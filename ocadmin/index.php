<?php
// Version
require_once(dirname(__DIR__) . '/system/version.php');

// Configuration
if (is_file(dirname(__DIR__) . '/config.php')) {
	require_once(dirname(__DIR__) . '/config.php');

	// Admin context
	define('APPLICATION', 'Admin');
	define('HTTP_SERVER', rtrim(HTTP_CATALOG, '/') . '/' . basename(__DIR__) . '/');
	define('DIR_APPLICATION', __DIR__ . '/');
	define('DIR_CATALOG', dirname(__DIR__) . '/catalog/');
	define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
	define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');

	// Legacy OpenCart notification endpoint
	define('OPENCART_SERVER', 'https://www.opencart.com/');
}

// Installs
if (!defined('DIR_APPLICATION')) {
	header('Location: ../install/index.php');
	exit();
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Framework
require_once(DIR_SYSTEM . 'framework.php');