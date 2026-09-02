<?php
namespace Opencart\Api\Controller\Api;
/**
 * Class System
 *
 * @package Opencart\Api\Controller\Api
 */
class System extends \Opencart\System\Engine\Controller {
	/**
	 * API root
	 *
	 * @return void
	 */
	public function index(): void {
		$this->response->setOutput(json_encode([
			'success'     => true,
			'application' => 'OpenCore API'
		]));
	}

	/**
	 * JSON not found response
	 *
	 * @return void
	 */
	public function notFound(): void {
		$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
		$this->response->setOutput(json_encode([
			'success' => false,
			'error'   => 'Not Found'
		]));
	}
}
