<?php
namespace Opencart\App\Model\Tool;

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

	public function addNotification(array $data): int {
		$code = trim((string)($data['code'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		$text = (string)($data['text'] ?? '');
		$is_global = !empty($data['is_global']);
		$targets = $this->validateTargets((array)($data['targets'] ?? []), $is_global);

		if ($code === '' || $title === '' || $text === '') {
			throw new \InvalidArgumentException('Notification code, title and text are required.');
		}

		if (array_key_exists('date_expire', $data)) {
			$date_expire = $data['date_expire'] === null ? null : (string)$data['date_expire'];
			$expire = $date_expire === null ? 'NULL' : "'" . $this->db->escape($date_expire) . "'";
		} else {
			$days = (int)$this->config->get('config_notification_expire_days');

			if ($days < 1) {
				$days = 7;
			}

			$expire = 'DATE_ADD(NOW(), INTERVAL ' . $days . ' DAY)';
		}

		$reference = ($data['reference'] ?? null) === null ? 'NULL' : "'" . $this->db->escape((string)$data['reference']) . "'";
		$url = ($data['url'] ?? null) === null ? 'NULL' : "'" . $this->db->escape((string)$data['url']) . "'";

		$this->db->query("INSERT INTO `" . DB_PREFIX . "notification` SET `code` = '" . $this->db->escape($code) . "', `reference` = " . $reference . ", `title` = '" . $this->db->escape($title) . "', `text` = '" . $this->db->escape($text) . "', `url` = " . $url . ", `is_global` = '" . (int)$is_global . "', `date_added` = NOW(), `date_expire` = " . $expire);

		$notification_id = $this->db->getLastId();

		try {
			foreach ($targets as $target) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "notification_target` SET `notification_id` = '" . $notification_id . "', `target_type` = '" . $this->db->escape($target['target_type']) . "', `target_id` = '" . $target['target_id'] . "'");
			}
		} catch (\Throwable $e) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "notification_target` WHERE `notification_id` = '" . $notification_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "notification` WHERE `notification_id` = '" . $notification_id . "'");

			throw $e;
		}

		return $notification_id;
	}

	public function getNotification(int $notification_id, int $user_id, int $user_group_id): array {
		$sql = $this->getVisibleSql($user_id, $user_group_id) . " AND `n`.`notification_id` = '" . $notification_id . "' LIMIT 1";

		return $this->db->query($sql)->row;
	}

	public function getNotifications(int $user_id, int $user_group_id, array $data = []): array {
		$sql = $this->getVisibleSql($user_id, $user_group_id) . " ORDER BY `n`.`date_added` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = max(0, (int)($data['start'] ?? 0));
			$limit = max(1, (int)($data['limit'] ?? 20));
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalNotifications(int $user_id, int $user_group_id, bool $unread_only = false): int {
		$sql = "SELECT COUNT(*) AS `total` FROM (" . $this->getVisibleSql($user_id, $user_group_id, $unread_only) . ") AS `notification_total`";

		return (int)$this->db->query($sql)->row['total'];
	}

	public function markRead(int $notification_id, int $user_id): void {
		$this->setUserStatus($notification_id, $user_id, 1);
	}

	public function dismiss(int $notification_id, int $user_id): void {
		$this->setUserStatus($notification_id, $user_id, 2);
	}

	public function markUnread(int $notification_id, int $user_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "notification_user` WHERE `notification_id` = '" . $notification_id . "' AND `user_id` = '" . $user_id . "'");
	}

	private function setUserStatus(int $notification_id, int $user_id, int $status): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "notification_user` SET `notification_id` = '" . $notification_id . "', `user_id` = '" . $user_id . "', `status` = '" . $status . "', `date_modified` = NOW() ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `date_modified` = NOW()");
	}

	private function getVisibleSql(int $user_id, int $user_group_id, bool $unread_only = false): string {
		$sql = "SELECT `n`.*, COALESCE(`nu`.`status`, 0) AS `status` FROM `" . DB_PREFIX . "notification` `n` LEFT JOIN `" . DB_PREFIX . "notification_user` `nu` ON (`nu`.`notification_id` = `n`.`notification_id` AND `nu`.`user_id` = '" . $user_id . "') WHERE (`n`.`date_expire` IS NULL OR `n`.`date_expire` > NOW()) AND (`nu`.`status` IS NULL OR `nu`.`status` != '2') AND (`n`.`is_global` = '1' OR EXISTS (SELECT 1 FROM `" . DB_PREFIX . "notification_target` `nt` WHERE `nt`.`notification_id` = `n`.`notification_id` AND ((`nt`.`target_type` = 'user' AND `nt`.`target_id` = '" . $user_id . "') OR (`nt`.`target_type` = 'user_group' AND `nt`.`target_id` = '" . $user_group_id . "'))))";

		if ($unread_only) {
			$sql .= " AND `nu`.`status` IS NULL";
		}

		return $sql;
	}

	private function validateTargets(array $targets, bool $is_global): array {
		$valid = [];

		foreach ($targets as $target) {
			$type = (string)($target['target_type'] ?? '');
			$id = filter_var($target['target_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

			if (!in_array($type, ['user', 'user_group'], true) || $id === false) {
				throw new \InvalidArgumentException('Notification targets must be a user or user_group with a positive ID.');
			}

			$valid[$type . ':' . $id] = ['target_type' => $type, 'target_id' => (int)$id];
		}

		if (!$is_global && !$valid) {
			throw new \InvalidArgumentException('Non-global notifications require at least one target.');
		}

		return array_values($valid);
	}
}
