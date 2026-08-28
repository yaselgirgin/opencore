<?php
// Error Reporting
error_reporting(E_ALL);

// Check Version
if (version_compare(PHP_VERSION, '8.0', '<')) {
	exit('PHP8.0+ Required');
}

if (!ini_get('date.timezone')) {
	date_default_timezone_set('UTC');
}

function oc_bootstrap_delete_directory(string $path): bool {
	if (!file_exists($path)) {
		return true;
	}

	if (!is_dir($path)) {
		return unlink($path);
	}

	$items = scandir($path);

	if ($items === false) {
		return false;
	}

	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}

		if (!oc_bootstrap_delete_directory($path . '/' . $item)) {
			return false;
		}
	}

	return rmdir($path);
}

function oc_bootstrap_copy_directory(string $source, string $target): bool {
	if (!is_dir($source)) {
		return false;
	}

	if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
		return false;
	}

	$items = scandir($source);

	if ($items === false) {
		return false;
	}

	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}

		$source_path = $source . '/' . $item;
		$target_path = $target . '/' . $item;

		if (is_dir($source_path)) {
			if (!oc_bootstrap_copy_directory($source_path, $target_path)) {
				return false;
			}
		} elseif (!copy($source_path, $target_path)) {
			return false;
		}
	}

	return true;
}

$internal_storage = rtrim(str_replace('\\', '/', DIR_SYSTEM), '/') . '/storage';
$runtime_storage = rtrim(str_replace('\\', '/', DIR_STORAGE), '/');
$external_storage = DIRECTORY_SEPARATOR === '\\' ? strcasecmp($runtime_storage, $internal_storage) !== 0 : $runtime_storage !== $internal_storage;

if ($external_storage) {
	$source_vendor = $internal_storage . '/vendor';
	$target_vendor = $runtime_storage . '/vendor';

	if (is_dir($source_vendor)) {
		if (!oc_bootstrap_delete_directory($target_vendor)) {
			throw new \RuntimeException('Could not remove the existing external vendor directory.');
		}

		if (!@rename($source_vendor, $target_vendor)) {
			if (!oc_bootstrap_copy_directory($source_vendor, $target_vendor) || !oc_bootstrap_delete_directory($source_vendor)) {
				throw new \RuntimeException('Could not replace the external vendor directory.');
			}
		}

		if (!is_file($target_vendor . '/autoload.php')) {
			throw new \RuntimeException('The external vendor directory is incomplete.');
		}

		if (!oc_bootstrap_delete_directory($internal_storage)) {
			throw new \RuntimeException('Could not remove the internal release storage directory.');
		}
	}
}

// Windows IIS Compatibility
if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['SCRIPT_FILENAME'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['PATH_TRANSLATED'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];

	if (isset($_SERVER['QUERY_STRING'])) {
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
	}
}

if (!isset($_SERVER['HTTP_HOST'])) {
	$_SERVER['HTTP_HOST'] = getenv('HTTP_HOST');
}

// Check if SSL
if ((isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) || (isset($_SERVER['HTTPS']) && (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443))) {
	$_SERVER['HTTPS'] = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
	$_SERVER['HTTPS'] = true;
} else {
	$_SERVER['HTTPS'] = false;
}

// OpenCart Autoloader
require_once(DIR_SYSTEM . 'engine/autoloader.php');

// Need config to store application values
require_once(DIR_SYSTEM . 'engine/config.php');

// Helper
require_once(DIR_SYSTEM . 'helper/general.php');
require_once(DIR_SYSTEM . 'helper/filter.php');
require_once(DIR_SYSTEM . 'helper/validation.php');
