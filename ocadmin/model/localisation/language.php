<?php
namespace Opencart\Admin\Model\Localisation;
/**
 * Class Language
 *
 * Can be loaded using $this->load->model('localisation/language');
 *
 * @package Opencart\Admin\Model\Localisation
 */
class Language extends \Opencart\System\Engine\Model {
	/**
	 * Add Language
	 *
	 * Create a new language record in the database.
	 *
	 * @param array<string, mixed> $data array of data
	 *
	 * @return int returns the primary key of the new language record
	 *
	 * @example
	 *
	 * $language_data = [
	 *     'name'       => 'Language Name',
	 *     'code'       => 'Language Code',
	 *     'locale'     => 'Language Locale',
	 *     'extension'  => '',
	 *     'sort_order' => 0,
	 *     'status'     => 0
	 * ];
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $language_id = $this->model_localisation_language->addLanguage($language_data);
	 */
	public function addLanguage(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "language` SET `name` = '" . $this->db->escape((string)$data['name']) . "', `code` = '" . $this->db->escape((string)$data['code']) . "', `locale` = '" . $this->db->escape((string)$data['locale']) . "', `extension` = '" . $this->db->escape((string)$data['extension']) . "', `sort_order` = '" . (int)$data['sort_order'] . "', `status` = '" . (bool)($data['status'] ?? 0) . "'");

		$this->cache->delete('language');

		$language_id = $this->db->getLastId();

		// Country
		$this->load->model('localisation/country');

		$results = $this->model_localisation_country->getDescriptionsByLanguageId($this->config->get('config_language_id'));

		foreach ($results as $country) {
			$this->model_localisation_country->addDescription($country['country_id'], $language_id, $country);
		}

		// Length
		$this->load->model('localisation/length_class');

		$results = $this->model_localisation_length_class->getDescriptionsByLanguageId($this->config->get('config_language_id'));

		foreach ($results as $length) {
			$this->model_localisation_length_class->addDescription($length['length_class_id'], $language_id, $length);
		}


		// Weight Class
		$this->load->model('localisation/weight_class');

		$results = $this->model_localisation_weight_class->getDescriptionsByLanguageId($this->config->get('config_language_id'));

		foreach ($results as $weight_class) {
			$this->model_localisation_weight_class->addDescription($weight_class['weight_class_id'], $language_id, $weight_class);
		}

		// Zone
		$this->load->model('localisation/zone');

		$results = $this->model_localisation_zone->getDescriptionsByLanguageId($this->config->get('config_language_id'));

		foreach ($results as $zone) {
			$this->model_localisation_zone->addDescription($zone['zone_id'], $language_id, $zone);
		}

		return $language_id;
	}

	/**
	 * Edit Language
	 *
	 * Edit language record in the database.
	 *
	 * @param int                  $language_id primary key of the language record
	 * @param array<string, mixed> $data        array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $language_data = [
	 *     'name'       => 'Language Name',
	 *     'code'       => 'Language Code',
	 *     'locale'     => 'Language Locale',
	 *     'extension'  => '',
	 *     'sort_order' => 0,
	 *     'status'     => 1
	 * ];
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $this->model_localisation_language->editLanguage($language_id, $language_data);
	 */
	public function editLanguage(int $language_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "language` SET `name` = '" . $this->db->escape((string)$data['name']) . "', `code` = '" . $this->db->escape((string)$data['code']) . "', `locale` = '" . $this->db->escape((string)$data['locale']) . "', `extension` = '" . $this->db->escape((string)$data['extension']) . "', `sort_order` = '" . (int)$data['sort_order'] . "', `status` = '" . (bool)($data['status'] ?? 0) . "' WHERE `language_id` = '" . (int)$language_id . "'");

		$this->cache->delete('language');
	}

	/**
	 * Delete Language
	 *
	 * Delete language record in the database.
	 *
	 * @param int $language_id primary key of the language record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $this->model_localisation_language->deleteLanguage($language_id);
	 */
	public function deleteLanguage(int $language_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "language` WHERE `language_id` = '" . (int)$language_id . "'");

		$this->cache->delete('language');

		// Country
		$this->load->model('localisation/country');

		$this->model_localisation_country->deleteDescriptionsByLanguageId($language_id);

		// Length Class
		$this->load->model('localisation/length_class');

		$this->model_localisation_length_class->deleteDescriptionsByLanguageId($language_id);


		// Weight Class
		$this->load->model('localisation/weight_class');

		$this->model_localisation_weight_class->deleteDescriptionsByLanguageId($language_id);

		// Zone
		$this->load->model('localisation/zone');

		$this->model_localisation_zone->deleteDescriptionsByLanguageId($language_id);
	}

	/**
	 * Get Language
	 *
	 * Get the record of the language record in the database.
	 *
	 * @param int $language_id primary key of the language record
	 *
	 * @return array<string, mixed> language record that has language ID
	 *
	 * @example
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $language_info = $this->model_localisation_language->getLanguage($language_id);
	 */
	public function getLanguage(int $language_id): array {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "language` WHERE `language_id` = '" . (int)$language_id . "'");

		$language = $query->row;

		if ($language) {
			$language['image'] = HTTP_CATALOG;

			if (!$language['extension']) {
				$language['image'] .= 'catalog/';
			} else {
				$language['image'] .= 'extension/' . $language['extension'] . '/catalog/';
			}

			$language['image'] .= 'language/' . $language['code'] . '/' . $language['code'] . '.png';
		}

		return $language;
	}

	/**
	 * Get Language By Code
	 *
	 * @param string $code
	 *
	 * @return array<string, mixed>
	 *
	 * @example
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $language_info = $this->model_localisation_language->getLanguageByCode($code);
	 */
	public function getLanguageByCode(string $code): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE `code` = '" . $this->db->escape($code) . "'");

		$language = $query->row;

		if ($language) {
			$language['image'] = HTTP_CATALOG;

			if (!$language['extension']) {
				$language['image'] .= 'catalog/';
			} else {
				$language['image'] .= 'extension/' . $language['extension'] . '/catalog/';
			}

			$language['image'] .= 'language/' . $language['code'] . '/' . $language['code'] . '.png';
		}

		return $language;
	}

	/**
	 * Get Languages
	 *
	 * Get the record of the language records in the database.
	 *
	 * @param array<string, mixed> $data array of filters
	 *
	 * @return array<string, array<string, mixed>> language records
	 *
	 * @example
	 *
	 * $filter_data = [
	 *     'sort'  => 'name',
	 *     'order' => 'DESC',
	 *     'start' => 0,
	 *     'limit' => 10
	 * ];
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $languages = $this->model_localisation_language->getLanguages($filter_data);
	 */
	public function getLanguages(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "language`";

		$sort_data = [
			'name',
			'code',
			'sort_order'
		];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY `sort_order`, `name`";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$results = $this->cache->get('language.' . md5($sql));

		if (!$results) {
			$query = $this->db->query($sql);

			$results = $query->rows;

			$this->cache->set('language.' . md5($sql), $results);
		}

		$language_data = [];

		foreach ($results as $result) {
			$image = HTTP_CATALOG;

			if (!$result['extension']) {
				$image .= 'catalog/';
			} else {
				$image .= 'extension/' . $result['extension'] . '/catalog/';
			}

			$language_data[$result['code']] = $result + ['image' => $image . 'language/' . $result['code'] . '/' . $result['code'] . '.png'];
		}

		return $language_data;
	}

	/**
	 * Get Languages By Extension
	 *
	 * @param string $extension
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $results = $this->model_localisation_language->getLanguagesByExtension($extension);
	 */
	public function getLanguagesByExtension(string $extension): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE `extension` = '" . $this->db->escape($extension) . "'");

		return $query->rows;
	}

	/**
	 * Get Total Languages
	 *
	 * Get the total number of language records in the database.
	 *
	 * @return int total number of language records
	 *
	 * @example
	 *
	 * $this->load->model('localisation/language');
	 *
	 * $language_total = $this->model_localisation_language->getTotalLanguages();
	 */
	public function getTotalLanguages(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "language`");

		return (int)$query->row['total'];
	}
}
