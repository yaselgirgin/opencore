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
}
