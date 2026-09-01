<?php
namespace Opencart\Install\Controller\Install;
/**
 * Class Step3
 *
 * @package Opencart\Install\Controller\Install
 */
class Step3 extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		if (($this->session->data['install_step'] ?? 0) < 2) {
			$this->response->redirect($this->url->link('install/step_1', 'language=' . $this->config->get('language_code')));

			return;
		}

		$this->load->language('install/step_3');
		$this->document->setTitle($this->language->get('heading_title'));

		$data = $this->language->all();
		$data['drivers'] = [];

		foreach (['mysqli', 'pdo'] as $driver) {
			if (($driver === 'mysqli' && extension_loaded('mysqli')) || ($driver === 'pdo' && extension_loaded('pdo') && extension_loaded('pdo_mysql'))) {
				$data['drivers'][] = ['value' => $driver, 'text' => $this->language->get('text_' . $driver)];
			}
		}

		$data['back'] = $this->url->link('install/step_2', 'language=' . $this->config->get('language_code'));
		$data['action'] = $this->url->link('install/step_3.save', 'language=' . $this->config->get('language_code'));
		$data['language'] = $this->config->get('language_code');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('install/step_3', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('install/step_3');

		$json = [];
		$post = $this->request->post;

		if (($this->session->data['install_step'] ?? 0) < 2) {
			$json['redirect'] = $this->url->link('install/step_1', 'language=' . $this->config->get('language_code'), true);
		}

		foreach (['db_hostname', 'db_username', 'db_database', 'db_port', 'username', 'firstname', 'lastname'] as $field) {
			if (!isset($post[$field]) || trim((string)$post[$field]) === '') {
				$json['error'][$field] = $this->language->get('error_' . $field);
			}
		}

		if (!isset($post['db_driver']) || !in_array($post['db_driver'], ['mysqli', 'pdo'], true) || ($post['db_driver'] === 'mysqli' && !extension_loaded('mysqli')) || ($post['db_driver'] === 'pdo' && (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')))) {
			$json['error']['db_driver'] = $this->language->get('error_db_driver');
		}

		if (!empty($post['db_prefix']) && preg_match('/[^a-z0-9_]/', (string)$post['db_prefix'])) {
			$json['error']['db_prefix'] = $this->language->get('error_db_prefix');
		}

		if (empty($post['password'])) {
			$json['error']['password'] = $this->language->get('error_password');
		} elseif (!isset($post['confirm']) || $post['password'] !== $post['confirm']) {
			$json['error']['confirm'] = $this->language->get('error_confirm');
		}

		if (!isset($post['email']) || !oc_validate_email((string)$post['email'])) {
			$json['error']['email'] = $this->language->get('error_email');
		}

		$post += ['db_password' => '', 'db_prefix' => 'oc_', 'db_ssl_key' => '', 'db_ssl_cert' => '', 'db_ssl_ca' => ''];

		if (!$json) {
			try {
				new \Opencart\System\Library\DB(
					(string)$post['db_driver'],
					html_entity_decode((string)$post['db_hostname'], ENT_QUOTES, 'UTF-8'),
					html_entity_decode((string)$post['db_username'], ENT_QUOTES, 'UTF-8'),
					html_entity_decode((string)$post['db_password'], ENT_QUOTES, 'UTF-8'),
					html_entity_decode((string)$post['db_database'], ENT_QUOTES, 'UTF-8'),
					(string)$post['db_port'],
					(string)$post['db_ssl_key'],
					(string)$post['db_ssl_cert'],
					(string)$post['db_ssl_ca']
				);
			} catch (\Throwable $e) {
				$json['error']['warning'] = $e->getMessage();
			}
		}

		if (!$json) {
			try {
				$this->load->model('install/install');
				$this->model_install_install->database($post);

				if (!$this->writeConfig($post)) {
					throw new \RuntimeException($this->language->get('error_config'));
				}

				$this->cache->delete('*');

				$this->session->data['install_step'] = 3;
				$json['redirect'] = $this->url->link('install/step_4', 'language=' . $this->config->get('language_code'), true);
			} catch (\Throwable $e) {
				$json['error']['warning'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return bool
	 */
	private function writeConfig(array $data): bool {
		$root = str_replace('\\', '/', rtrim(DIR_OPENCART, '/\\')) . '/';
		$catalog = defined('HTTP_OPENCART') ? HTTP_OPENCART : preg_replace('~install/?$~', '', HTTP_SERVER);
		$defines = [
			'HTTP_CATALOG' => $catalog,
			'DIR_OPENCART' => $root,
			'DIR_IMAGE' => "DIR_OPENCART . 'image/'",
			'DIR_SYSTEM' => "DIR_OPENCART . 'system/'",
			'DIR_STORAGE' => "DIR_SYSTEM . 'storage/'",
			'DIR_CONFIG' => "DIR_SYSTEM . 'config/'",
			'DIR_CACHE' => "DIR_STORAGE . 'cache/'",
			'DIR_LOGS' => "DIR_STORAGE . 'logs/'",
			'DIR_SESSION' => "DIR_STORAGE . 'session/'",
			'DIR_UPLOAD' => "DIR_STORAGE . 'upload/'"
		];

		$output = "<?php\n";

		foreach ($defines as $name => $value) {
			$output .= str_starts_with($value, 'DIR_') ? "define('$name', $value);\n" : "define('$name', " . var_export($value, true) . ");\n";
		}

		$output .= "\n";

		foreach (['DB_DRIVER' => 'db_driver', 'DB_HOSTNAME' => 'db_hostname', 'DB_USERNAME' => 'db_username', 'DB_PASSWORD' => 'db_password', 'DB_DATABASE' => 'db_database', 'DB_PORT' => 'db_port', 'DB_PREFIX' => 'db_prefix', 'DB_SSL_KEY' => 'db_ssl_key', 'DB_SSL_CERT' => 'db_ssl_cert', 'DB_SSL_CA' => 'db_ssl_ca'] as $constant => $field) {
			$output .= "define('$constant', " . var_export(html_entity_decode((string)$data[$field], ENT_QUOTES, 'UTF-8'), true) . ");\n";
		}

		$target = DIR_OPENCART . 'config.php';

		if (file_exists($target) || !is_writable(DIR_OPENCART)) {
			return false;
		}

		$temp = tempnam(DIR_OPENCART, 'config.');

		if ($temp === false) {
			return false;
		}

		try {
			if (file_put_contents($temp, $output, LOCK_EX) !== strlen($output)) {
				return false;
			}

			return rename($temp, $target);
		} finally {
			if (is_file($temp)) {
				unlink($temp);
			}
		}
	}
}
