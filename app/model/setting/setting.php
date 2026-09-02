<?php
namespace Opencart\App\Model\Setting;
/**
 * Class Setting
 *
 * Can be loaded using $this->load->model('setting/setting');
 *
 * @package Opencart\App\Model\Setting
 */
class Setting extends \Opencart\System\Engine\Model {
	/**
	 * Get Settings
	 *
	 * Get the record of the setting records in the database.
	 *
	 * @return array<int, array<string, mixed>> setting records
	 *
	 * @example
	 *
	 * $this->load->model('setting/setting');
	 *
	 * $results = $this->model_setting_setting->getSettings();
	 */
	public function getSettings(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` ORDER BY `setting_id` ASC");

		return $query->rows;
	}

	/**
	 * Edit Setting
	 *
	 * @param string               $code
	 * @param array<string, mixed> $data     array of data
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('setting/setting');
	 *
	 * $this->model_setting_setting->editSetting($code, $data);
	 */
	public function editSetting(string $code, array $data): void {
		$this->deleteSetting($code);

		foreach ($data as $key => $value) {
			if (substr($key, 0, strlen($code)) == $code) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(!is_array($value) ? $value : json_encode($value)) . "', `serialized` = '" . (bool)is_array($value) . "'");
			}
		}
	}

	/**
	 * Edit Value
	 *
	 * Update an existing global setting value without rewriting its group.
	 *
	 * @param string $code
	 * @param string $key
	 * @param string $value
	 *
	 * @return void
	 */
	public function editValue(string $code, string $key, string $value): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($value) . "', `serialized` = '0' WHERE `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "'");
	}

	/**
	 * Delete Setting
	 *
	 * @param string $code
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('setting/setting');
	 *
	 * $this->model_setting_setting->deleteSetting($code);
	 */
	public function deleteSetting(string $code): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . $this->db->escape($code) . "'");
	}
}
