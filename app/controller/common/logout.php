<?php
namespace Opencart\App\Controller\Common;
/**
 * Class Logout
 *
 * Can be loaded using $this->load->controller('common/logout');
 *
 * @package Opencart\App\Controller\Common
 */
class Logout extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->user->logout();

		unset($this->session->data['user_token']);

		$this->response->redirect($this->url->link('common/login', '', true));
	}
}
