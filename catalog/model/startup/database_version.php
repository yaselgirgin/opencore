<?php
namespace Opencart\Catalog\Model\Startup;
/**
 * Class DatabaseVersion
 *
 * @package Opencart\Catalog\Model\Startup
 */
class DatabaseVersion extends \Opencart\System\Engine\Model {
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
}
