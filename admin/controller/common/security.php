<?php
namespace Opencart\Admin\Controller\Common;
/**
 * Class Security
 *
 * Can be loaded using $this->load->controller('common/security');
 *
 * @package Opencart\Admin\Controller\Common
 */
class Security extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/security');

		$data['list'] = $this->load->controller('common/security.getList');

		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('common/security', $data);
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('common/security');

		$this->response->setOutput($this->load->controller('common/security.getList'));
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		// Install directory exists
		$path = DIR_OPENCART . 'install/';

		if (is_dir($path)) {
			$data['install'] = $path;
		} else {
			$data['install'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		if ($data['install']) {
			return $this->load->view('common/security_list', $data);
		} else {
			return '';
		}
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->language('common/security');

		$json = [];

		if (!$this->user->hasPermission('modify', 'common/security')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			if (!is_dir(DIR_OPENCART . 'install/')) {
				$json['error'] = $this->language->get('error_install');
			}
		}

		if (!$json) {
			$files = [];

			$path = DIR_OPENCART . 'install/';

			// Make path into an array
			$directory = [$path];

			// While the path array is still populated keep looping through
			while (count($directory) != 0) {
				$next = array_shift($directory);

				if (is_dir($next)) {
					foreach (glob(rtrim($next, '/') . '/{*,.[!.]*,..?*}', GLOB_BRACE) as $file) {
						// If directory add to path array
						if (is_dir($file)) {
							$directory[] = $file;
						}

						// Add the file to the files to be deleted array
						$files[] = $file;
					}
				}
			}

			rsort($files);

			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);
				} elseif (is_dir($file)) {
					rmdir($file);
				}
			}

			rmdir($path);

			$json['success'] = $this->language->get('text_install_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

}
