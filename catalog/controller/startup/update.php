<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Update
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Update extends \Opencart\System\Engine\Controller {
	public function index(): ?\Opencart\System\Engine\Action {
		if (!oc_update_database_compatible($this->config)) {
			return new \Opencart\System\Engine\Action('api/system.unavailable');
		}

		return null;
	}
}
