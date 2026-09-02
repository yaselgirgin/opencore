<?php
namespace Opencart\App\Model\Tool;

/**
 * Class RuntimeDiagnostics
 *
 * @package Opencart\App\Model\Tool
 */
class RuntimeDiagnostics extends \Opencart\System\Engine\Model {
	/**
	 * @return array<string, array<int, array<string, bool|int|string|null>>>
	 */
	public function getDiagnostics(): array {
		$database_version = $this->getDatabaseVersion();

		return [
			'opencore' => [
				['name' => 'installed_version', 'value' => VERSION, 'status' => 'ok'],
				['name' => 'latest_stable_version', 'value' => null, 'status' => 'warning'],
				['name' => 'database_version', 'value' => $database_version, 'status' => $database_version === null ? 'error' : 'ok'],
				$this->getDatabaseCompatibility($database_version)
			],
			'environment' => [
				['name' => 'php_version', 'value' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'error'],
				['name' => 'database_server', 'value' => $this->getDatabaseServerVersion(), 'status' => 'ok'],
				$this->getExtension('mysqli', extension_loaded('mysqli')),
				$this->getExtension('curl', extension_loaded('curl')),
				$this->getExtension('openssl', function_exists('openssl_encrypt')),
				$this->getExtension('zip', extension_loaded('zip')),
				$this->getExtension('image', extension_loaded('gd') || extension_loaded('imagick')),
				['name' => 'file_uploads', 'value' => (bool)ini_get('file_uploads'), 'type' => 'boolean', 'status' => ini_get('file_uploads') ? 'ok' : 'error'],
				['name' => 'memory_limit', 'value' => (string)ini_get('memory_limit'), 'status' => 'ok'],
				['name' => 'upload_max_filesize', 'value' => (string)ini_get('upload_max_filesize'), 'status' => 'ok'],
				['name' => 'post_max_size', 'value' => (string)ini_get('post_max_size'), 'status' => 'ok'],
				['name' => 'max_execution_time', 'value' => (string)ini_get('max_execution_time'), 'status' => 'ok']
			],
			'paths' => [
				$this->getPath('app_path', DIR_APPLICATION),
				$this->getPath('storage_path', DIR_STORAGE),
				$this->getInstallPath(),
				$this->getWritablePath('storage_writable', DIR_STORAGE),
				$this->getWritablePath('cache_writable', DIR_CACHE),
				$this->getWritablePath('logs_writable', DIR_LOGS),
				$this->getWritablePath('session_writable', DIR_SESSION),
				$this->getWritablePath('upload_writable', DIR_UPLOAD)
			]
		];
	}

	/**
	 * @return int|null
	 */
	private function getDatabaseVersion(): ?int {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version' LIMIT 1");

		if (!$query->num_rows) {
			return null;
		}

		$value = trim((string)$query->row['value']);

		return ctype_digit($value) && (int)$value > 0 ? (int)$value : null;
	}

	/**
	 * @param int|null $database_version
	 *
	 * @return array{name: string, value: int|null, target: int, state: string, status: string}
	 */
	private function getDatabaseCompatibility(?int $database_version): array {
		if ($database_version === null) {
			return ['name' => 'database_compatibility', 'value' => null, 'target' => DATABASE_VERSION, 'state' => 'invalid', 'status' => 'error'];
		}

		if ($database_version === DATABASE_VERSION) {
			return ['name' => 'database_compatibility', 'value' => $database_version, 'target' => DATABASE_VERSION, 'state' => 'compatible', 'status' => 'ok'];
		}

		if ($database_version < DATABASE_VERSION) {
			return ['name' => 'database_compatibility', 'value' => $database_version, 'target' => DATABASE_VERSION, 'state' => 'upgrade_required', 'status' => 'error'];
		}

		return ['name' => 'database_compatibility', 'value' => $database_version, 'target' => DATABASE_VERSION, 'state' => 'newer', 'status' => 'error'];
	}

	/**
	 * @return string
	 */
	private function getDatabaseServerVersion(): string {
		$query = $this->db->query('SELECT VERSION() AS `version`');

		return (string)$query->row['version'];
	}

	/**
	 * @return array{name: string, value: bool, type: string, status: string}
	 */
	private function getExtension(string $name, bool $loaded): array {
		return ['name' => $name, 'value' => $loaded, 'type' => 'boolean', 'status' => $loaded ? 'ok' : 'error'];
	}

	/**
	 * @return array{name: string, value: string, status: string}
	 */
	private function getPath(string $name, string $path): array {
		return ['name' => $name, 'value' => $path, 'status' => is_dir($path) ? 'ok' : 'error'];
	}

	/**
	 * @return array{name: string, value: string, state: string, status: string}
	 */
	private function getInstallPath(): array {
		$path = DIR_OPENCART . 'install/';

		return ['name' => 'install_path', 'value' => $path, 'state' => is_dir($path) ? 'present' : 'removed', 'status' => is_dir($path) ? 'warning' : 'ok'];
	}

	/**
	 * @return array{name: string, value: bool, type: string, status: string}
	 */
	private function getWritablePath(string $name, string $path): array {
		return ['name' => $name, 'value' => is_writable($path), 'type' => 'boolean', 'status' => is_writable($path) ? 'ok' : 'error'];
	}
}
