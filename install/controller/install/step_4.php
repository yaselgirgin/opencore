<?php
namespace Opencart\Install\Controller\Install;
/**
 * Class Step4
 *
 * @package Opencart\Install\Controller\Install
 */
class Step4 extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		if (($this->session->data['install_step'] ?? 0) !== 3) {
			$this->response->redirect($this->url->link('install/step_1', 'language=' . $this->config->get('language_code')));

			return;
		}

		$this->render(false);
	}

	/**
	 * @return void
	 */
	public function blocked(): void {
		$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
		$this->render(true);
	}

	private function render(bool $blocked): void {
		$this->load->language('install/step_4');
		$this->document->setTitle($this->language->get($blocked ? 'heading_blocked' : 'heading_title'));

		$data['blocked'] = $blocked;
		$data['heading_title'] = $this->language->get($blocked ? 'heading_blocked' : 'heading_title');
		$data['text_message'] = $this->language->get($blocked ? 'text_blocked' : 'text_success');
		$data['text_warning'] = $blocked ? '' : $this->language->get('text_warning');
		$data['text_app'] = $this->language->get('text_app');
		$data['app'] = HTTP_OPENCART;
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('install/step_4', $data));
	}
}
