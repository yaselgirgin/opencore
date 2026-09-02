<?php
namespace Opencart\App\Controller\Common;
/**
 * Class Developer
 *
 * Can be loaded using $this->load->controller('common/developer');
 *
 * @package Opencart\App\Controller\Common
 */
class Developer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('common/developer');

		$data['user_token'] = $this->session->data['user_token'];

		$this->response->setOutput($this->load->view('common/developer', $data));
	}

	/**
	 * Edit
	 *
	 * @return void
	 */
	public function edit(): void {
		$this->load->language('common/developer');

		$json = [];

		if (!$this->user->hasPermission('modify', 'common/developer')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('developer', $this->request->post);

			$json['success'] = $this->language->get('text_developer_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Cache
	 *
	 * @return void
	 */
	public function cache(): void {
		$this->load->language('common/developer');

		$json = [];

		if (!$this->user->hasPermission('modify', 'common/developer')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$files = glob(DIR_CACHE . 'cache.*');

			if ($files) {
				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					}
				}
			}

			$json['success'] = $this->language->get('text_cache_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Theme
	 *
	 * @return void
	 */
	public function theme(): void {
		$this->load->language('common/developer');

		$json = [];

		if (!$this->user->hasPermission('modify', 'common/developer')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$directories = glob(DIR_CACHE . 'template/*', GLOB_ONLYDIR);

			if ($directories) {
				foreach ($directories as $directory) {
					$files = glob($directory . '/*');

					foreach ($files as $file) {
						if (is_file($file)) {
							unlink($file);
						}
					}

					if (is_dir($directory)) {
						rmdir($directory);
					}
				}
			}

			$json['success'] = $this->language->get('text_theme_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

}
