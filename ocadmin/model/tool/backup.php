<?php
namespace Opencart\Admin\Model\Tool;
/**
 * Class Backup
 *
 * Can be loaded using $this->load->model('tool/backup');
 *
 * @package Opencart\Admin\Model\Tool
 */
class Backup extends \Opencart\System\Engine\Model {
	private const FORMAT_VERSION = 1;
	private const ROW_BATCH_SIZE = 200;

	public function createCompleteBackup(string $directory, array $context): array {
		$this->assertBackupDirectory($directory, false);
		$this->assertContext($context);

		$temporary = rtrim($directory, '/\\') . '.tmp-' . bin2hex(random_bytes(8)) . '/';

		if (!mkdir($temporary, 0750, true)) {
			throw new \RuntimeException('Database backup workspace could not be created.');
		}

		try {
			$metadata = $this->createSqlBackup($temporary . 'database.sql', $context, $this->getCompleteObjectInventory()['tables']);
			$evidence = $metadata + [
				'database_sql' => ['size' => filesize($temporary . 'database.sql'), 'sha256' => hash_file('sha256', $temporary . 'database.sql')],
				'status'       => 'VERIFIED'
			];
			$this->writeJsonFile($temporary . 'evidence.json', $evidence);
			$this->validateCompleteBackup($temporary, DB_DATABASE);

			if (!rename(rtrim($temporary, '/\\'), rtrim($directory, '/\\'))) {
				throw new \RuntimeException('Verified database backup could not be activated.');
			}

			return $this->validateCompleteBackup($directory, DB_DATABASE);
		} catch (\Throwable $throwable) {
			$this->removeBackupTree($temporary);
			throw $throwable;
		}
	}

	public function createManualBackup(string $file, array $tables): array {
		$this->assertManualBackupFile($file, false);
		$available = $this->getCompleteObjectInventory()['tables'];
		$tables = array_values(array_unique($tables));
		sort($tables, SORT_STRING);

		if (!$tables || array_diff($tables, $available)) {
			throw new \RuntimeException('Manual backup table selection is invalid.');
		}

		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version'");
		$context = ['source_version' => VERSION, 'source_database_version' => $query->num_rows === 1 ? (string)$query->row['value'] : null, 'target_version' => VERSION, 'updates' => []];
		$temporary = $file . '.tmp-' . bin2hex(random_bytes(8));

		try {
			$metadata = $this->createSqlBackup($temporary, $context, $tables);
			if (!rename($temporary, $file)) {
				throw new \RuntimeException('Manual SQL backup could not be activated.');
			}
			return $metadata;
		} catch (\Throwable $throwable) {
			@unlink($temporary);
			throw $throwable;
		}
	}

	public function validateCompleteBackup(string $directory, string $expected_database): array {
		$this->assertBackupDirectory($directory, true);
		$evidence = $this->readJsonFile(rtrim($directory, '/\\') . '/evidence.json');
		$sql = rtrim($directory, '/\\') . '/database.sql';

		if (($evidence['format_version'] ?? null) !== self::FORMAT_VERSION || ($evidence['database'] ?? '') !== $expected_database || ($evidence['db_prefix'] ?? null) !== DB_PREFIX || ($evidence['status'] ?? '') !== 'VERIFIED' || !is_array($evidence['tables'] ?? null) || array_keys($evidence['database_sql'] ?? []) !== ['size', 'sha256'] || !is_file($sql) || filesize($sql) !== $evidence['database_sql']['size'] || !hash_equals((string)$evidence['database_sql']['sha256'], hash_file('sha256', $sql))) {
			throw new \RuntimeException('Database backup identity is invalid.');
		}
		$files = array_values(array_diff(scandir(rtrim($directory, '/\\')) ?: [], ['.', '..']));
		sort($files, SORT_STRING);
		if ($files !== ['database.sql', 'evidence.json']) {
			throw new \RuntimeException('Database backup contains undeclared components.');
		}
		$this->validateSqlContract($sql, $evidence);

		return ['metadata' => $evidence, 'evidence' => $evidence, 'evidence_sha256' => hash_file('sha256', rtrim($directory, '/\\') . '/evidence.json')];
	}

	public function restoreCompleteBackup(string $directory, string $expected_database): array {
		$backup = $this->validateCompleteBackup($directory, $expected_database);
		$metadata = $backup['metadata'];
		$sql = rtrim($directory, '/\\') . '/database.sql';
		$current = $this->getCompleteObjectInventory();
		$foreign_key_checks = (int)$this->db->query('SELECT @@FOREIGN_KEY_CHECKS AS `value`')->row['value'];
		$expected_tables = array_column($metadata['tables'], 'table');

		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');

		try {
			foreach (array_reverse($current['tables']) as $table) {
				$this->db->query('DROP TABLE `' . $this->db->escape($table) . '`');
			}

			$this->readSqlStatements($sql, function(string $statement): void {
				$this->db->query(substr($statement, 0, -1));
			});
		} catch (\Throwable $throwable) {
			throw new \RuntimeException('Database restore failed and may be partial.', 0, $throwable);
		} finally {
			$this->db->query('SET FOREIGN_KEY_CHECKS = ' . $foreign_key_checks);
		}

		$restored = $this->getCompleteObjectInventory();
		if ($restored['tables'] !== $expected_tables) {
			throw new \RuntimeException('Restored database inventory is inconsistent.');
		}

		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version'");
		$expected_version = $metadata['source_database_version'];
		if (($expected_version === null && $query->num_rows !== 0) || ($expected_version !== null && ($query->num_rows !== 1 || $query->row['value'] !== $expected_version))) {
			throw new \RuntimeException('Restored database version metadata is inconsistent.');
		}
		foreach ($metadata['tables'] as $table) {
			$name = $this->validateTableMetadata($table);
			if ((int)$this->db->query('SELECT COUNT(*) AS `total` FROM `' . $this->db->escape($name) . '`')->row['total'] !== $table['rows']) {
				throw new \RuntimeException('Database restored row count is inconsistent.');
			}
		}

		return ['status' => 'DATABASE_RESTORED', 'database_version' => $expected_version, 'tables' => count($metadata['tables'])];
	}

	public function isCompleteSqlBackup(string $file): bool {
		if (!is_file($file)) {
			return false;
		}
		$handle = fopen($file, 'rb');
		if (!$handle) {
			return false;
		}
		$complete = trim((string)fgets($handle)) === '-- OpenCore SQL Backup Format ' . self::FORMAT_VERSION;
		fclose($handle);
		return $complete;
	}

	public function restoreManualBackup(string $file): array {
		$this->assertManualBackupFile($file, true);
		$metadata = $this->readSqlMetadata($file);
		if (($metadata['database'] ?? '') !== DB_DATABASE || ($metadata['db_prefix'] ?? '') !== DB_PREFIX) {
			throw new \RuntimeException('Manual SQL backup database identity is invalid.');
		}
		$this->validateSqlContract($file, $metadata);
		$foreign_key_checks = (int)$this->db->query('SELECT @@FOREIGN_KEY_CHECKS AS `value`')->row['value'];
		try {
			$this->readSqlStatements($file, function(string $statement): void {
				$this->db->query(substr($statement, 0, -1));
			});
		} finally {
			$this->db->query('SET FOREIGN_KEY_CHECKS = ' . $foreign_key_checks);
		}
		return $metadata;
	}
	/**
	 * Get Tables
	 *
	 * @return array<int, string>
	 *
	 * @example
	 *
	 * $this->load->model('tool/backup');
	 *
	 * $tables = $this->model_tool_backup->getTables();
	 */
	public function getTables(): array {
		$table_data = [];

		$query = $this->db->query("SHOW TABLES FROM `" . DB_DATABASE . "`");

		foreach ($query->rows as $result) {
			if (isset($result['Tables_in_' . DB_DATABASE]) && substr($result['Tables_in_' . DB_DATABASE], 0, strlen(DB_PREFIX)) == DB_PREFIX) {
				$table_data[] = $result['Tables_in_' . DB_DATABASE];
			}
		}

		return $table_data;
	}

	/**
	 * Get Records
	 *
	 * Get the record of the database table records in the database.
	 *
	 * @param string $table
	 * @param int    $start
	 * @param int    $limit
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $this->load->model('tool/backup');
	 *
	 * $records = $this->model_tool_backup->getRecords($table, $start, $limit);
	 */
	public function getRecords(string $table, int $start = 0, int $limit = 100): array {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT * FROM `" . $table . "` LIMIT " . (int)$start . "," . (int)$limit);

		if ($query->num_rows) {
			return $query->rows;
		} else {
			return [];
		}
	}

	/**
	 * Get Total Records
	 *
	 * Get the total number of total database table records in the database.
	 *
	 * @param string $table
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $this->load->model('tool/backup');
	 *
	 * $record_total = $this->model_tool_backup->getTotalRecords($table);
	 */
	public function getTotalRecords(string $table): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . $table . "`");

		if ($query->num_rows) {
			return (int)$query->row['total'];
		} else {
			return 0;
		}
	}

	private function getCompleteObjectInventory(): array {
		$query = $this->db->query('SHOW FULL TABLES FROM `' . $this->db->escape(DB_DATABASE) . '`');
		$tables = [];
		$unsupported = [];

		foreach ($query->rows as $row) {
			$values = array_values($row);
			$name = (string)($values[0] ?? '');
			$type = strtoupper((string)($values[1] ?? ''));

			if (!str_starts_with($name, DB_PREFIX)) {
				continue;
			}

			$this->assertIdentifier($name);
			if ($type === 'BASE TABLE') {
				$tables[] = $name;
			} else {
				$unsupported[] = $type . ':' . $name;
			}
		}

		$trigger_query = $this->db->query('SHOW TRIGGERS FROM `' . $this->db->escape(DB_DATABASE) . '`');
		foreach ($trigger_query->rows as $trigger) {
			if (str_starts_with((string)($trigger['Table'] ?? ''), DB_PREFIX)) {
				$unsupported[] = 'TRIGGER:' . (string)($trigger['Trigger'] ?? '');
			}
		}

		foreach (['ROUTINE' => 'ROUTINES', 'EVENT' => 'EVENTS'] as $type => $object) {
			$object_query = $this->db->query("SELECT COUNT(*) AS `total` FROM `information_schema`.`" . $object . "` WHERE `" . $type . "_SCHEMA` = '" . $this->db->escape(DB_DATABASE) . "'");
			if ((int)$object_query->row['total'] > 0) {
				$unsupported[] = $object . ':' . (int)$object_query->row['total'];
			}
		}

		if ($unsupported) {
			throw new \RuntimeException('Complete backup does not support one or more database object types: ' . implode(', ', $unsupported));
		}

		sort($tables, SORT_STRING);
		return ['tables' => $tables, 'views' => [], 'triggers' => [], 'routines' => [], 'events' => []];
	}

	private function getTableColumns(string $table): array {
		$this->assertIdentifier($table);
		$query = $this->db->query('SHOW FULL COLUMNS FROM `' . $this->db->escape($table) . '`');
		$columns = [];
		foreach ($query->rows as $column) {
			$name = (string)($column['Field'] ?? '');
			$this->assertIdentifier($name);
			$columns[] = $name;
		}
		if (!$columns) {
			throw new \RuntimeException('Database table has no columns.');
		}
		return $columns;
	}

	private function getTablePrimaryKey(string $table): array {
		$this->assertIdentifier($table);
		$query = $this->db->query("SHOW INDEX FROM `" . $this->db->escape($table) . "` WHERE `Key_name` = 'PRIMARY'");
		$primary = [];
		foreach ($query->rows as $index) {
			$name = (string)($index['Column_name'] ?? '');
			$this->assertIdentifier($name);
			$primary[(int)$index['Seq_in_index']] = $name;
		}
		ksort($primary, SORT_NUMERIC);
		return array_values($primary);
	}

	private function createSqlBackup(string $file, array $context, array $tables): array {
		$this->assertContext($context);
		$handle = fopen($file, 'xb');
		if (!$handle) {
			throw new \RuntimeException('SQL backup file could not be created.');
		}

		$this->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
		try {
			$inventory = [];
			foreach ($tables as $table) {
				$this->assertIdentifier($table);
				$columns = $this->getTableColumns($table);
				$primary = $this->getTablePrimaryKey($table);
				if (!$primary) {
					throw new \RuntimeException('Complete backup requires a primary key for every table.');
				}
				$rows = (int)$this->db->query('SELECT COUNT(*) AS `total` FROM `' . $this->db->escape($table) . '`')->row['total'];
				$inventory[] = ['table' => $table, 'columns' => $columns, 'primary_key' => $primary, 'rows' => $rows];
			}

			$server = $this->db->query('SELECT VERSION() AS `version`');
			$metadata = [
				'format_version'          => self::FORMAT_VERSION,
				'database'                => DB_DATABASE,
				'db_prefix'               => DB_PREFIX,
				'source_version'          => $context['source_version'],
				'source_database_version' => $context['source_database_version'],
				'target_version'          => $context['target_version'],
				'updates'                 => $context['updates'],
				'server'                  => ['driver' => DB_DRIVER, 'version' => (string)$server->row['version']],
				'created_at'              => gmdate('c'),
				'objects'                 => ['base_tables' => count($inventory), 'views' => 0, 'triggers' => 0, 'routines' => 0, 'events' => 0],
				'tables'                  => $inventory
			];
			$header = '-- OpenCore SQL Backup Format ' . self::FORMAT_VERSION . "\n-- OPENCORE-METADATA " . base64_encode(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) . "\n";
			if (fwrite($handle, $header) !== strlen($header)) {
				throw new \RuntimeException('SQL backup header write failed.');
			}

			$this->writeSqlStatement($handle, 'SET FOREIGN_KEY_CHECKS = 0;');
			foreach ($inventory as $table_data) {
				$table = $table_data['table'];
				$create = (string)($this->db->query('SHOW CREATE TABLE `' . $this->db->escape($table) . '`')->row['Create Table'] ?? '');
				if (!str_starts_with($create, 'CREATE TABLE `' . $table . '`')) {
					throw new \RuntimeException('Database table definition could not be captured.');
				}
				$this->writeSqlStatement($handle, 'DROP TABLE IF EXISTS `' . $table . '`;');
				$this->writeSqlStatement($handle, $create . ';');
				$columns = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $table_data['columns']));
				$order = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $table_data['primary_key']));
				$offset = 0;
				$count = 0;
				do {
					$query = $this->db->query('SELECT * FROM `' . $this->db->escape($table) . '` ORDER BY ' . $order . ' LIMIT ' . $offset . ',' . self::ROW_BATCH_SIZE);
					foreach ($query->rows as $row) {
						$values = [];
						foreach ($table_data['columns'] as $column) {
							$values[] = $row[$column] === null ? 'NULL' : "X'" . bin2hex((string)$row[$column]) . "'";
						}
						$this->writeSqlStatement($handle, 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES (' . implode(', ', $values) . ');');
						$count++;
					}
					$offset += $query->num_rows;
				} while ($query->num_rows === self::ROW_BATCH_SIZE);
				if ($count !== $table_data['rows']) {
					throw new \RuntimeException('Database changed while the SQL backup was being captured.');
				}
			}
			$this->writeSqlStatement($handle, 'SET FOREIGN_KEY_CHECKS = 1;');
			$this->db->query('COMMIT');
			fclose($handle);
			return $metadata;
		} catch (\Throwable $throwable) {
			$this->db->query('ROLLBACK');
			if (is_resource($handle)) {
				fclose($handle);
			}
			throw $throwable;
		}
	}

	private function validateTableMetadata(array $table): string {
		$required = ['table', 'columns', 'primary_key', 'rows'];
		if (array_keys($table) !== $required || !is_string($table['table']) || !str_starts_with($table['table'], DB_PREFIX) || !is_array($table['columns']) || !$table['columns'] || !is_array($table['primary_key']) || !$table['primary_key'] || !is_int($table['rows']) || $table['rows'] < 0) {
			throw new \RuntimeException('Database backup table metadata is invalid.');
		}
		$this->assertIdentifier($table['table']);
		foreach (array_merge($table['columns'], $table['primary_key']) as $column) {
			if (!is_string($column)) throw new \RuntimeException('Database backup column metadata is invalid.');
			$this->assertIdentifier($column);
		}
		if (array_diff($table['primary_key'], $table['columns'])) {
			throw new \RuntimeException('Database backup primary key metadata is invalid.');
		}
		return $table['table'];
	}

	private function writeSqlStatement($handle, string $statement): void {
		$frame = '-- OPENCORE-SQL-STATEMENT ' . strlen($statement) . ' ' . hash('sha256', $statement) . "\n" . $statement . "\n";
		if (fwrite($handle, $frame) !== strlen($frame)) {
			throw new \RuntimeException('SQL backup stream write failed.');
		}
	}

	private function readSqlMetadata(string $file): array {
		$handle = fopen($file, 'rb');
		if (!$handle || trim((string)fgets($handle)) !== '-- OpenCore SQL Backup Format ' . self::FORMAT_VERSION) {
			throw new \RuntimeException('OpenCore SQL backup header is invalid.');
		}
		$line = trim((string)fgets($handle));
		fclose($handle);
		if (!str_starts_with($line, '-- OPENCORE-METADATA ')) {
			throw new \RuntimeException('OpenCore SQL backup metadata is missing.');
		}
		$data = base64_decode(substr($line, 21), true);
		$metadata = is_string($data) ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : null;
		if (!is_array($metadata)) {
			throw new \RuntimeException('OpenCore SQL backup metadata is invalid.');
		}
		return $metadata;
	}

	private function readSqlStatements(string $file, callable $callback): void {
		$handle = fopen($file, 'rb');
		if (!$handle) {
			throw new \RuntimeException('OpenCore SQL backup cannot be read.');
		}
		fgets($handle);
		fgets($handle);
		while (($line = fgets($handle)) !== false) {
			if (!preg_match('/^-- OPENCORE-SQL-STATEMENT ([1-9][0-9]*) ([a-f0-9]{64})\n$/', $line, $matches)) {
				fclose($handle);
				throw new \RuntimeException('OpenCore SQL statement frame is invalid.');
			}
			$remaining = (int)$matches[1];
			$statement = '';
			while ($remaining > 0 && !feof($handle)) {
				$chunk = fread($handle, min(8192, $remaining));
				if ($chunk === false || $chunk === '') {
					break;
				}
				$statement .= $chunk;
				$remaining -= strlen($chunk);
			}
			if ($remaining !== 0 || fread($handle, 1) !== "\n" || !hash_equals($matches[2], hash('sha256', $statement)) || !str_ends_with($statement, ';')) {
				fclose($handle);
				throw new \RuntimeException('OpenCore SQL statement evidence is invalid.');
			}
			$callback($statement);
		}
		fclose($handle);
	}

	private function validateSqlContract(string $file, array $metadata): void {
		$header = $this->readSqlMetadata($file);
		foreach (array_keys($header) as $key) {
			if (!array_key_exists($key, $metadata) || $metadata[$key] !== $header[$key]) {
				throw new \RuntimeException('OpenCore SQL metadata does not match its evidence.');
			}
		}
		$tables = $metadata['tables'] ?? null;
		if (!is_array($tables)) {
			throw new \RuntimeException('OpenCore SQL table inventory is invalid.');
		}
		foreach ($tables as $table) {
			$this->validateTableMetadata($table);
		}
		$stage = 'off';
		$table_index = 0;
		$row_index = 0;
		$this->readSqlStatements($file, function(string $statement) use (&$stage, &$table_index, &$row_index, $tables): void {
			while (true) {
				if ($stage === 'done') {
					throw new \RuntimeException('OpenCore SQL backup contains trailing statements.');
				}
				if ($stage === 'off') {
					if ($statement !== 'SET FOREIGN_KEY_CHECKS = 0;') throw new \RuntimeException('OpenCore SQL prologue is invalid.');
					$stage = 'drop'; return;
				}
				if ($table_index >= count($tables)) {
					if ($statement !== 'SET FOREIGN_KEY_CHECKS = 1;') throw new \RuntimeException('OpenCore SQL epilogue is invalid.');
					$stage = 'done'; return;
				}
				$table = $tables[$table_index];
				$name = $table['table'];
				if ($stage === 'drop') {
					if ($statement !== 'DROP TABLE IF EXISTS `' . $name . '`;') throw new \RuntimeException('OpenCore SQL DROP contract is invalid.');
					$stage = 'create'; return;
				}
				if ($stage === 'create') {
					if (!str_starts_with($statement, 'CREATE TABLE `' . $name . '`') || !str_ends_with($statement, ';')) throw new \RuntimeException('OpenCore SQL CREATE contract is invalid.');
					$stage = 'insert'; $row_index = 0; return;
				}
				if ($row_index < $table['rows']) {
					if (!str_starts_with($statement, 'INSERT INTO `' . $name . '` (')) throw new \RuntimeException('OpenCore SQL INSERT contract is invalid.');
					$row_index++; return;
				}
				$table_index++; $stage = 'drop';
			}
		});
		if ($stage !== 'done') {
			throw new \RuntimeException('OpenCore SQL backup is incomplete.');
		}
	}

	private function assertContext(array $context): void {
		if (array_keys($context) !== ['source_version', 'source_database_version', 'target_version', 'updates'] || !$this->isVersion($context['source_version']) || ($context['source_database_version'] !== null && !$this->isVersion($context['source_database_version'])) || !$this->isVersion($context['target_version']) || !is_array($context['updates'])) {
			throw new \RuntimeException('Database backup release context is invalid.');
		}
		foreach ($context['updates'] as $update) {
			if (!is_string($update)) throw new \RuntimeException('Database backup update identity is invalid.');
		}
	}

	private function isVersion($version): bool {
		return is_string($version) && (bool)preg_match('/^\d{4}\.(?:0[1-9]|1[0-2])\.[1-9]\d*$/', $version);
	}

	private function assertIdentifier(string $identifier): void {
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
			throw new \RuntimeException('Database identifier is invalid.');
		}
	}

	private function assertBackupDirectory(string $directory, bool $must_exist): void {
		$storage = rtrim(str_replace('\\', '/', (string)realpath(DIR_STORAGE)), '/') . '/';
		$normalized = rtrim(str_replace('\\', '/', $directory), '/') . '/';
		if ($storage === '/' || !str_starts_with($normalized, $storage) || str_contains($normalized, '/../') || ($must_exist && (!is_dir($normalized) || is_link(rtrim($normalized, '/')))) || (!$must_exist && file_exists($normalized))) {
			throw new \RuntimeException('Database backup directory boundary is invalid.');
		}
	}

	private function assertManualBackupFile(string $file, bool $must_exist): void {
		$root = rtrim(str_replace('\\', '/', (string)realpath(DIR_STORAGE . 'backup/')), '/') . '/';
		$normalized = str_replace('\\', '/', $file);
		if ($root === '/' || dirname($normalized) . '/' !== $root || strtolower(pathinfo($normalized, PATHINFO_EXTENSION)) !== 'sql' || ($must_exist && (!is_file($normalized) || is_link($normalized))) || (!$must_exist && file_exists($normalized))) {
			throw new \RuntimeException('Manual SQL backup path is invalid.');
		}
	}

	private function writeJsonFile(string $file, array $data): void {
		$temp = $file . '.tmp-' . bin2hex(random_bytes(8));
		$content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
		if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !rename($temp, $file)) {
			@unlink($temp);
			throw new \RuntimeException('Database backup metadata write failed.');
		}
	}

	private function readJsonFile(string $file): array {
		$data = is_file($file) ? json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : null;
		if (!is_array($data)) throw new \RuntimeException('Database backup metadata is invalid.');
		return $data;
	}

	private function removeBackupTree(string $directory): void {
		if (!is_dir($directory)) return;
		$this->assertBackupDirectory($directory, true);
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $entry) {
			if ($entry->isLink()) throw new \RuntimeException('Database backup cleanup encountered a link.');
			$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
		}
		rmdir($directory);
	}
}
