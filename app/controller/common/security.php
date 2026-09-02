<?php
namespace Opencart\App\Controller\Common;
/**
 * Class Security
 *
 * Can be loaded using $this->load->controller('common/security');
 *
 * @package Opencart\App\Controller\Common
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

		// Storage directory exists
		$path = DIR_SYSTEM . 'storage/';

		if (DIR_STORAGE == $path) {
			$data['storage'] = $path;

			$data['document_root'] = str_replace('\\', '/', realpath($this->request->server['DOCUMENT_ROOT'] . '/../')) . '/';

			$path = '';

			$data['paths'] = [];

			$parts = explode('/', rtrim($data['document_root'], '/'));

			foreach ($parts as $part) {
				$path .= $part . '/';

				$data['paths'][] = $path;
			}

			rsort($data['paths']);
		} else {
			$data['storage'] = '';
		}

		// Storage delete
		$path = DIR_SYSTEM . 'storage/';

		if (is_dir($path) && DIR_STORAGE != $path) {
			$data['storage_delete'] = $path;
		} else {
			$data['storage_delete'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		if ($data['install'] || $data['storage'] || $data['storage_delete']) {
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
					foreach (oc_glob(rtrim($next, '/') . '/{*,.[!.]*,..?*}') as $file) {
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

	/**
	 * Storage
	 *
	 * @return void
	 */
	public function storage(): void {
		$this->load->language('common/security');

		$json = [];

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['name'])) {
			$name = preg_replace('/[^a-zA-Z0-9_\.-]/', '', basename(html_entity_decode(trim($this->request->get['name']), ENT_QUOTES, 'UTF-8')));
		} else {
			$name = '';
		}

		if (isset($this->request->get['path'])) {
			$path = preg_replace('/[^a-zA-Z0-9_\:\/\.-]/', '', html_entity_decode(trim($this->request->get['path']), ENT_QUOTES, 'UTF-8'));
		} else {
			$path = '';
		}

		if (!$this->user->hasPermission('modify', 'common/security')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$base_old = DIR_STORAGE;
			$base_new = $path . $name . '/';

			if (!is_dir($base_old)) {
				$json['error'] = $this->language->get('error_storage');
			}

			if (!$json) {
				$root = str_replace('\\', '/', realpath($this->request->server['DOCUMENT_ROOT']));

				if (!str_starts_with($root, rtrim($path, '/') . '/')) {
					$json['error'] = $this->language->get('error_storage_root');
				}
			}

			if (!$json && !str_starts_with($name, 'storage')) {
				$json['error'] = $this->language->get('error_storage_name');
			}

			if (!$json && !is_writable(DIR_OPENCART . 'config.php')) {
				$json['error'] = $this->language->get('error_writable');
			}

			if (!$json) {
				if (!is_dir($base_new)) {
					if (!is_writable($path)) {
						$json['error'] = str_replace('%s', $path, $this->language->get('error_writable_path'));
					}
				} elseif (!is_writable($base_new)) {
					$json['error'] = str_replace('%s', $path, $this->language->get('error_writable_path'));
				}
			}
		}

		if (!$json) {
			$files = [];
			$directory = [$base_old];

			while (count($directory) != 0) {
				$next = array_shift($directory);

				foreach (oc_glob(rtrim($next, '/') . '/{*,.[!.]*,..?*}') as $file) {
					if (is_dir($file)) {
						$directory[] = $file;
					}

					$files[] = $file;
				}
			}

			if (!is_dir($base_new)) {
				if (!mkdir($base_new, 0777) && !is_dir($base_new)) {
					$json['error'] = str_replace('%s', $base_new, $this->language->get('error_writable_path'));
				}
			}

			$total = count($files);
			$limit = 200;
			$start = ($page - 1) * $limit;
			$end = ($start > ($total - $limit)) ? $total : ($start + $limit);

			for ($i = $start; !$json && $i < $end; $i++) {
				$destination = substr($files[$i], strlen($base_old));
				$path_new = '';
				$directories = explode('/', dirname($destination));

				foreach ($directories as $directory) {
					if (!$path_new) {
						$path_new = $directory;
					} else {
						$path_new = $path_new . '/' . $directory;
					}

					if (!is_dir($base_new . $path_new)) {
						if (!mkdir($base_new . $path_new, 0777) && !is_dir($base_new . $path_new)) {
							$json['error'] = str_replace('%s', $base_new . $path_new, $this->language->get('error_writable_path'));

							break;
						}
					}
				}

				if ($json) {
					break;
				}

				if (is_file($base_old . $destination)) {
					$source = $base_old . $destination;
					$target = $base_new . $destination;

					if (!is_file($target) && !copy($source, $target)) {
						$json['error'] = str_replace('%s', $target, $this->language->get('error_writable_path'));
					} elseif (!is_file($target)) {
						$json['error'] = str_replace('%s', $target, $this->language->get('error_writable_path'));
					} else {
						$source_hash = hash_file('sha256', $source);
						$target_hash = hash_file('sha256', $target);

						if ($source_hash === false || $target_hash === false || $source_hash !== $target_hash) {
							$json['error'] = str_replace('%s', $target, $this->language->get('error_writable_path'));
						}
					}
				}
			}

			if (!$json && $end < $total) {
				$json['text'] = sprintf($this->language->get('text_storage_move'), $start, $end, $total);
				$json['next'] = $this->url->link('common/security.storage', '&user_token=' . $this->session->data['user_token'] . '&name=' . $name . '&path=' . $path . '&page=' . ($page + 1), true);
			} elseif (!$json) {
				$file = DIR_OPENCART . 'config.php';
				$lines = file($file);
				$output = '';

				if ($lines === false) {
					$json['error'] = $this->language->get('error_writable');
				} else {
					foreach ($lines as $line) {
						if (str_contains($line, 'define(\'DIR_STORAGE')) {
							$output .= 'define(\'DIR_STORAGE\', \'' . $base_new . '\');' . "\n";
						} else {
							$output .= $line;
						}
					}

					if (file_put_contents($file, $output) === false || !str_contains((string)file_get_contents($file), "define('DIR_STORAGE', '" . $base_new . "');")) {
						$json['error'] = $this->language->get('error_writable');
					}
				}

				if (!$json) {
					rsort($files);

					foreach ($files as $file) {
						if (is_file($file)) {
							unlink($file);
						} elseif (is_dir($file)) {
							rmdir($file);
						}
					}

					rmdir($base_old);
					$json['success'] = $this->language->get('text_storage_success');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('common/security');

		$json = [];

		if (isset($this->request->get['remove'])) {
			$remove = (string)$this->request->get['remove'];
		} else {
			$remove = '';
		}

		if (!$this->user->hasPermission('modify', 'common/security')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$path = '';

			if ($remove == 'storage') {
				$path = DIR_SYSTEM . 'storage/';

				if (!is_dir($path) || DIR_STORAGE == $path) {
					$json['error'] = $this->language->get('error_storage');
				}
			}

			if (!$path) {
				$json['error'] = $this->language->get('error_remove');
			}
		}

		if (!$json) {
			$directory = [$path];
			$files = [];

			while (count($directory) != 0) {
				$next = array_shift($directory);

				if (is_dir($next)) {
					foreach (oc_glob(rtrim($next, '/') . '/{*,.[!.]*,..?*}') as $file) {
						if (is_dir($file)) {
							$directory[] = $file;
						}

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
			$json['success'] = $this->language->get('text_' . $remove . '_delete_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

}
