<?php
namespace Opencart\Install\Controller\Install;
/**
 * Class Step2
 *
 * @package Opencart\Install\Controller\Install
 */
class Step2 extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		if (($this->session->data['install_step'] ?? 0) < 1) {
			$this->response->redirect($this->url->link('install/step_1', 'language=' . $this->config->get('language_code')));

			return;
		}

		$this->load->language('install/step_2');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_step_2'] = $this->language->get('text_step_2');
		$data['text_php'] = $this->language->get('text_php');
		$data['text_extensions'] = $this->language->get('text_extensions');
		$data['text_filesystem'] = $this->language->get('text_filesystem');
		$data['text_requirement'] = $this->language->get('text_requirement');
		$data['text_current'] = $this->language->get('text_current');
		$data['text_required'] = $this->language->get('text_required');
		$data['text_status'] = $this->language->get('text_status');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['button_back'] = $this->language->get('button_back');

		$data['php_checks'] = $this->getPhpChecks();
		$data['extension_checks'] = $this->getExtensionChecks();
		$data['filesystem_checks'] = $this->getFilesystemChecks();

		$data['back'] = $this->url->link('install/step_1', 'language=' . $this->config->get('language_code'));
		$data['action'] = $this->url->link('install/step_2.save', 'language=' . $this->config->get('language_code'));
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('install/step_2', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('install/step_2');

		$json = [];

		if (($this->session->data['install_step'] ?? 0) < 1) {
			$json['redirect'] = $this->url->link('install/step_1', 'language=' . $this->config->get('language_code'), true);
		}

		$checks = array_merge($this->getPhpChecks(), $this->getExtensionChecks(), $this->getFilesystemChecks());

		foreach ($checks as $check) {
			if (!$check['status']) {
				$json['error'] = sprintf($this->language->get('error_requirement'), $check['name']);
				break;
			}
		}

		if (!$json) {
			$this->session->data['install_step'] = 2;
			$json['redirect'] = $this->url->link('install/step_3', 'language=' . $this->config->get('language_code'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getPhpChecks(): array {
		return [
			[
				'name' => $this->language->get('text_php_version'),
				'current' => PHP_VERSION,
				'required' => '8.1+',
				'status' => version_compare(PHP_VERSION, '8.1', '>=')
			],
			[
				'name' => 'file_uploads',
				'current' => ini_get('file_uploads') ? $this->language->get('text_on') : $this->language->get('text_off'),
				'required' => $this->language->get('text_on'),
				'status' => (bool)ini_get('file_uploads')
			],
			[
				'name' => 'session.auto_start',
				'current' => ini_get('session.auto_start') ? $this->language->get('text_on') : $this->language->get('text_off'),
				'required' => $this->language->get('text_off'),
				'status' => !ini_get('session.auto_start')
			]
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getExtensionChecks(): array {
		$extensions = [
			'MySQLi / PDO MySQL' => extension_loaded('mysqli') || (extension_loaded('pdo') && extension_loaded('pdo_mysql')),
			'GD' => extension_loaded('gd'),
			'cURL' => extension_loaded('curl'),
			'OpenSSL' => function_exists('openssl_encrypt'),
			'ZLIB' => extension_loaded('zlib'),
			'mbstring' => extension_loaded('mbstring')
		];

		$checks = [];

		foreach ($extensions as $name => $status) {
			$checks[] = [
				'name' => $name,
				'current' => $status ? $this->language->get('text_available') : $this->language->get('text_missing'),
				'required' => $this->language->get('text_required_value'),
				'status' => $status
			];
		}

		return $checks;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getFilesystemChecks(): array {
		$checks = [];
		$config = DIR_OPENCART . 'config.php';

		$checks[] = [
			'name' => $config,
			'current' => is_file($config) && is_writable($config) ? $this->language->get('text_writable') : $this->language->get('text_unwritable'),
			'required' => $this->language->get('text_writable'),
			'status' => is_file($config) && is_writable($config)
		];

		return $checks;
	}
}
