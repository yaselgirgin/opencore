<?php
namespace Opencart\Admin\Controller\Event;
/**
 * Class Currency
 *
 * @package Opencart\Admin\Controller\Event
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
		if ($route != 'model/setting/setting.editSetting' || $args[0] != 'config' || !isset($args[1]['config_currency'])) {
			return;
		}

		try {
			$this->load->model('localisation/currency');
			$this->model_localisation_currency->refreshRates($args[1]['config_currency']);
		} catch (\Throwable $e) {
			$this->log->write('Automatic currency update failed: ' . $e->getMessage());
		}
	}
}
