<?php
namespace Opencart\Admin\Controller\Startup;
/**
 * Class Application
 *
 * @package Opencart\Admin\Controller\Startup
 */
class Application extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Url
		$this->registry->set('url', new \Opencart\System\Library\Url($this->config->get('site_url')));

		// Currency
		$this->registry->set('currency', new \Opencart\System\Library\Cart\Currency($this->registry));

		// Weight
		$this->registry->set('weight', new \Opencart\System\Library\Cart\Weight($this->registry));

		// Length
		$this->registry->set('length', new \Opencart\System\Library\Cart\Length($this->registry));

		$this->load->helper('validation');
	}
}
