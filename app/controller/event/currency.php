<?php
namespace Opencart\App\Controller\Event;
/**
 * Class Currency
 *
 * @package Opencart\App\Controller\Event
 */
class Currency extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * Auto update currencies
	 *
	 * model/setting/setting.editSetting
	 *
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output
	 *
	 * @return void
	 */
	public function index(string &$route, array &$args, &$output): void {
		if ($route != 'setting/setting.editSetting' || $args[0] != 'config') {
			return;
		}

		if (array_key_exists('config_currency_auto', $args[1])) {
			$status = filter_var($args[1]['config_currency_auto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

			if ($status === null) {
				$this->log->write('Currency cron status synchronization failed: invalid config_currency_auto value.');

				return;
			}

			try {
				$this->load->model('setting/cron');

				$currency_cron = $this->model_setting_cron->getCronByCode('currency');

				if ($currency_cron && $currency_cron['action'] == 'cron/currency') {
					$this->model_setting_cron->editStatus((int)$currency_cron['cron_id'], $status);
				}
			} catch (\Throwable $e) {
				$this->log->write('Currency cron status synchronization failed: ' . $e->getMessage());
			}
		}

		if (isset($args[1]['config_currency'])) {
			try {
				$this->load->model('localisation/currency');
				$this->model_localisation_currency->refreshRates($args[1]['config_currency']);
			} catch (\Throwable $e) {
				$this->log->write('Automatic currency update failed: ' . $e->getMessage());
			}
		}
	}
}
