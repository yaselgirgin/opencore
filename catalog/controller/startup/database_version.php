<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class DatabaseVersion
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class DatabaseVersion extends \Opencart\System\Engine\Controller {
	/**
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->model('startup/database_version');
		$current = $this->model_startup_database_version->getDatabaseVersion();

		if ($current === DATABASE_VERSION) {
			return null;
		}

		if ($current === null) {
			$error = 'database_version_invalid';
		} elseif ($current > DATABASE_VERSION) {
			$error = 'database_version_incompatible';
		} elseif (is_dir(DIR_OPENCART . 'install/')) {
			$error = 'database_upgrade_required';
		} else {
			$error = 'database_upgrade_files_missing';
		}

		$this->config->set('database_version_error', $error);

		return new \Opencart\System\Engine\Action('startup/database_version.error');
	}

	/**
	 * @return void
	 */
	public function error(): void {
		$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 503 Service Unavailable');
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode(['error' => $this->config->get('database_version_error')]));
	}
}
