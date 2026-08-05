<?php
namespace Opencart\Catalog\Controller\Api\System;
/**
 * Class Ping
 *
 * @package Opencart\Catalog\Controller\Api\System
 */
class Ping extends \Opencart\System\Engine\Controller {
	/**
	 * Health check
	 *
	 * @return void
	 */
	public function index(): void {
		$this->response->setOutput(json_encode(['success' => true]));
	}
}
