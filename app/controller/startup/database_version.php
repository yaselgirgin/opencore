<?php
namespace Opencart\App\Controller\Startup;
/**
 * Class DatabaseVersion
 *
 * @package Opencart\App\Controller\Startup
 */
class DatabaseVersion extends \Opencart\System\Engine\Controller {
	/**
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		if (($this->request->get['route'] ?? '') === 'tool/backup.restore') {
			return null;
		}

		$this->load->model('startup/database_version');
		$current = $this->model_startup_database_version->getDatabaseVersion();

		if ($current === DATABASE_VERSION) {
			return null;
		}

		if ($current !== null && $current < DATABASE_VERSION && is_dir(DIR_OPENCART . 'install/')) {
			return new \Opencart\System\Engine\Action('startup/database_version.upgrade');
		}

		$this->config->set('database_version_error', $current === null ? 'database_version_invalid' : ($current > DATABASE_VERSION ? 'database_version_incompatible' : 'database_upgrade_files_missing'));

		return new \Opencart\System\Engine\Action('startup/database_version.error');
	}

	/**
	 * @return void
	 */
	public function upgrade(): void {
			$this->response->redirect(HTTP_SERVER . 'install/');
	}

	/**
	 * @return void
	 */
	public function error(): void {
		$error = $this->config->get('database_version_error');
		$message = match ($error) {
			'database_version_invalid' => 'Database version could not be determined.',
			'database_version_incompatible' => 'The database revision is newer than this OpenCore version. Downgrade is not supported.',
			'database_upgrade_files_missing' => 'A database upgrade is required, but the install directory containing the upgrade files is missing.',
			default => 'Database startup is unavailable.'
		};
		$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 503 Service Unavailable');
		$this->response->setOutput($message);
	}
}
