<?php
namespace Opencart\Install\Controller\Install;
/**
 * Class Step1
 *
 * @package Opencart\Install\Controller\Install
 */
class Step1 extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->session->data['install_step'] = 1;

		$this->load->language('install/step_1');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_step_1'] = $this->language->get('text_step_1');
		$data['button_continue'] = $this->language->get('button_continue');

		$license_file = DIR_OPENCART . 'LICENSE';
		$license = is_readable($license_file) ? file_get_contents($license_file) : false;

		if ($license === false || trim($license) === '') {
			$data['error_warning'] = $this->language->get('error_license');
			$data['license'] = '';
			$data['continue'] = '';
		} else {
			$data['error_warning'] = '';
			$data['license'] = $this->formatLicense($license);
			$data['continue'] = $this->url->link('install/step_2', 'language=' . $this->config->get('language_code'));
		}

		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('install/step_1', $data));
	}

	/**
	 * Format the canonical plain-text GPL for the installer view.
	 *
	 * @param string $license
	 *
	 * @return string
	 */
	private function formatLicense(string $license): string {
		$lines = preg_split('/\R/u', trim($license));
		$output = [];
		$paragraph = [];
		$section = 0;

		$flush = static function () use (&$output, &$paragraph): void {
			if ($paragraph) {
				$output[] = '<p>' . implode("\n", $paragraph) . '</p>';
				$paragraph = [];
			}
		};

		foreach ($lines as $line) {
			$text = trim($line);

			if ($text === '') {
				$flush();
				continue;
			}

			$escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

			if (in_array($text, ['GNU GENERAL PUBLIC LICENSE', 'Preamble', 'TERMS AND CONDITIONS', 'How to Apply These Terms to Your New Programs'], true)) {
				$flush();
				$output[] = '<h3>' . $escaped . '</h3>';
			} elseif (preg_match('/^(\d+)\.\s/u', $text, $matches) && (int)$matches[1] === $section) {
				$flush();
				$output[] = '<h4>' . $escaped . '</h4>';
				$section++;
			} else {
				$paragraph[] = $escaped;
			}
		}

		$flush();

		return implode("\n", $output);
	}
}
