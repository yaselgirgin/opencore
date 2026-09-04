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

	/**
	 * Upgrade database revision 1 to 2.
	 *
	 * @return void
	 */
	public function upgrade2(): void {
		$notification = DB_PREFIX . 'notification';
		$columns = [];

		foreach ($this->db->query("SHOW COLUMNS FROM `" . $notification . "`")->rows as $column) {
			$columns[$column['Field']] = $column;
		}

		if (isset($columns['status'])) {
			$this->db->query("DELETE FROM `" . $notification . "`");
			$this->db->query("ALTER TABLE `" . $notification . "` DROP COLUMN `status`");
		}

		if (($columns['notification_id']['Type'] ?? '') != 'int(10) unsigned') {
			$this->db->query("ALTER TABLE `" . $notification . "` MODIFY `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
		}

		if (!isset($columns['code'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD `code` VARCHAR(64) NOT NULL AFTER `notification_id`");
		}

		if (!isset($columns['reference'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD `reference` VARCHAR(255) NULL AFTER `code`");
		}

		if (($columns['title']['Type'] ?? '') != 'varchar(255)') {
			$this->db->query("ALTER TABLE `" . $notification . "` MODIFY `title` VARCHAR(255) NOT NULL");
		}

		if (($columns['text']['Null'] ?? '') == 'YES') {
			$this->db->query("ALTER TABLE `" . $notification . "` MODIFY `text` TEXT NOT NULL");
		}

		if (($columns['date_added']['Null'] ?? '') == 'YES') {
			$this->db->query("ALTER TABLE `" . $notification . "` MODIFY `date_added` DATETIME NOT NULL");
		}

		if (!isset($columns['url'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD `url` VARCHAR(2048) NULL AFTER `text`");
		}

		if (!isset($columns['is_global'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD `is_global` TINYINT(1) NOT NULL DEFAULT '0' AFTER `url`");
		}

		if (!isset($columns['date_expire'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD `date_expire` DATETIME NULL AFTER `date_added`");
		}

		$indexes = [];

		foreach ($this->db->query("SHOW INDEX FROM `" . $notification . "`")->rows as $index) {
			$indexes[$index['Key_name']] = true;
		}

		if (!isset($indexes['code_reference'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD KEY `code_reference` (`code`, `reference`)");
		}

		if (!isset($indexes['date_added'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD KEY `date_added` (`date_added`)");
		}

		if (!isset($indexes['date_expire'])) {
			$this->db->query("ALTER TABLE `" . $notification . "` ADD KEY `date_expire` (`date_expire`)");
		}

		$target_table = $this->db->query("SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape(DB_PREFIX . 'notification_target') . "' LIMIT 1");

		if (!$target_table->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "notification_target` (`notification_target_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `notification_id` INT UNSIGNED NOT NULL, `target_type` VARCHAR(32) NOT NULL, `target_id` INT UNSIGNED NOT NULL, PRIMARY KEY (`notification_target_id`), UNIQUE KEY `notification_target` (`notification_id`, `target_type`, `target_id`), KEY `target` (`target_type`, `target_id`, `notification_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}

		$user_table = $this->db->query("SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape(DB_PREFIX . 'notification_user') . "' LIMIT 1");

		if (!$user_table->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "notification_user` (`notification_id` INT UNSIGNED NOT NULL, `user_id` INT UNSIGNED NOT NULL, `status` TINYINT(1) NOT NULL, `date_modified` DATETIME NOT NULL, PRIMARY KEY (`notification_id`, `user_id`), KEY `user_status` (`user_id`, `status`, `notification_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		$setting = $this->db->query("SELECT `setting_id` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config' AND `key` = 'config_notification_expire_days' LIMIT 1");

		if (!$setting->num_rows) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `code` = 'config', `key` = 'config_notification_expire_days', `value` = '7', `serialized` = '0'");
		}
		$cron = $this->db->query("SELECT `cron_id` FROM `" . DB_PREFIX . "cron` WHERE `code` = 'notification_cleanup' LIMIT 1");

		if (!$cron->num_rows) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "cron` SET `code` = 'notification_cleanup', `description` = 'Removes expired notifications.', `cycle` = 'day', `action` = 'cron/notification_cleanup', `status` = '1', `date_added` = NOW(), `date_modified` = NOW()");
		}
	}

	/**
	 * Upgrade database revision 2 to 3.
	 *
	 * @return void
	 */
	public function upgrade3(): void {
		$table = $this->db->query("SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape(DB_PREFIX . 'release_notification') . "' LIMIT 1");

		if (!$table->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "release_notification` (`release_notification_id` TINYINT UNSIGNED NOT NULL, `release_version` VARCHAR(255) NOT NULL, `date_modified` DATETIME NOT NULL, PRIMARY KEY (`release_notification_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		} else {
			$columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "release_notification`")->rows;
			$release_version = [];

			foreach ($columns as $column) {
				$release_version[$column['Field']] = $column;
			}

			if (($release_version['release_version']['Type'] ?? '') != 'varchar(255)') {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "release_notification` MODIFY `release_version` VARCHAR(255) NOT NULL");
			}
		}
	}

	/**
	 * Upgrade database revision 3 to 4.
	 *
	 * @return void
	 */
	public function upgrade4(): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "event` SET `trigger` = CONCAT('app/', SUBSTRING(`trigger`, 7)) WHERE `trigger` LIKE 'admin/%'");

		$codes = [
			'admin_currency_setting' => 'app_currency_setting',
			'admin_mail_user_forgotten' => 'app_mail_user_forgotten',
			'admin_mail_user_authorize' => 'app_mail_user_authorize',
			'admin_mail_user_authorize_reset' => 'app_mail_user_authorize_reset'
		];

		foreach ($codes as $old => $new) {
			$this->db->query("UPDATE `" . DB_PREFIX . "event` SET `code` = '" . $new . "' WHERE `code` = '" . $old . "'");
		}
	}

	/**
	 * Upgrade database revision 4 to 5.
	 *
	 * @return void
	 */
	public function upgrade5(): void {
		$table = $this->db->query("SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape(DB_PREFIX . 'user_preference') . "' LIMIT 1");

		if (!$table->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "user_preference` (`user_id` INT(11) NOT NULL, `color_mode` VARCHAR(16) NOT NULL DEFAULT 'system', `color_scheme` VARCHAR(16) NOT NULL DEFAULT 'blue', `font_family` VARCHAR(16) NOT NULL DEFAULT 'sans-serif', `theme_base` VARCHAR(16) NOT NULL DEFAULT 'slate', `corner_radius` DECIMAL(2,1) NOT NULL DEFAULT '0.5', `menu` VARCHAR(16) NOT NULL DEFAULT 'expanded', `content_width` VARCHAR(16) NOT NULL DEFAULT 'wide', PRIMARY KEY (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
	}
}
