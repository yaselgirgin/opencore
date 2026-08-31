<?php
namespace Opencart\Catalog\Controller\Cron;

class NotificationCleanup extends \Opencart\System\Engine\Controller {
	public function index(int $cron_id, string $code, string $cycle, string $date_added, string $date_modified): void {
		$this->load->model('tool/notification');
		$this->model_tool_notification->deleteExpiredNotifications();
	}
}
