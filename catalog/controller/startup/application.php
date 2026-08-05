<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Application
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Application extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Validation
		$this->load->helper('validation');
	}
}
