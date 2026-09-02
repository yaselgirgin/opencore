<?php
namespace Opencart\Api\Controller\Cron;
/**
 * Class Currency
 *
 * @package Opencart\Api\Controller\Cron
 */
class Currency extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param int    $cron_id
	 * @param string $code
	 * @param string $cycle
	 * @param string $date_added
	 * @param string $date_modified
	 *
	 * @return void
	 */
	public function index(int $cron_id, string $code, string $cycle, string $date_added, string $date_modified): void {
		$this->load->model('localisation/currency');
		$this->model_localisation_currency->refreshRates((string)$this->config->get('config_currency'));
	}
}
