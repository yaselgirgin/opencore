<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Api
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Api extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		if (isset($this->request->get['route'])) {
			$route = strtolower((string)$this->request->get['route']);
		} else {
			$route = '';
		}

		$public = [
			'api/system',
			'api/system/ping'
		];

		if ($route && (!str_starts_with($route, 'api/') || !in_array($route, $public, true))) {
			return new \Opencart\System\Engine\Action('api/system.notFound');
		}

		return null;
	}
}
