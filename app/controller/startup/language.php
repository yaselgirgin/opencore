<?php
namespace Opencart\App\Controller\Startup;
/**
 * Class Language
 *
 * @package Opencart\App\Controller\Startup
 */
class Language extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<string, array<string, string>>
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
		if (isset(self::$languages[$this->config->get('config_language_admin')])) {
			$language_info = self::$languages[$this->config->get('config_language_admin')];
		}

		// If cookie has language stored
		if (isset($this->request->cookie['language']) && isset(self::$languages[$this->request->cookie['language']])) {
			$language_info = self::$languages[$this->request->cookie['language']];
		}

		if ($language_info) {
			// Set the config language_id key
			$this->config->set('config_language_id', $language_info['language_id']);
			$this->config->set('config_language_admin', $language_info['code']);

			$this->load->language('default');
		}
	}

	/**
	 * After
	 *
	 * Fill the language up with default values
	 *
	 * @param string       $route
	 * @param string       $prefix
	 * @param string       $code
	 * @param array<mixed> $output
	 *
	 * @return void
	 */
	public function after(string &$route, string &$prefix, string &$code, array &$output): void {
		if (!$code) {
			$code = $this->config->get('config_language_admin');
		}

		// Use $this->language->load so it's not triggering infinite loops
		$this->language->load($route, $prefix, $code);
	}
}
