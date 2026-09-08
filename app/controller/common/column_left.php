<?php
namespace Opencart\App\Controller\Common;
/**
 * Class Column Left
 *
 * Can be loaded using $this->load->controller('common/column_left');
 *
 * @package Opencart\App\Controller\Common
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
		$this->load->language('common/header');

		$data['home'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']);
		$data['heading_title'] = $this->language->get('heading_title');

        $this->load->language('user/profile');

        $data['profile'] = $this->url->link('user/profile', 'user_token=' . $this->session->data['user_token']);
        $data['notification_all'] = $this->url->link('tool/notification', 'user_token=' . $this->session->data['user_token']);
        $data['logout'] = $this->url->link('common/logout', 'user_token=' . $this->session->data['user_token']);

        $data['text_profile'] = $this->language->get('text_profile');
        $data['text_theme_settings'] = $this->language->get('text_theme_settings');
        $data['text_notification'] = $this->language->get('text_notification');
        $data['text_logout'] = $this->language->get('text_logout');

        $this->load->model('user/user');

        $user_info = $this->model_user_user->getUser($this->user->getId());

        if ($user_info) {
            $data['firstname'] = $user_info['firstname'];
            $data['lastname'] = $user_info['lastname'];
            $data['user_group'] = $user_info['user_group'] ?? '';
        } else {
            $data['firstname'] = '';
            $data['lastname'] = '';
            $data['user_group'] = '';
        }

        $this->load->model('tool/image');

        if ($user_info && !empty($user_info['image']) && is_file(DIR_IMAGE . html_entity_decode($user_info['image'], ENT_QUOTES, 'UTF-8'))) {
            $data['image'] = $this->model_tool_image->resize($user_info['image'], 45, 45);
        } else {
            $data['image'] = $this->model_tool_image->resize('profile.png', 45, 45);
        }

        $this->load->model('tool/notification');

        $data['notification_total'] = $this->model_tool_notification->getTotalNotifications(
            $this->user->getId(),
            $this->user->getGroupId(),
            true
        );

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
				'href'     => $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token']),
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
