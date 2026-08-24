<?php
namespace Opencart\Admin\Controller\Setting;
/**
 * Class Setting
 *
 * @package Opencart\Admin\Controller\Setting
 */
class Setting extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('setting/setting');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_stores'),
			'href' => $this->url->link('setting/store', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('setting/setting.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('setting/store', 'user_token=' . $this->session->data['user_token']);

		// General
		$data['config_description'] = (array)$this->config->get('config_description');

		// Store Details
		$data['config_name'] = $this->config->get('config_name');

		$data['store_url'] = HTTP_CATALOG;

		$data['config_owner'] = $this->config->get('config_owner');
		$data['config_address'] = $this->config->get('config_address');
		$data['config_geocode'] = $this->config->get('config_geocode');
		$data['config_email'] = $this->config->get('config_email');
		$data['config_telephone'] = $this->config->get('config_telephone');

		// Image
		$data['config_image'] = $this->config->get('config_image');

		$this->load->model('tool/image');

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));

		if ($data['config_image'] && is_file(DIR_IMAGE . html_entity_decode($data['config_image'], ENT_QUOTES, 'UTF-8'))) {
			$data['thumb'] = $this->model_tool_image->resize($data['config_image'], $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));
		} else {
			$data['thumb'] = $data['placeholder'];
		}

		$data['config_open'] = $this->config->get('config_open');
		$data['config_comment'] = $this->config->get('config_comment');

		// Location
		$this->load->model('localisation/location');

		$data['locations'] = $this->model_localisation_location->getLocations();

		$data['config_location'] = (array)$this->config->get('config_location');

		// Country
		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();

		$data['config_country_id'] = $this->config->get('config_country_id');
		$data['config_zone_id'] = $this->config->get('config_zone_id');
		$data['config_timezone'] = $this->config->get('config_timezone');

		$data['timezones'] = [];

		$timestamp = date_create('now');

		$timezones = timezone_identifiers_list();

		foreach ($timezones as $timezone) {
			date_timezone_set($timestamp, timezone_open($timezone));

			$hour = ' (' . date_format($timestamp, 'P') . ')';

			$data['timezones'][] = [
				'text'  => $timezone . $hour,
				'value' => $timezone
			];
		}

		// Language
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['config_language_catalog'] = $this->config->get('config_language_catalog');
		$data['config_language_admin'] = $this->config->get('config_language_admin');

		// Currency
		$this->load->model('localisation/currency');

		$data['currencies'] = $this->model_localisation_currency->getCurrencies();

		$data['config_currency'] = $this->config->get('config_currency');

		// Length Class
		$this->load->model('localisation/length_class');

		$data['length_classes'] = $this->model_localisation_length_class->getLengthClasses();

		$data['config_length_class_id'] = $this->config->get('config_length_class_id');

		// Weight Class
		$this->load->model('localisation/weight_class');

		$data['weight_classes'] = $this->model_localisation_weight_class->getWeightClasses();

		$data['config_weight_class_id'] = $this->config->get('config_weight_class_id');

		// Options
		$data['config_pagination'] = $this->config->get('config_pagination');
		$data['config_pagination_admin'] = $this->config->get('config_pagination_admin');
		$data['config_autocomplete_limit'] = $this->config->get('config_autocomplete_limit');
		$data['config_2fa'] = $this->config->get('config_2fa');
		$data['config_password_uppercase'] = $this->config->get('config_password_uppercase');
		$data['config_password_lowercase'] = $this->config->get('config_password_lowercase');
		$data['config_password_number'] = $this->config->get('config_password_number');
		$data['config_password_symbol'] = $this->config->get('config_password_symbol');

		// Image
		$data['config_logo'] = $this->config->get('config_logo');

		$this->load->model('tool/image');

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));

		if ($data['config_logo'] && is_file(DIR_IMAGE . html_entity_decode($data['config_logo'], ENT_QUOTES, 'UTF-8'))) {
			$data['logo'] = $this->model_tool_image->resize($data['config_logo'], $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));
		} else {
			$data['logo'] = $data['placeholder'];
		}
		// Fav Icon
		$data['config_icon'] = $this->config->get('config_icon');
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));

		if ($data['config_icon'] && is_file(DIR_IMAGE . html_entity_decode($data['config_icon'], ENT_QUOTES, 'UTF-8'))) {
			$data['icon'] = $this->model_tool_image->resize($data['config_icon'], $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));
		} else {
			$data['icon'] = '';
		}

		// Image
		$data['config_image_default_width'] = $this->config->get('config_image_default_width');
		$data['config_image_default_height'] = $this->config->get('config_image_default_height');
		$data['config_image_location_width'] = $this->config->get('config_image_location_width');
		$data['config_image_location_height'] = $this->config->get('config_image_location_height');

		// Mail
		$data['config_mail_engine'] = $this->config->get('config_mail_engine');
		$data['config_mail_parameter'] = $this->config->get('config_mail_parameter');
		$data['config_mail_smtp_hostname'] = $this->config->get('config_mail_smtp_hostname');
		$data['config_mail_smtp_username'] = $this->config->get('config_mail_smtp_username');
		$data['config_mail_smtp_password'] = $this->config->get('config_mail_smtp_password');
		$data['config_mail_smtp_port'] = $this->config->get('config_mail_smtp_port');
		$data['config_mail_smtp_timeout'] = $this->config->get('config_mail_smtp_timeout');
		// Server
		$data['config_maintenance'] = $this->config->get('config_maintenance');
		$data['config_session_expire'] = $this->config->get('config_session_expire');
		$data['config_session_samesite'] = $this->config->get('config_session_samesite');
		$data['config_compression'] = $this->config->get('config_compression');

		// Security
		$data['config_user_2fa'] = $this->config->get('config_user_2fa');
		$data['config_2fa_expire'] = $this->config->get('config_2fa_expire');
		$data['config_user_password_uppercase'] = $this->config->get('config_user_password_uppercase');
		$data['config_user_password_lowercase'] = $this->config->get('config_user_password_lowercase');
		$data['config_user_password_number'] = $this->config->get('config_user_password_number');
		$data['config_user_password_symbol'] = $this->config->get('config_user_password_symbol');
		$data['config_user_password_length'] = $this->config->get('config_user_password_length');

		$data['config_shared'] = $this->config->get('config_shared');

		// Uploads
		$data['config_file_max_size'] = $this->config->get('config_file_max_size');
		$data['config_file_ext_allowed'] = $this->config->get('config_file_ext_allowed');
		$data['config_file_mime_allowed'] = $this->config->get('config_file_mime_allowed');

		// Errors
		$data['config_error_display'] = $this->config->get('config_error_display');
		$data['config_error_log'] = $this->config->get('config_error_log');
		$data['config_error_filename'] = $this->config->get('config_error_filename');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/setting', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('setting/setting');

		$json = [];

		if (!$this->user->hasPermission('modify', 'setting/setting')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['config_name']) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		foreach ($this->request->post['config_description'] as $language_id => $value) {
			if (!oc_validate_length($value['meta_title'], 1, 64)) {
				$json['error']['meta_title_' . $language_id] = $this->language->get('error_meta_title');
			}
		}

		if (!oc_validate_length($this->request->post['config_owner'], 3, 64)) {
			$json['error']['owner'] = $this->language->get('error_owner');
		}

		if (!oc_validate_length($this->request->post['config_address'], 3, 256)) {
			$json['error']['address'] = $this->language->get('error_address');
		}

		if ((oc_strlen($this->request->post['config_email']) > 96) || !filter_var($this->request->post['config_email'], FILTER_VALIDATE_EMAIL)) {
			$json['error']['email'] = $this->language->get('error_email');
		}

		if (!$this->request->post['config_pagination']) {
			$json['error']['pagination'] = $this->language->get('error_pagination');
		}

		if (!$this->request->post['config_pagination_admin']) {
			$json['error']['pagination_admin'] = $this->language->get('error_pagination');
		}

		if (!$this->request->post['config_autocomplete_limit']) {
			$json['error']['autocomplete_limit'] = $this->language->get('error_autocomplete_limit');
		}

		if (!$this->request->post['config_image_default_width'] || !$this->request->post['config_image_default_height']) {
			$json['error']['image_default'] = $this->language->get('error_image_category');
		}

		if (!$this->request->post['config_image_location_width'] || !$this->request->post['config_image_location_height']) {
			$json['error']['image_location'] = $this->language->get('error_image_location');
		}

		if ($this->request->post['config_user_2fa'] && !$this->request->post['config_mail_engine']) {
			$json['error']['warning'] = $this->language->get('error_user_2fa');
		}

		if (!$this->request->post['config_file_max_size']) {
			$json['error']['file_max_size'] = $this->language->get('error_file_max_size');
		}

		$disallowed = [
			'php',
			'php4',
			'php3'
		];

		$extensions = explode("\n", $this->request->post['config_file_ext_allowed']);

		foreach ($extensions as $extension) {
			if (in_array(trim($extension), $disallowed)) {
				$json['error']['file_ext_allowed'] = $this->language->get('error_extension');

				break;
			}
		}

		$disallowed = [
			'php',
			'php4',
			'php3'
		];

		$mimes = explode("\n", $this->request->post['config_file_mime_allowed']);

		foreach ($mimes as $mime) {
			if (in_array(trim($mime), $disallowed)) {
				$json['error']['file_mime_allowed'] = $this->language->get('error_mime');

				break;
			}
		}

		if (!$this->request->post['config_error_filename']) {
			$json['error']['error_filename'] = $this->language->get('error_log_required');
		} elseif (preg_match('/\.\.[\/\\\]?/', $this->request->post['config_error_filename'])) {
			$json['error']['error_filename'] = $this->language->get('error_log_invalid');
		} elseif (substr($this->request->post['config_error_filename'], strrpos($this->request->post['config_error_filename'], '.')) != '.log') {
			$json['error']['error_filename'] = $this->language->get('error_log_extension');
		}

		if (isset($json['error']) && !isset($json['error']['warning'])) {
			$json['error']['warning'] = $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('config', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
