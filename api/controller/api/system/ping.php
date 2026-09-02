<?php
namespace Opencart\Api\Controller\Api\System;
/**
 * Class Ping
 *
 * @package Opencart\Api\Controller\Api\System
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
