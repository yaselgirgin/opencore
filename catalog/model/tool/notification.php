<?php
namespace Opencart\Catalog\Model\Tool;

class Notification extends \Opencart\System\Engine\Model {
	public function deleteExpiredNotifications(): void {
		$query = $this->db->query("SELECT `notification_id` FROM `" . DB_PREFIX . "notification` WHERE `date_expire` IS NOT NULL AND `date_expire` <= NOW()");

		foreach ($query->rows as $result) {
			$notification_id = (int)$result['notification_id'];

			$this->db->query("DELETE FROM `" . DB_PREFIX . "notification_user` WHERE `notification_id` = '" . $notification_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "notification_target` WHERE `notification_id` = '" . $notification_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "notification` WHERE `notification_id` = '" . $notification_id . "'");
		}
	}
}
