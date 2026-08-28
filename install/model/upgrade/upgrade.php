<?php
namespace Opencart\Install\Model\Upgrade;
/**
 * Class Upgrade
 *
 * @package Opencart\Install\Model\Upgrade
 */
class Upgrade extends \Opencart\System\Engine\Model {
	/**
	 * @return int|null
	 */
	public function getDatabaseVersion(): ?int {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version' LIMIT 1");

		if (!$query->num_rows) {
			return null;
		}

		$value = trim((string)$query->row['value']);

		return ctype_digit($value) && (int)$value > 0 ? (int)$value : null;
	}

	/**
	 * @param int $version
	 *
	 * @return void
	 */
	public function setDatabaseVersion(int $version): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $version . "' WHERE `code` = 'system' AND `key` = 'database_version'");

		if (!$this->db->countAffected()) {
			throw new \RuntimeException('The database revision could not be saved.');
		}
	}
}
