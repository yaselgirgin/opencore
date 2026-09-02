<?php
namespace Opencart\Api\Model\Setting;
/**
 * Class Setting
 *
 * Can be called using $this->load->model('setting/setting');
 *
 * @package Opencart\Api\Model\Setting
 */
class Setting extends \Opencart\System\Engine\Model {
	/**
	 * Get Settings
	 *
	 * Get the record of the setting records in the database.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $this->load->model('setting/setting');
	 *
	 * $settings = $this->model_setting_setting->getSettings();
	 */
	public function getSettings(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` ORDER BY `setting_id` ASC");

		return $query->rows;
	}
}
