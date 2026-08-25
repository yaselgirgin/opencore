<?php
namespace Opencart\Admin\Controller\Startup;
/**
 * Class Update
 *
 * @package Opencart\Admin\Controller\Startup
 */
class Update extends \Opencart\System\Engine\Controller {
	public function index(): ?\Opencart\System\Engine\Action {
		$route = isset($this->request->get['route']) && is_string($this->request->get['route']) ? $this->request->get['route'] : '';

		if (!oc_update_database_compatible($this->config) && !oc_update_gate_admin_route_allowed($route)) {
			return new \Opencart\System\Engine\Action('tool/upgrade');
		}

		return null;
	}
}
