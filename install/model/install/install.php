<?php
namespace Opencart\Install\Model\Install;
/**
 * Class Install
 *
 * @package Opencart\Install\Model\Install
 */
class Install extends \Opencart\System\Engine\Model {
	/**
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function database(array $data): void {
		$this->load->helper('db_schema');

		$args = [
			(string)$data['db_driver'],
			html_entity_decode((string)$data['db_hostname'], ENT_QUOTES, 'UTF-8'),
			html_entity_decode((string)$data['db_username'], ENT_QUOTES, 'UTF-8'),
			html_entity_decode((string)$data['db_password'], ENT_QUOTES, 'UTF-8'),
			html_entity_decode((string)$data['db_database'], ENT_QUOTES, 'UTF-8'),
			(string)$data['db_port'],
			(string)$data['db_prefix'],
			(string)$data['db_ssl_key'],
			(string)$data['db_ssl_cert'],
			(string)$data['db_ssl_ca']
		];

		if (!oc_db_create(...$args)) {
			throw new \RuntimeException('The OpenCore database schema could not be created.');
		}

		$db = new \Opencart\System\Library\DB($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[7], $args[8], $args[9]);
		$prefix = (string)$data['db_prefix'];

		$db->query("SET CHARACTER SET utf8mb4");
		$db->query("SET @@session.sql_mode = ''");

		$this->seedStaticData($db, $prefix);
		$this->seedAdministrator($db, $prefix, $data);
		$this->updateEmail($db, $prefix, (string)$data['email']);
	}

	private function seedStaticData(\Opencart\System\Library\DB $db, string $prefix): void {
		$language_code = (string)$this->config->get('language_code');

		if (!in_array($language_code, ['tr-tr', 'en-gb'], true)) {
			throw new \RuntimeException('The selected installer language is not supported.');
		}

		$file = DIR_APPLICATION . 'opencart-' . $language_code . '.sql';
		$lines = file($file, FILE_IGNORE_NEW_LINES);

		if ($lines === false) {
			throw new \RuntimeException('The selected installer data file could not be read.');
		}

		$sql = '';
		$started = false;
		$statements = 0;

		foreach ($lines as $line) {
			if (str_starts_with($line, 'INSERT INTO ')) {
				$sql = '';
				$started = true;
			}

			if ($started) {
				$sql .= $line . "\n";
			}

			if ($started && str_ends_with($line, ');')) {
				$db->query(str_replace('INSERT INTO `oc_', 'INSERT INTO `' . $prefix, trim($sql)));
				$started = false;
				$statements++;
			}
		}

		if ($started || !$statements) {
			throw new \RuntimeException('The selected installer data file is invalid.');
		}
	}

	private function seedAdministrator(\Opencart\System\Library\DB $db, string $prefix, array $data): void {
		$db->query("INSERT INTO `{$prefix}user` (`user_id`, `user_group_id`, `username`, `password`, `firstname`, `lastname`, `email`, `image`, `ip`, `status`, `date_added`) VALUES (1, 1, '" . $db->escape((string)$data['username']) . "', '" . $db->escape(password_hash(html_entity_decode((string)$data['password'], ENT_QUOTES, 'UTF-8'), PASSWORD_DEFAULT)) . "', '" . $db->escape((string)$data['firstname']) . "', '" . $db->escape((string)$data['lastname']) . "', '" . $db->escape((string)$data['email']) . "', '', '', 1, NOW())");
	}

	private function updateEmail(\Opencart\System\Library\DB $db, string $prefix, string $email): void {
		$db->query("UPDATE `{$prefix}setting` SET `value` = '" . $db->escape($email) . "' WHERE `code` = 'config' AND `key` = 'config_email'");
	}
}
