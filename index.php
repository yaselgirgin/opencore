<?php
// Version
require_once(__DIR__ . '/system/version.php');

// Configuration
if (is_file(__DIR__ . '/config.php')) {
	require_once(__DIR__ . '/config.php');

	// Catalog context
	define('APPLICATION', 'Catalog');
	define('HTTP_SERVER', HTTP_CATALOG);
	define('DIR_APPLICATION', __DIR__ . '/catalog/');
	define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
	define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: install/index.php');
	exit();
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Framework
require_once(DIR_SYSTEM . 'framework.php');
