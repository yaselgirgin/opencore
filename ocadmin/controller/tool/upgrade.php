<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Class Upgrade
 *
 * @package Opencart\Admin\Controller\Tool
 */
class Upgrade extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('tool/upgrade');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/upgrade', 'user_token=' . $this->session->data['user_token'])
		];

		$data['current_version'] = VERSION;
		$data['check'] = $this->url->link('tool/upgrade.check', 'user_token=' . $this->session->data['user_token']);
		$data['prepare'] = $this->url->link('tool/upgrade.prepare', 'user_token=' . $this->session->data['user_token']);
		$data['apply'] = $this->url->link('tool/upgrade.apply', 'user_token=' . $this->session->data['user_token']);
		$data['database'] = $this->url->link('tool/upgrade.database', 'user_token=' . $this->session->data['user_token']);
		$data['recover'] = $this->url->link('tool/upgrade.recover', 'user_token=' . $this->session->data['user_token']);
		$data['can_modify'] = $this->user->hasPermission('modify', 'tool/upgrade');
		$gate_state = function_exists('oc_update_gate_state') ? oc_update_gate_state(DIR_STORAGE) : [];
		$data['pending_database_version'] = ($gate_state['status'] ?? '') === 'DATABASE_PENDING' ? (string)($gate_state['target_version'] ?? '') : '';
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/upgrade', $data));
	}

	public function check(): void {
		$this->load->language('tool/upgrade');

		$json = [];

		if (!$this->user->hasPermission('access', 'tool/upgrade')) {
			$json['success'] = false;
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('tool/upgrade');

			$result = $this->model_tool_upgrade->discover(VERSION);

			if ($result['success']) {
				$json = $result;
			} else {
				$json['success'] = false;
				$json['error'] = $this->language->get('error_check');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function prepare(): void {
		$this->load->language('tool/upgrade');

		$json = [];

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$json['success'] = false;
			$json['error'] = $this->language->get('error_method');
		} elseif (!$this->user->hasPermission('modify', 'tool/upgrade')) {
			$json['success'] = false;
			$json['error'] = $this->language->get('error_permission_modify');
		} elseif (!isset($this->request->post['version']) || !is_string($this->request->post['version'])) {
			$json['success'] = false;
			$json['error'] = $this->language->get('error_prepare');
		} else {
			$this->load->model('tool/upgrade');

			$result = $this->model_tool_upgrade->prepare($this->request->post['version'], VERSION);
			$json = $result;

			if (!$result['success']) {
				$json['error'] = $result['status'] === 'DOWNLOAD_FAILED' ? $this->language->get('error_download') : $this->language->get('error_validation');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function apply(): void {
		$this->mutate('apply');
	}

	public function recover(): void {
		$this->mutate('recover');
	}

	public function database(): void {
		$this->mutate('database');
	}

	private function mutate(string $operation): void {
		$this->load->language('tool/upgrade');
		$json = [];

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$json = ['success' => false, 'error' => $this->language->get('error_method')];
		} elseif (!$this->user->hasPermission('modify', 'tool/upgrade')) {
			$json = ['success' => false, 'error' => $this->language->get('error_permission_modify')];
		} elseif (!isset($this->request->post['version']) || !is_string($this->request->post['version'])) {
			$json = ['success' => false, 'error' => $this->language->get('error_apply')];
		} else {
			$this->load->model('tool/upgrade');
			if ($operation === 'apply') {
				$result = $this->model_tool_upgrade->apply($this->request->post['version'], VERSION);
			} elseif ($operation === 'database') {
				$result = $this->model_tool_upgrade->continueDatabase($this->request->post['version'], VERSION);
			} else {
				$result = $this->model_tool_upgrade->recover($this->request->post['version']);
			}
			$json = $result;
			if (!$result['success']) {
				$json['error'] = $this->language->get('error_' . strtolower($result['status']));
				if (!$json['error'] || $json['error'] === 'error_' . strtolower($result['status'])) {
					$json['error'] = $this->language->get('error_apply');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
