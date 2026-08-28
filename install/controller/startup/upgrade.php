<?php
namespace Opencart\Install\Controller\Startup;
/**
 * Class Upgrade
 *
 * @package Opencart\Install\Controller\Startup
 */
class Upgrade extends \Opencart\System\Engine\Controller {
	/**
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$route = $this->request->get['route'] ?? $this->config->get('action_default');
		$completion = $route === 'install/step_4' && ($this->session->data['install_step'] ?? 0) === 3;

		if (!INSTALL_CONFIGURED || $completion) {
			return null;
		}

		$this->load->model('upgrade/upgrade');
		$current = $this->model_upgrade_upgrade->getDatabaseVersion();

		if ($current !== null && $current < DATABASE_VERSION) {
			if (str_starts_with($route, 'upgrade/')) {
				return null;
			}

			return new \Opencart\System\Engine\Action('upgrade/upgrade');
		}

		if ($current === DATABASE_VERSION) {
			return new \Opencart\System\Engine\Action('install/step_4.blocked');
		}

		$this->config->set('database_upgrade_error', $current === null ? 'Database version could not be determined.' : 'Database revision is newer than this application version.');

		return new \Opencart\System\Engine\Action('startup/upgrade.error');
	}

	/**
	 * @return void
	 */
	public function error(): void {
		$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 503 Service Unavailable');
		$this->response->setOutput((string)$this->config->get('database_upgrade_error'));
	}
}
