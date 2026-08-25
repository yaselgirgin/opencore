<?php
namespace Opencart\Catalog\Controller\Api;
/**
 * Class System
 *
 * @package Opencart\Catalog\Controller\Api
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

	public function unavailable(): void {
		$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 503 Service Unavailable');
		$this->response->addHeader('Retry-After: 60');
		$this->response->setOutput(json_encode([
			'success' => false,
			'error'   => 'OpenCore database compatibility recovery is required.'
		]));
	}
}
