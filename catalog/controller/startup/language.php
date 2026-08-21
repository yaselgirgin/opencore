<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Language
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Language extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static array $languages = [];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Language
		$this->load->model('localisation/language');

		self::$languages = $this->model_localisation_language->getLanguages();

		$language_info = [];

		// Set default language
		if (isset(self::$languages[$this->config->get('config_language_catalog')])) {
			$language_info = self::$languages[$this->config->get('config_language_catalog')];
		}

		// If GET has language var
		if (isset($this->request->get['language']) && isset(self::$languages[$this->request->get['language']])) {
			$language_info = self::$languages[$this->request->get['language']];
		}

		if ($language_info) {
			// Set the config language_id key
			$this->config->set('config_language_id', $language_info['language_id']);
			$this->config->set('config_language', $language_info['code']);

			$this->load->language('default');
		}
	}

	/**
	 * After
	 *
	 * Override the language default values
	 *
	 * @param string       $route
	 * @param string       $prefix
	 * @param string       $code
	 * @param array<mixed> $output
	 *
	 * @return void
	 */
	public function after(&$route, &$prefix, &$code, &$output): void {
		if (!$code) {
			$code = $this->config->get('config_language');
		}

		// Use $this->language->load so it's not triggering infinite loops
		$this->language->load($route, $prefix, $code);
	}
}
