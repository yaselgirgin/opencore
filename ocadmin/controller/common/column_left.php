<?php
namespace Opencart\Admin\Controller\Common;
/**
 * Class Column Left
 *
 * Can be loaded using $this->load->controller('common/column_left');
 *
 * @package Opencart\Admin\Controller\Common
 */
class ColumnLeft extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		if (!isset($this->request->get['user_token']) || !isset($this->session->data['user_token']) || ((string)$this->request->get['user_token'] != $this->session->data['user_token'])) {
			return '';
		}

		$this->load->language('common/column_left');

		$data['menus'] = [];
		$data['menus'][] = [
			'id'       => 'menu-dashboard',
			'icon'     => 'fas fa-home',
			'name'     => $this->language->get('text_dashboard'),
			'href'     => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
			'children' => []
		];

		$system = [];

		if ($this->user->hasPermission('access', 'setting/setting')) {
			$system[] = [
				'name'     => $this->language->get('text_setting'),
				'href'     => $this->url->link('setting/store', 'user_token=' . $this->session->data['user_token']),
				'children' => []
			];
		}

		$users = [];

		if ($this->user->hasPermission('access', 'user/user')) {
			$users[] = [
				'name'     => $this->language->get('text_users'),
				'href'     => $this->url->link('user/user', 'user_token=' . $this->session->data['user_token']),
				'children' => []
			];
		}

		if ($this->user->hasPermission('access', 'user/user_permission')) {
			$users[] = [
				'name'     => $this->language->get('text_user_group'),
				'href'     => $this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token']),
				'children' => []
			];
		}

		if ($users) {
			$system[] = [
				'name'     => $this->language->get('text_users'),
				'href'     => '',
				'children' => $users
			];
		}

		$localisation = [];
		$localisation_routes = [
			'localisation/language'       => 'text_language',
			'localisation/country'        => 'text_country',
			'localisation/zone'           => 'text_zone',
			'localisation/location'       => 'text_location',
			'localisation/currency'       => 'text_currency',
			'localisation/address_format' => 'text_address_format',
			'localisation/length_class'   => 'text_length_class',
			'localisation/weight_class'   => 'text_weight_class',
		];

		foreach ($localisation_routes as $route => $language_key) {
			if ($this->user->hasPermission('access', $route)) {
				$localisation[] = [
					'name'     => $this->language->get($language_key),
					'href'     => $this->url->link($route, 'user_token=' . $this->session->data['user_token']),
					'children' => []
				];
			}
		}

		if ($localisation) {
			$system[] = [
				'name'     => $this->language->get('text_localisation'),
				'href'     => '',
				'children' => $localisation
			];
		}

		$maintenance = [];
		$maintenance_routes = [
			'tool/cron'                => 'text_cron',
			'tool/runtime_diagnostics' => 'text_runtime_diagnostics',
			'tool/upgrade'             => 'text_upgrade',
			'tool/backup'              => 'text_backup',
			'tool/upload'              => 'text_upload',
			'tool/log'                 => 'text_log'
		];

		foreach ($maintenance_routes as $route => $language_key) {
			if ($this->user->hasPermission('access', $route)) {
				$maintenance[] = [
					'name'     => $this->language->get($language_key),
					'href'     => $this->url->link($route, 'user_token=' . $this->session->data['user_token']),
					'children' => []
				];
			}
		}

		if ($maintenance) {
			$system[] = [
				'name'     => $this->language->get('text_maintenance'),
				'href'     => '',
				'children' => $maintenance
			];
		}

		if ($system) {
			$data['menus'][] = [
				'id'       => 'menu-system',
				'icon'     => 'fas fa-cog',
				'name'     => $this->language->get('text_system'),
				'href'     => '',
				'children' => $system
			];
		}

		return $this->load->view('common/column_left', $data);
	}
}
