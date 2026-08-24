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

		$data['can_modify'] = $this->user->hasPermission('modify', 'tool/cron');
		$data['scheduler_command'] = 'php "' . DIR_OPENCART . 'cron.php"';
		$data['crons'] = [];

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

			$this->model_setting_cron->editStatus((int)$this->request->post['cron_id'], $status);

			$json['success'] = $this->language->get('text_success');
			$json['redirect'] = $this->url->link('tool/cron', 'user_token=' . $this->session->data['user_token'], true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
