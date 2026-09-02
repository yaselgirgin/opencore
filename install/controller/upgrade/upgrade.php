<?php
namespace Opencart\Install\Controller\Upgrade;
/**
 * Class Upgrade
 *
 * @package Opencart\Install\Controller\Upgrade
 */
class Upgrade extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('upgrade/upgrade');
		$this->load->model('upgrade/upgrade');

		$state = $this->getState();

		$this->document->setTitle($this->language->get('heading_title'));
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_prepare'] = $this->language->get('text_prepare');
		$data['text_check'] = $this->language->get('text_check');
		$data['text_current'] = $this->language->get('text_current');
		$data['text_target'] = $this->language->get('text_target');
		$data['text_pending'] = $this->language->get('text_pending');
		$data['text_backup_warning'] = $this->language->get('text_backup_warning');
		$data['entry_backup'] = $this->language->get('entry_backup');
		$data['button_upgrade'] = $this->language->get('button_upgrade');
		$data['current'] = $state['current'] ?? $this->language->get('text_invalid');
		$data['target'] = DATABASE_VERSION;
		$data['pending'] = $state['pending'];
		$data['can_upgrade'] = $state['can_upgrade'];
		$data['error_warning'] = $state['error'] ? $this->language->get($state['error']) : '';
		$data['action'] = $this->url->link('upgrade/upgrade.run', 'language=' . $this->config->get('language_code'));
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('upgrade/upgrade', $data));
	}

	/**
	 * @return void
	 */
	public function run(): void {
		$this->load->language('upgrade/upgrade');
		$this->load->model('upgrade/upgrade');

		$json = [];
		if (($this->request->post['backup'] ?? '') !== '1') {
			$json['error']['backup'] = $this->language->get('error_backup');
		}

		$state = $this->getState();

		if ($state['error']) {
			$json['error']['warning'] = $this->language->get($state['error']);
		}

		if (!$state['can_upgrade']) {
			$json['error']['warning'] = $json['error']['warning'] ?? $this->language->get('error_not_required');
		}

		if (!$json) {
			try {
				for ($revision = $state['current'] + 1; $revision <= DATABASE_VERSION; $revision++) {
					$method = 'upgrade' . $revision;

					if (!isset($this->model_upgrade_upgrade->{$method})) {
						throw new \RuntimeException('Missing upgrade method: ' . $method);
					}
				}

				for ($revision = $state['current'] + 1; $revision <= DATABASE_VERSION; $revision++) {
					$method = 'upgrade' . $revision;

					$this->model_upgrade_upgrade->{$method}();
					$this->model_upgrade_upgrade->setDatabaseVersion($revision);
				}
			} catch (\Throwable $e) {
				$json['error']['warning'] = $this->language->get('error_upgrade');
			}
		}

		if (!$json) {
			$json['redirect'] = HTTP_OPENCART;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return array{current: int|null, pending: array<int>, can_upgrade: bool, error: string}
	 */
	private function getState(): array {
		$current = $this->model_upgrade_upgrade->getDatabaseVersion();
		$error = '';

		if ($current === null) {
			$error = 'error_database_version_invalid';
		} elseif ($current > DATABASE_VERSION) {
			$error = 'error_database_version_incompatible';
		}

		return [
			'current' => $current,
			'pending' => !$error && $current < DATABASE_VERSION ? range($current + 1, DATABASE_VERSION) : [],
			'can_upgrade' => !$error && $current < DATABASE_VERSION,
			'error' => $error
		];
	}
}
