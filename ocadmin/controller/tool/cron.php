<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Class Cron
 *
 * @package Opencart\Admin\Controller\Tool
 */
class Cron extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('tool/cron');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->request->get['sort'])) {
			$sort = (string)$this->request->get['sort'];
		} else {
			$sort = 'code';
		}

		if (isset($this->request->get['order'])) {
			$order = (string)$this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['refresh'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['can_modify'] = $this->user->hasPermission('modify', 'tool/cron');
		$data['scheduler_command'] = 'php "' . DIR_OPENCART . 'cron.php"';
		$data['crons'] = [];
		$process_available = $this->isRunAvailable();

		$filter_data = [
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_pagination_admin'),
			'limit' => $this->config->get('config_pagination_admin')
		];

		$this->load->model('setting/cron');

		$results = $this->model_setting_cron->getCrons($filter_data);

		foreach ($results as $result) {
			$source_resolved = false;

			if (preg_match('/^cron\/[a-z0-9_]+(?:\/[a-z0-9_]+)*$/', $result['action'])) {
				$source_resolved = is_file(DIR_CATALOG . 'controller/' . $result['action'] . '.php');
			}

			$data['crons'][] = [
				'date_added'      => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'date_modified'   => date($this->language->get('datetime_format'), strtotime($result['date_modified'])),
				'source_resolved' => $source_resolved,
				'can_run'         => $process_available && (bool)$result['status'] && $source_resolved,
				'run'             => $this->url->link('tool/cron.run', 'user_token=' . $this->session->data['user_token']),
				'enable'          => $this->url->link('tool/cron.enable', 'user_token=' . $this->session->data['user_token']),
				'disable'         => $this->url->link('tool/cron.disable', 'user_token=' . $this->session->data['user_token'])
			] + $result;
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_code'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . '&sort=code' . $url);
		$data['sort_cycle'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . '&sort=cycle' . $url);
		$data['sort_action'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . '&sort=action' . $url);
		$data['sort_status'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url);
		$data['sort_date_added'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . '&sort=date_added' . $url);
		$data['sort_date_modified'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . '&sort=date_modified' . $url);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$cron_total = $this->model_setting_cron->getTotalCrons();

		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $cron_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($cron_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($cron_total - $this->config->get('config_pagination_admin'))) ? $cron_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $cron_total, ceil($cron_total / $this->config->get('config_pagination_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/cron', $data));
	}

	/**
	 * Enable
	 *
	 * @return void
	 */
	public function enable(): void {
		$this->setStatus(true);
	}

	/**
	 * Disable
	 *
	 * @return void
	 */
	public function disable(): void {
		$this->setStatus(false);
	}

	/**
	 * Run
	 *
	 * @return void
	 */
	public function run(): void {
		$this->load->language('tool/cron');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/cron')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->post['cron_id']) || filter_var($this->request->post['cron_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
			$json['error'] = $this->language->get('error_cron');
		} elseif (!$this->isRunAvailable()) {
			$json['error'] = $this->language->get('error_run');
		}

		if (!$json) {
			$cron_id = (int)$this->request->post['cron_id'];

			$this->load->model('setting/cron');

			$cron_info = $this->model_setting_cron->getCron($cron_id);

			if (!$cron_info || !$cron_info['status'] || !$this->isActionResolved($cron_info['action'])) {
				$json['error'] = $this->language->get('error_run');
			} elseif ($this->runProcess($cron_id)) {
				$json['success'] = $this->language->get('text_run_success');
			} else {
				$json['error'] = $this->language->get('error_run');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Set Status
	 *
	 * @param bool $status
	 *
	 * @return void
	 */
	private function setStatus(bool $status): void {
		$this->load->language('tool/cron');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/cron')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->post['cron_id']) || filter_var($this->request->post['cron_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
			$json['error'] = $this->language->get('error_cron');
		}

		if (!$json) {
			$this->load->model('setting/cron');

			$cron_id = (int)$this->request->post['cron_id'];
			$cron_info = $this->model_setting_cron->getCron($cron_id);

			$this->model_setting_cron->editStatus($cron_id, $status);

			if ($cron_info && $cron_info['code'] == 'currency' && $cron_info['action'] == 'cron/currency') {
				$this->load->model('setting/setting');
				$this->model_setting_setting->editValue('config', 'config_currency_auto', (string)(int)$status);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Is Run Available
	 *
	 * @return bool
	 */
	private function isRunAvailable(): bool {
		if (!defined('PHP_CLI') || !is_string(PHP_CLI) || !preg_match('/^(?:[a-zA-Z]:[\\\\\/]|\/)/', PHP_CLI)) {
			return false;
		}

		return is_file(PHP_CLI) && is_executable(PHP_CLI) && function_exists('proc_open') && function_exists('proc_close');
	}

	/**
	 * Is Action Resolved
	 *
	 * @param string $action
	 *
	 * @return bool
	 */
	private function isActionResolved(string $action): bool {
		return (bool)preg_match('/^cron\/[a-z0-9_]+(?:\/[a-z0-9_]+)*$/', $action) && is_file(DIR_CATALOG . 'controller/' . $action . '.php');
	}

	/**
	 * Run Process
	 *
	 * @param int $cron_id
	 *
	 * @return bool
	 */
	private function runProcess(int $cron_id): bool {
		$command = [PHP_CLI, DIR_OPENCART . 'cron.php', '--cron-id=' . $cron_id];
		$descriptor = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];

		try {
			$process = proc_open($command, $descriptor, $pipes, DIR_OPENCART, null, ['bypass_shell' => true]);
		} catch (\Throwable $e) {
			$this->log->write('Cron Run Now process could not be started: ' . $e->getMessage());

			return false;
		}

		if (!is_resource($process)) {
			$this->log->write('Cron Run Now process could not be started.');

			return false;
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stdout = '';
		$stderr = '';
		$exit_code = -1;
		$timed_out = false;
		$started = microtime(true);

		do {
			$this->drainPipe($pipes[1], $stdout);
			$this->drainPipe($pipes[2], $stderr);

			$status = proc_get_status($process);

			if (!is_array($status)) {
				$this->log->write('Cron Run Now process status could not be read.');
				$timed_out = true;
				proc_terminate($process);
				break;
			}

			if (!$status['running']) {
				$exit_code = $status['exitcode'];
				break;
			}

			if ((microtime(true) - $started) >= 120) {
				$timed_out = true;
				proc_terminate($process);
				break;
			}

			usleep(100000);
		} while (true);

		if ($timed_out) {
			$terminate_deadline = microtime(true) + 2;

			do {
				$this->drainPipe($pipes[1], $stdout);
				$this->drainPipe($pipes[2], $stderr);

				$status = proc_get_status($process);

				if (!is_array($status) || !$status['running']) {
					break;
				}

				usleep(100000);
			} while (microtime(true) < $terminate_deadline);

			if (is_array($status) && $status['running']) {
				proc_terminate($process, 9);
			}
		}

		$this->drainPipe($pipes[1], $stdout);
		$this->drainPipe($pipes[2], $stderr);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$close_code = proc_close($process);

		if ($timed_out) {
			$this->log->write('Cron Run Now process timed out after 120 seconds.');

			return false;
		}

		if ($exit_code < 0) {
			$exit_code = $close_code;
		}

		if ($exit_code !== 0) {
			$diagnostic = trim($stderr . ($stdout !== '' ? "\n" . $stdout : ''));
			$this->log->write('Cron Run Now process failed with exit code ' . $exit_code . ($diagnostic !== '' ? ': ' . $diagnostic : '.'));

			return false;
		}

		return true;
	}

	/**
	 * Drain Pipe
	 *
	 * @param resource $pipe
	 * @param string   $output
	 *
	 * @return void
	 */
	private function drainPipe($pipe, string &$output): void {
		while (($chunk = fread($pipe, 8192)) !== false && $chunk !== '') {
			$remaining = 32768 - strlen($output);

			if ($remaining > 0) {
				$output .= substr($chunk, 0, $remaining);
			}
		}
	}
}
