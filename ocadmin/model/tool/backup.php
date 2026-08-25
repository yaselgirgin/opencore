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
			$metadata = $this->createStructuredBackup($temporary, $context, $this->getCompleteObjectInventory()['tables']);
			$this->writeJsonFile($temporary . 'metadata.json', $metadata);
			$evidence = $this->createEvidence($temporary, $metadata);
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

	public function createManualBackup(string $directory, array $tables): array {
		$this->assertBackupDirectory($directory, false);
		$available = $this->getCompleteObjectInventory()['tables'];
		$tables = array_values(array_unique($tables));
		sort($tables, SORT_STRING);

		if (!$tables || array_diff($tables, $available)) {
			throw new \RuntimeException('Manual backup table selection is invalid.');
		}

		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version'");
		$context = ['source_version' => VERSION, 'source_database_version' => $query->num_rows === 1 ? (string)$query->row['value'] : null, 'target_version' => VERSION, 'updates' => []];
		return $this->createCompleteBackupForTables($directory, $context, $tables);
	}

	private function createCompleteBackupForTables(string $directory, array $context, array $tables): array {
		$this->assertBackupDirectory($directory, false);
		$this->assertContext($context);
		$temporary = rtrim($directory, '/\\') . '.tmp-' . bin2hex(random_bytes(8)) . '/';
		if (!mkdir($temporary, 0750, true)) throw new \RuntimeException('Database backup workspace could not be created.');
		try {
			$metadata = $this->createStructuredBackup($temporary, $context, $tables);
			$this->writeJsonFile($temporary . 'metadata.json', $metadata);
			$evidence = $this->createEvidence($temporary, $metadata);
			$this->writeJsonFile($temporary . 'evidence.json', $evidence);
			$this->validateCompleteBackup($temporary, DB_DATABASE);
			if (!rename(rtrim($temporary, '/\\'), rtrim($directory, '/\\'))) throw new \RuntimeException('Verified database backup could not be activated.');
			return $this->validateCompleteBackup($directory, DB_DATABASE);
		} catch (\Throwable $throwable) {
			$this->removeBackupTree($temporary);
			throw $throwable;
		}
	}

	public function validateCompleteBackup(string $directory, string $expected_database): array {
		$this->assertBackupDirectory($directory, true);
		$metadata = $this->readJsonFile(rtrim($directory, '/\\') . '/metadata.json');
		$evidence = $this->readJsonFile(rtrim($directory, '/\\') . '/evidence.json');

		if (($metadata['format_version'] ?? null) !== self::FORMAT_VERSION || ($metadata['database'] ?? '') !== $expected_database || ($metadata['db_prefix'] ?? null) !== DB_PREFIX || ($evidence['format_version'] ?? null) !== self::FORMAT_VERSION || ($evidence['database'] ?? '') !== $expected_database || ($evidence['db_prefix'] ?? null) !== DB_PREFIX || ($evidence['source_version'] ?? null) !== ($metadata['source_version'] ?? null) || ($evidence['source_database_version'] ?? null) !== ($metadata['source_database_version'] ?? null) || ($evidence['target_version'] ?? null) !== ($metadata['target_version'] ?? null) || ($evidence['updates'] ?? null) !== ($metadata['updates'] ?? null) || ($evidence['created_at'] ?? null) !== ($metadata['created_at'] ?? null) || ($evidence['status'] ?? '') !== 'VERIFIED' || ($evidence['tables'] ?? null) !== ($metadata['tables'] ?? null) || !is_array($evidence['components'] ?? null)) {
			throw new \RuntimeException('Database backup identity is invalid.');
		}
		if ($this->getComponentEvidence($directory, $metadata) !== $evidence['components']) throw new \RuntimeException('Database backup component evidence is invalid.');

		return ['metadata' => $metadata, 'evidence' => $evidence, 'evidence_sha256' => hash_file('sha256', rtrim($directory, '/\\') . '/evidence.json')];
	}

	public function restoreCompleteBackup(string $directory, string $expected_database, bool $replace_database = true): array {
		$backup = $this->validateCompleteBackup($directory, $expected_database);
		$metadata = $backup['metadata'];
		$schema = $this->readSchema(rtrim($directory, '/\\') . '/schema.ndjson');
		$current = $this->getCompleteObjectInventory();
		$foreign_key_checks = (int)$this->db->query('SELECT @@FOREIGN_KEY_CHECKS AS `value`')->row['value'];
		$expected_tables = array_column($metadata['tables'], 'table');
		if (array_keys($schema) !== $expected_tables) throw new \RuntimeException('Database backup schema inventory is inconsistent.');

		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');

		try {
			$drop_tables = $replace_database ? $current['tables'] : $expected_tables;
			foreach (array_reverse($drop_tables) as $table) {
				$this->db->query('DROP TABLE IF EXISTS `' . $this->db->escape($table) . '`');
			}

			foreach ($metadata['tables'] as $table) $this->db->query($schema[$this->validateTableMetadata($table)]);
			foreach ($metadata['tables'] as $table) $this->restoreStructuredTable($directory, $table);
		} catch (\Throwable $throwable) {
			throw new \RuntimeException('Database restore failed and may be partial.', 0, $throwable);
		} finally {
			$this->db->query('SET FOREIGN_KEY_CHECKS = ' . $foreign_key_checks);
		}

		$restored = $this->getCompleteObjectInventory();
		if ($replace_database && $restored['tables'] !== $expected_tables) {
			throw new \RuntimeException('Restored database inventory is inconsistent.');
		}

		$expected_version = $metadata['source_database_version'];
		if ($replace_database || in_array(DB_PREFIX . 'setting', $expected_tables, true)) {
			$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version'");
			if (($expected_version === null && $query->num_rows !== 0) || ($expected_version !== null && ($query->num_rows !== 1 || $query->row['value'] !== $expected_version))) {
				throw new \RuntimeException('Restored database version metadata is inconsistent.');
			}
		}
		foreach ($metadata['tables'] as $table) {
			$name = $this->validateTableMetadata($table);
			if ((int)$this->db->query('SELECT COUNT(*) AS `total` FROM `' . $this->db->escape($name) . '`')->row['total'] !== $table['rows']) {
				throw new \RuntimeException('Database restored row count is inconsistent.');
			}
		}

		return ['status' => 'DATABASE_RESTORED', 'database_version' => $expected_version, 'tables' => count($metadata['tables'])];
	}

	public function exportSqlBackup(string $directory, $handle): array {
		$backup = $this->validateCompleteBackup($directory, DB_DATABASE);
		$metadata = $backup['metadata'];
		$schema = $this->readSchema(rtrim($directory, '/\\') . '/schema.ndjson');
		$this->writeExport($handle, "-- OpenCore Database Backup\n-- OpenCore Version: " . $metadata['source_version'] . "\n-- Database Version: " . ($metadata['source_database_version'] ?? 'not-provisioned') . "\n-- Created: " . $metadata['created_at'] . "\n\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
		foreach ($metadata['tables'] as $table) {
			$name = $this->validateTableMetadata($table);
			$this->writeExport($handle, 'DROP TABLE IF EXISTS `' . $name . "`;\n" . $schema[$name] . ";\n");
			$this->exportStructuredRows($directory, $table, $handle);
			$this->writeExport($handle, "\n");
		}
		$this->writeExport($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
		return $metadata;
	}

	public function deleteCompleteBackup(string $directory): void {
		$this->validateCompleteBackup($directory, DB_DATABASE);
		$this->removeBackupTree($directory);
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

	private function createStructuredBackup(string $directory, array $context, array $tables): array {
		$schema = fopen($directory . 'schema.ndjson', 'xb');
		if (!$schema) throw new \RuntimeException('Structured backup schema could not be created.');
		if (!mkdir($directory . 'data/', 0750)) { fclose($schema); throw new \RuntimeException('Structured backup data directory could not be created.'); }
		$inventory = [];
		$this->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
		try {
			foreach ($tables as $table) {
				$columns = $this->getTableColumns($table);
				$primary = $this->getTablePrimaryKey($table);
				if (!$primary) throw new \RuntimeException('Complete backup requires a primary key for every table.');
				$create = (string)($this->db->query('SHOW CREATE TABLE `' . $this->db->escape($table) . '`')->row['Create Table'] ?? '');
				if (!str_starts_with($create, 'CREATE TABLE `' . $table . '`')) throw new \RuntimeException('Database table definition could not be captured.');
				$this->writeJsonLine($schema, ['table' => $table, 'create_base64' => base64_encode($create)]);
				$data_path = 'data/' . $table . '.ndjson';
				$data = fopen($directory . $data_path, 'xb');
				if (!$data) throw new \RuntimeException('Structured table data could not be created.');
				$count = 0;
				$offset = 0;
				$order = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $primary));
				do {
					$query = $this->db->query('SELECT * FROM `' . $this->db->escape($table) . '` ORDER BY ' . $order . ' LIMIT ' . $offset . ',' . self::ROW_BATCH_SIZE);
					foreach ($query->rows as $row) {
						$values = [];
						foreach ($columns as $column) $values[] = $row[$column] === null ? ['type' => 'null'] : ['type' => 'base64', 'value' => base64_encode((string)$row[$column])];
						$this->writeJsonLine($data, $values);
						$count++;
					}
					$offset += $query->num_rows;
				} while ($query->num_rows === self::ROW_BATCH_SIZE);
				fclose($data);
				$inventory[] = ['table' => $table, 'columns' => $columns, 'primary_key' => $primary, 'rows' => $count, 'data' => $data_path];
			}
			$this->db->query('COMMIT');
		} catch (\Throwable $throwable) {
			$this->db->query('ROLLBACK');
			throw $throwable;
		} finally {
			if (is_resource($schema)) fclose($schema);
		}
		$server = $this->db->query('SELECT VERSION() AS `version`');
		return ['format_version' => self::FORMAT_VERSION, 'database' => DB_DATABASE, 'db_prefix' => DB_PREFIX, 'source_version' => $context['source_version'], 'source_database_version' => $context['source_database_version'], 'target_version' => $context['target_version'], 'updates' => $context['updates'], 'server' => ['driver' => DB_DRIVER, 'version' => (string)$server->row['version']], 'created_at' => gmdate('c'), 'objects' => ['base_tables' => count($inventory), 'views' => 0, 'triggers' => 0, 'routines' => 0, 'events' => 0], 'tables' => $inventory];
	}

	private function createEvidence(string $directory, array $metadata): array {
		return ['format_version' => self::FORMAT_VERSION, 'database' => $metadata['database'], 'db_prefix' => $metadata['db_prefix'], 'source_version' => $metadata['source_version'], 'source_database_version' => $metadata['source_database_version'], 'target_version' => $metadata['target_version'], 'updates' => $metadata['updates'], 'created_at' => $metadata['created_at'], 'components' => $this->getComponentEvidence($directory, $metadata), 'tables' => $metadata['tables'], 'status' => 'VERIFIED'];
	}

	private function validateTableMetadata(array $table): string {
		$required = ['table', 'columns', 'primary_key', 'rows', 'data'];
		if (array_keys($table) !== $required || !is_string($table['table']) || !str_starts_with($table['table'], DB_PREFIX) || !is_array($table['columns']) || !$table['columns'] || !is_array($table['primary_key']) || !$table['primary_key'] || !is_int($table['rows']) || $table['rows'] < 0 || $table['data'] !== 'data/' . $table['table'] . '.ndjson') {
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

	private function getComponentEvidence(string $directory, array $metadata): array {
		$directory = rtrim($directory, '/\\') . '/';
		$paths = ['metadata.json', 'schema.ndjson'];
		foreach ($metadata['tables'] as $table) { $this->validateTableMetadata($table); $paths[] = $table['data']; }
		sort($paths, SORT_STRING);
		$components = [];
		foreach ($paths as $path) {
			$file = $directory . $path;
			if (!is_file($file)) throw new \RuntimeException('Structured backup component is missing.');
			$components[] = ['path' => $path, 'size' => filesize($file), 'sha256' => hash_file('sha256', $file)];
		}
		$actual = [];
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
			if ($file->isLink() || !$file->isFile()) throw new \RuntimeException('Structured backup contains an unsupported filesystem entry.');
			$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory)));
			if ($relative !== 'evidence.json') $actual[] = $relative;
		}
		sort($actual, SORT_STRING);
		if ($actual !== $paths) throw new \RuntimeException('Structured backup contains undeclared components.');
		return $components;
	}

	private function readSchema(string $file): array {
		$handle = fopen($file, 'rb');
		if (!$handle) throw new \RuntimeException('Structured schema cannot be read.');
		$schema = [];
		while (($line = fgets($handle)) !== false) {
			$row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
			$table = is_array($row) ? (string)($row['table'] ?? '') : '';
			$create = is_array($row) && is_string($row['create_base64'] ?? null) ? base64_decode($row['create_base64'], true) : false;
			$this->assertIdentifier($table);
			if (isset($schema[$table]) || !is_string($create) || !str_starts_with($create, 'CREATE TABLE `' . $table . '`')) throw new \RuntimeException('Structured schema record is invalid.');
			$schema[$table] = $create;
		}
		fclose($handle);
		return $schema;
	}

	private function readStructuredRows(string $directory, array $table, callable $callback): void {
		$this->validateTableMetadata($table);
		$handle = fopen(rtrim($directory, '/\\') . '/' . $table['data'], 'rb');
		if (!$handle) throw new \RuntimeException('Structured table data cannot be read.');
		$count = 0;
		while (($line = fgets($handle)) !== false) {
			$values = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($values) || count($values) !== count($table['columns'])) throw new \RuntimeException('Structured backup row is invalid.');
			$decoded = [];
			foreach ($values as $value) {
				if (is_array($value) && ($value['type'] ?? '') === 'null' && count($value) === 1) $decoded[] = null;
				elseif (is_array($value) && ($value['type'] ?? '') === 'base64' && count($value) === 2 && is_string($value['value'] ?? null) && base64_decode($value['value'], true) !== false) $decoded[] = base64_decode($value['value'], true);
				else throw new \RuntimeException('Structured backup value encoding is invalid.');
			}
			$callback($decoded);
			$count++;
		}
		fclose($handle);
		if ($count !== $table['rows']) throw new \RuntimeException('Structured backup row count is inconsistent.');
	}

	private function restoreStructuredTable(string $directory, array $table): void {
		$name = $this->validateTableMetadata($table);
		$columns = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $table['columns']));
		$this->readStructuredRows($directory, $table, function(array $values) use ($name, $columns): void {
			$sql = array_map(fn($value): string => $value === null ? 'NULL' : "'" . $this->db->escape($value) . "'", $values);
			$this->db->query('INSERT INTO `' . $this->db->escape($name) . '` (' . $columns . ') VALUES (' . implode(', ', $sql) . ')');
		});
	}

	private function exportStructuredRows(string $directory, array $table, $handle): void {
		$name = $this->validateTableMetadata($table);
		$columns = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $table['columns']));
		$this->readStructuredRows($directory, $table, function(array $values) use ($name, $columns, $handle): void {
			$sql = array_map(static fn($value): string => $value === null ? 'NULL' : "X'" . bin2hex($value) . "'", $values);
			$this->writeExport($handle, 'INSERT INTO `' . $name . '` (' . $columns . ') VALUES (' . implode(', ', $sql) . ");\n");
		});
	}

	private function writeExport($handle, string $content): void {
		if (!is_resource($handle) || fwrite($handle, $content) !== strlen($content)) throw new \RuntimeException('SQL export stream write failed.');
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

	private function writeJsonLine($handle, array $data): void {
		$line = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
		if (fwrite($handle, $line) !== strlen($line)) throw new \RuntimeException('Structured backup stream write failed.');
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
