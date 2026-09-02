<?php
namespace Opencart\Api\Controller\Cron;
/**
 * Class Cron
 *
 * @package Opencart\Api\Controller\Cron
 */
class Cron extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param int|null $cron_id
	 *
	 * @return int
	 */
	public function index(?int $cron_id = null): int {
		$this->load->model('setting/cron');

		if ($cron_id !== null) {
			$result = $this->model_setting_cron->getCron($cron_id);

			if (!$result) {
				$this->log->write('Selected cron job was not found: ' . $cron_id);

				return 1;
			}

			if (!$result['status']) {
				$this->log->write('Selected cron job is disabled: ' . $result['code']);

				return 1;
			}

			if (!$this->isActionResolved($result['action'])) {
				$this->log->write('Selected cron job action is invalid or missing: ' . $result['code']);

				return 1;
			}

			if (!$this->execute($result)) {
				return 1;
			}

			$this->model_setting_cron->editCron($result['cron_id']);

			return 0;
		}

		$time = time();

		$results = $this->model_setting_cron->getCrons();

		foreach ($results as $result) {
			if ($result['status'] && (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10))) {
				if (!$this->execute($result)) {
					continue;
				}

				$this->model_setting_cron->editCron($result['cron_id']);
			}
		}

		return 0;
	}

	/**
	 * Execute
	 *
	 * @param array<string, mixed> $cron
	 *
	 * @return bool
	 */
	private function execute(array $cron): bool {
		try {
			$output = $this->load->controller($cron['action'], $cron['cron_id'], $cron['code'], $cron['cycle'], $cron['date_added'], $cron['date_modified']);

			if ($output instanceof \Throwable) {
				$this->log->write('Cron job "' . $cron['code'] . '" failed for action "' . $cron['action'] . '": ' . $output->getMessage());

				return false;
			}
		} catch (\Throwable $e) {
			$this->log->write('Cron job "' . $cron['code'] . '" failed for action "' . $cron['action'] . '": ' . $e->getMessage());

			return false;
		}

		return true;
	}

	/**
	 * Is Action Resolved
	 *
	 * @param string $action
	 *
	 * @return bool
	 */
	private function isActionResolved(string $action): bool {
		return (bool)preg_match('/^cron\/[a-z0-9_]+(?:\/[a-z0-9_]+)*$/', $action) && is_file(DIR_APPLICATION . 'controller/' . $action . '.php');
	}
}
