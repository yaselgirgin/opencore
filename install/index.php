<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

$https = (isset($_SERVER['HTTPS']) && in_array($_SERVER['HTTPS'], ['on', '1'], true))
	|| (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
	|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
	|| (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
$protocol = $https ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$install_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/');
$root_path = preg_replace('~/install$~', '', $install_path) ?: '';

define('APPLICATION', 'Install');
define('HTTP_SERVER', $protocol . $host . $install_path . '/');
define('HTTP_OPENCART', $protocol . $host . $root_path . '/');
define('INSTALL_ADMIN_DIRECTORY', 'admin');

$opencart_path = str_replace('\\', '/', realpath(__DIR__ . '/../')) . '/';
$config_file = $opencart_path . 'config.php';
$configured = false;

if (is_readable($config_file)) {
	$config_source = file_get_contents($config_file);
	$required = ['HTTP_CATALOG', 'DIR_OPENCART', 'DIR_IMAGE', 'DIR_SYSTEM', 'DIR_STORAGE', 'DIR_CONFIG', 'DIR_CACHE', 'DIR_LOGS', 'DIR_SESSION', 'DIR_UPLOAD', 'DB_DRIVER', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'];
	$configured = is_string($config_source);

	foreach ($required as $constant) {
		if (!preg_match("~define\\(\\s*['\"]" . $constant . "['\"]\\s*,~", $config_source)) {
			$configured = false;
			break;
		}
	}

	if ($configured) {
		require_once($config_file);
	}
}

defined('DIR_OPENCART') || define('DIR_OPENCART', $opencart_path);
define('DIR_APPLICATION', DIR_OPENCART . 'install/');
defined('DIR_IMAGE') || define('DIR_IMAGE', DIR_OPENCART . 'image/');
defined('DIR_SYSTEM') || define('DIR_SYSTEM', DIR_OPENCART . 'system/');
defined('DIR_STORAGE') || define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
defined('DIR_CONFIG') || define('DIR_CONFIG', DIR_SYSTEM . 'config/');
defined('DIR_CACHE') || define('DIR_CACHE', DIR_STORAGE . 'cache/');
defined('DIR_LOGS') || define('DIR_LOGS', DIR_STORAGE . 'logs/');
defined('DIR_SESSION') || define('DIR_SESSION', DIR_STORAGE . 'session/');
defined('DIR_UPLOAD') || define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

define('INSTALL_CONFIGURED', $configured);

require_once(DIR_SYSTEM . 'version.php');
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'framework.php');
