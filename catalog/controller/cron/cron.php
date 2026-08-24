<?php
namespace Opencart\Catalog\Controller\Cron;
/**
 * Class Cron
 *
 * @package Opencart\Catalog\Controller\Cron
 */
class Cron extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		if (PHP_SAPI !== 'cli') {
			$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(['error' => 'Cron execution is only available from the command line.']));

			return;
		}

		$time = time();

		$this->load->model('setting/cron');

		$results = $this->model_setting_cron->getCrons();

		foreach ($results as $result) {
			if ($result['status'] && (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10))) {
				try {
					$output = $this->load->controller($result['action'], $result['cron_id'], $result['code'], $result['cycle'], $result['date_added'], $result['date_modified']);

					if ($output instanceof \Throwable) {
						$this->log->write('Cron job "' . $result['code'] . '" failed for action "' . $result['action'] . '": ' . $output->getMessage());

						continue;
					}
				} catch (\Throwable $e) {
					$this->log->write('Cron job "' . $result['code'] . '" failed for action "' . $result['action'] . '": ' . $e->getMessage());

					continue;
				}

				$this->model_setting_cron->editCron($result['cron_id']);
			}
		}
	}
}
