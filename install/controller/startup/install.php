<?php
namespace Opencart\Install\Controller\Startup;
/**
 * Class Install
 *
 * @package Opencart\Install\Controller\Startup
 */
class Install extends \Opencart\System\Engine\Controller {
	/**
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->registry->set('document', new \Opencart\System\Library\Document());
		$this->registry->set('url', new \Opencart\System\Library\Url(HTTP_SERVER));

		if (isset($this->request->get['language']) && $this->request->get['language'] !== $this->config->get('language_code')) {
			$languages = array_map('basename', glob(DIR_LANGUAGE . '*', GLOB_ONLYDIR) ?: []);

			if (in_array($this->request->get['language'], $languages, true)) {
				$this->config->set('language_code', $this->request->get['language']);
			}
		}

		$language = new \Opencart\System\Library\Language($this->config->get('language_code'));
		$language->addPath(DIR_LANGUAGE);
		$language->load('default');
		$this->registry->set('language', $language);

		return null;
	}
}
