<?php
namespace Opencart\Admin\Model\Tool;
/**
 * Class Upgrade
 *
 * @package Opencart\Admin\Model\Tool
 */
class Upgrade extends \Opencart\System\Engine\Model {
	private const RELEASES_URL = 'https://api.github.com/repos/yaselgirgin/opencore/releases?per_page=100';
	private const RELEASE_URL_PREFIX = 'https://github.com/yaselgirgin/opencore/releases/tag/';
	private const DOWNLOAD_URL_PREFIX = 'https://github.com/yaselgirgin/opencore/releases/download/';
	private const CONTRACT_VERSION = 1;
	private const PROTECTED_PATHS_VERSION = 1;
	private const MAX_ARTIFACT_SIZE = 536870912;
	private const MAX_MANIFEST_SIZE = 1048576;
	private const MAX_STAGING_SIZE = 1073741824;
	private const DATABASE_UPDATE_HANDLERS = [];
	private const DATABASE_UPDATE_PLANS = [];

	public function getDatabaseVersion(): ?string {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'system' AND `key` = 'database_version'");

		if ($query->num_rows > 1) {
			throw new \RuntimeException('Database version metadata is duplicated.');
		}

		return $query->num_rows ? (string)$query->row['value'] : null;
	}

	public function setDatabaseVersion(string $version, string $source_version): void {
		if ($this->normalizeVersion($version) !== $version || $this->normalizeVersion($source_version) !== $source_version || $this->getDatabaseVersion() !== $source_version) {
			throw new \RuntimeException('Database version metadata precondition failed.');
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($version) . "' WHERE `code` = 'system' AND `key` = 'database_version' AND `value` = '" . $this->db->escape($source_version) . "'");

		if ($this->db->countAffected() !== 1) {
			throw new \RuntimeException('Database version metadata update failed.');
		}
	}

	public function getDatabaseRecoveryPlan(string $source_version): array {
		return $this->resolveDatabaseRecoveryPlan(self::DATABASE_UPDATE_HANDLERS, self::DATABASE_UPDATE_PLANS, $source_version, VERSION);
	}

	private function resolveDatabaseRecoveryPlan(array $handlers, array $plans, string $source_version, string $target_version): array {
		if ($this->normalizeVersion($source_version) !== $source_version || $this->normalizeVersion($target_version) !== $target_version || $this->compareVersions($source_version, $target_version) >= 0) {
			throw new \RuntimeException('Database recovery version boundary is unsupported.');
		}

		foreach ($handlers as $identifier => $route) {
			if (!is_string($identifier) || !is_string($route) || $route === '') {
				throw new \RuntimeException('Database update handler allowlist is invalid.');
			}
		}

		foreach ($plans as $plan_source => $identifiers) {
			if (!is_string($plan_source) || !is_array($identifiers) || !$identifiers || $this->normalizeVersion($plan_source) !== $plan_source) {
				throw new \RuntimeException('Database recovery plan contract is invalid.');
			}

			$validated = $this->validateDatabaseIdentifiers($identifiers, $plan_source, $target_version);

			foreach ($validated as $identifier) {
				if (!array_key_exists($identifier, $handlers)) {
					throw new \RuntimeException('Database recovery plan references an unknown handler.');
				}
			}
		}

		if (!isset($plans[$source_version])) {
			throw new \RuntimeException('Database recovery source version is not supported by this release.');
		}

		return array_values($plans[$source_version]);
	}

	private function createDatabaseBackup(string $root, string $source_version, string $target_version, string $database_version, array $updates): array {
		$this->load->model('tool/backup');

		return $this->model_tool_backup->createCompleteBackup($root . 'backup/database/', [
			'source_version'          => $source_version,
			'source_database_version' => $database_version,
			'target_version'          => $target_version,
			'updates'                 => $updates
		]);
	}

	private function validateDatabaseBackupEvidence(string $root, array $evidence): void {
		$this->load->model('tool/backup');
		$validated = $this->model_tool_backup->validateCompleteBackup($root . 'backup/database/', DB_DATABASE);

		if ($validated !== $evidence) {
			throw new \RuntimeException('Database backup evidence does not match its dump.');
		}
	}

	private function restoreDatabaseBackup(string $root): array {
		$this->load->model('tool/backup');

		return $this->model_tool_backup->restoreCompleteBackup($root . 'backup/database/', DB_DATABASE);
	}

	public function discover(string $current_version): array {
		$releases = $this->requestReleases();

		if ($releases === null) {
			return ['success' => false];
		}

		$latest = null;

		foreach ($releases as $release) {
			if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) {
				continue;
			}

			$version = $this->normalizeVersion((string)($release['tag_name'] ?? ''));

			if (!$version || ($latest && $this->compareVersions($version, $latest['version']) <= 0)) {
				continue;
			}

			$url = (string)($release['html_url'] ?? '');

			if (!str_starts_with($url, self::RELEASE_URL_PREFIX) || filter_var($url, FILTER_VALIDATE_URL) === false) {
				$url = '';
			}

			$latest = [
				'version'      => $version,
				'tag'          => (string)$release['tag_name'],
				'name'         => (string)($release['name'] ?? ''),
				'published_at' => (string)($release['published_at'] ?? ''),
				'url'          => $url
			];
		}

		if (!$latest) {
			return ['success' => true, 'status' => 'NO_RELEASE_AVAILABLE', 'current_version' => $current_version, 'latest_version' => null];
		}

		return [
			'success'         => true,
			'status'          => $this->compareVersions($latest['version'], $current_version) > 0 ? 'UPDATE_AVAILABLE' : 'UP_TO_DATE',
			'current_version' => $current_version,
			'latest_version'  => $latest['version'],
			'release'         => $latest
		];
	}

	public function prepare(string $version, string $current_version): array {
		if ($this->normalizeVersion($version) !== $version || $this->compareVersions($version, $current_version) <= 0) {
			return ['success' => false, 'status' => 'VALIDATION_FAILED'];
		}

		$root = rtrim(DIR_STORAGE, '/\\') . '/updates/' . $version . '/';
		$state_file = $root . 'state/state.json';
		$state = $this->readState($state_file);

		if (($state['status'] ?? '') === 'STAGED' && ($state['target_version'] ?? '') === $version) {
			$staged_artifact = $root . 'download/opencore-' . $version . '.zip';
			$staged_hash = (string)($state['artifact_sha256'] ?? '');

			try {
				if (!preg_match('/^[a-f0-9]{64}$/', $staged_hash) || !is_file($staged_artifact) || !hash_equals($staged_hash, hash_file('sha256', $staged_artifact))) {
					throw new \RuntimeException('Staged artifact identity is invalid.');
				}

				$this->validateStaging($root . 'staging/', $version, $current_version);

				return ['success' => true, 'status' => 'ALREADY_STAGED', 'version' => $version];
			} catch (\Throwable $throwable) {
				$this->log->write('OpenCore staged update revalidation failed for ' . $version . ': ' . $throwable->getMessage());
				return ['success' => false, 'status' => 'VALIDATION_FAILED'];
			}
		}

		$releases = $this->requestReleases();

		if ($releases === null) {
			return ['success' => false, 'status' => 'DOWNLOAD_FAILED'];
		}

		$release = null;

		foreach ($releases as $candidate) {
			if (is_array($candidate) && empty($candidate['draft']) && empty($candidate['prerelease']) && $this->normalizeVersion((string)($candidate['tag_name'] ?? '')) === $version) {
				$release = $candidate;
				break;
			}
		}

		if (!$release) {
			return ['success' => false, 'status' => 'DOWNLOAD_FAILED'];
		}

		$assets = $this->selectAssets($release, $version);

		if (!$assets) {
			$this->log->write('OpenCore update asset selection failed for ' . $version . '.');
			return ['success' => false, 'status' => 'DOWNLOAD_FAILED'];
		}

		try {
			$this->createDirectory($root . 'download/');
			$this->createDirectory($root . 'staging/');
			$this->createDirectory($root . 'state/');

			$artifact = $root . 'download/' . $assets['artifact']['name'];
			$checksum_file = $artifact . '.sha256';

			$this->download((string)$assets['checksum']['url'], $checksum_file, 4096);
			$this->download((string)$assets['artifact']['url'], $artifact, self::MAX_ARTIFACT_SIZE);

			$expected_hash = $this->parseChecksum((string)file_get_contents($checksum_file), $assets['artifact']['name']);

			if (!$expected_hash || !hash_equals($expected_hash, hash_file('sha256', $artifact))) {
				throw new \RuntimeException('Artifact SHA-256 validation failed.');
			}

			$this->extractArchive($artifact, $root . 'staging/');
			$manifest = $this->validateStaging($root . 'staging/', $version, $current_version);

			$this->writeState($state_file, [
				'status'          => 'STAGED',
				'target_version'  => $version,
				'artifact'        => 'download/' . $assets['artifact']['name'],
				'artifact_sha256' => $expected_hash,
				'staging'         => 'staging/',
				'database_update' => (bool)$manifest['database']['required'],
				'validated_at'    => gmdate('c')
			]);

			return ['success' => true, 'status' => 'STAGED', 'version' => $version];
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore update staging failed for ' . $version . ': ' . $throwable->getMessage());

			try {
				$this->writeState($state_file, ['status' => 'VALIDATION_FAILED', 'target_version' => $version, 'failed_at' => gmdate('c')]);
			} catch (\Throwable) {
			}

			return ['success' => false, 'status' => is_file($root . 'download/' . ($assets['artifact']['name'] ?? '')) ? 'VALIDATION_FAILED' : 'DOWNLOAD_FAILED'];
		}
	}

	public function apply(string $version, string $current_version): array {
		if ($this->normalizeVersion($version) !== $version || $this->compareVersions($version, $current_version) <= 0) {
			return ['success' => false, 'status' => 'PREFLIGHT_FAILED'];
		}

		$update_root = rtrim(DIR_STORAGE, '/\\') . '/updates/';
		$root = $update_root . $version . '/';
		$state_file = $root . 'state/state.json';
		$journal_file = $root . 'state/journal.json';
		$lock_file = $update_root . 'apply.lock';
		$state = $this->readState($state_file);

		if (($state['status'] ?? '') === 'STAGED' && is_file($lock_file)) {
			try {
				$this->assertLockOwner($lock_file, $version);
				$this->discardUnstartedApply($root, $version);
				$this->releaseLock($lock_file, $version);
				return ['success' => true, 'status' => 'STAGED', 'version' => $version];
			} catch (\Throwable $throwable) {
				$this->log->write('OpenCore unstarted apply recovery failed for ' . $version . ': ' . $throwable->getMessage());
				return ['success' => false, 'status' => 'RECOVERY_REQUIRED'];
			}
		}

		if (($state['status'] ?? '') !== 'STAGED' || ($state['target_version'] ?? '') !== $version || is_file($lock_file) || $this->findUnresolvedUpdate($update_root)) {
			return ['success' => false, 'status' => 'RECOVERY_REQUIRED'];
		}

		$database_version = null;

		try {
			$this->revalidateStagedState($root, $state, $version, $current_version);
			$manifest = $this->validateStaging($root . 'staging/', $version, $current_version);

			$database_version = $this->getDatabaseVersion();

			if ($database_version !== $current_version) {
				throw new \RuntimeException('Database version metadata does not match the source release.');
			}
			foreach (['backup/', 'vendor-candidate/', 'vendor-swap/', 'vendor-restore/', 'vendor-failed/'] as $workspace) {
				if (file_exists($root . $workspace)) {
					return ['success' => false, 'status' => 'RECOVERY_REQUIRED'];
				}
			}
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore filesystem apply preflight failed for ' . $version . ': ' . $throwable->getMessage());
			return ['success' => false, 'status' => 'PREFLIGHT_FAILED'];
		}

		try {
			$this->acquireLock($lock_file, $version);
			try {
				$journal = $this->createApplyJournal($root, $manifest, $version, $current_version, (string)$database_version);

				if ($manifest['database']['required']) {
					$journal['database']['source_database_version'] = $database_version;
					$journal['database']['backup'] = $this->createDatabaseBackup($root, $current_version, $version, (string)$database_version, $manifest['database']['updates']);
					$journal['database']['status'] = 'DATABASE_BACKUP_VERIFIED';
				}

				$this->writeState($journal_file, $journal);
			} catch (\Throwable $throwable) {
				$this->discardUnstartedApply($root, $version);
				$this->releaseLock($lock_file, $version);
				$this->log->write('OpenCore update backup preparation failed for ' . $version . ': ' . $throwable->getMessage());
				return ['success' => false, 'status' => 'PREFLIGHT_FAILED'];
			}
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore update lock acquisition failed for ' . $version . ': ' . $throwable->getMessage());
			return ['success' => false, 'status' => 'RECOVERY_REQUIRED'];
		}

		try {
			$this->writeState($state_file, ['status' => 'APPLYING', 'target_version' => $version, 'started_at' => gmdate('c')]);

			foreach ($journal['operations'] as $index => $operation) {
				if ($operation['path'] === 'system/version.php') {
					continue;
				}
				$this->assertLivePrecondition($operation);
				$journal['operations'][$index]['status'] = 'MUTATING';
				$this->writeState($journal_file, $journal);
				$this->applyOperation($root, $journal['operations'][$index]);
				$journal['operations'][$index]['status'] = 'COMPLETED';
				$this->writeState($journal_file, $journal);
			}

			if ($journal['vendor']['included']) {
				$this->applyVendor($root, $journal, $journal_file);
			}

			if ($journal['database']['required']) {
				$journal['status'] = 'DATABASE_PENDING';
				$journal['database']['status'] = 'DATABASE_PENDING';
				$journal['database']['pending_at'] = gmdate('c');
				$this->writeState($journal_file, $journal);
				$this->writeState($state_file, [
					'status'                  => 'DATABASE_PENDING',
					'source_version'          => $current_version,
					'source_database_version' => $database_version,
					'target_version'          => $version,
					'updates'                 => $journal['database']['updates'],
					'backup'                  => 'backup/database',
					'backup_sha256'           => $journal['database']['backup']['evidence_sha256'],
					'pending_at'               => gmdate('c')
				]);

				return ['success' => true, 'status' => 'DATABASE_PENDING', 'version' => $version];
			}

			$this->setDatabaseVersion($version, (string)$database_version);
			$journal['database']['status'] = 'METADATA_SYNCHRONIZED';
			$this->writeState($journal_file, $journal);

			foreach ($journal['operations'] as $index => $operation) {
				if ($operation['path'] !== 'system/version.php') {
					continue;
				}
				$this->assertLivePrecondition($operation);
				$journal['operations'][$index]['status'] = 'MUTATING';
				$this->writeState($journal_file, $journal);
				$this->applyOperation($root, $journal['operations'][$index]);
				$journal['operations'][$index]['status'] = 'COMPLETED';
				$this->writeState($journal_file, $journal);
			}

			$this->validateInstalledVersion($version);
			if ($this->getDatabaseVersion() !== $version) {
				throw new \RuntimeException('Database version metadata did not advance to the target release.');
			}
			$journal['database']['status'] = 'METADATA_VERIFIED';
			$journal['status'] = 'APPLIED';
			$journal['completed_at'] = gmdate('c');
			$this->writeState($journal_file, $journal);
			$this->writeState($state_file, ['status' => 'APPLIED', 'target_version' => $version, 'completed_at' => gmdate('c')]);
			$this->releaseLock($lock_file, $version);

			return ['success' => true, 'status' => 'APPLIED', 'version' => $version];
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore filesystem apply failed for ' . $version . ': ' . $throwable->getMessage());
			$this->writeState($state_file, ['status' => 'ROLLBACK_REQUIRED', 'target_version' => $version, 'failed_at' => gmdate('c')]);

			try {
				$this->rollbackJournal($root, $version);
				$this->writeState($state_file, ['status' => 'APPLY_FAILED_ROLLED_BACK', 'target_version' => $version, 'rolled_back_at' => gmdate('c')]);
				$this->releaseLock($lock_file, $version);
				return ['success' => false, 'status' => 'APPLY_FAILED_ROLLED_BACK'];
			} catch (\Throwable $rollback_error) {
				$this->log->write('OpenCore filesystem rollback failed for ' . $version . ': ' . $rollback_error->getMessage());
				$this->writeState($state_file, ['status' => 'ROLLBACK_FAILED', 'target_version' => $version, 'failed_at' => gmdate('c')]);
				return ['success' => false, 'status' => 'ROLLBACK_FAILED'];
			}
		}
	}

	public function continueDatabase(string $version, string $current_version): array {
		if ($this->normalizeVersion($version) !== $version || $this->normalizeVersion($current_version) !== $current_version) {
			return ['success' => false, 'status' => 'DATABASE_RECOVERY_REQUIRED'];
		}

		$update_root = rtrim(DIR_STORAGE, '/\\') . '/updates/';
		$root = $update_root . $version . '/';
		$state_file = $root . 'state/state.json';
		$journal_file = $root . 'state/journal.json';
		$lock_file = $update_root . 'apply.lock';

		if (!is_file($state_file) && !is_file($lock_file)) {
			try {
				if ($version !== $current_version) {
					throw new \RuntimeException('Restore recovery target must be the deployed application version.');
				}

				$database_version = $this->getDatabaseVersion();

				if ($database_version === null) {
					throw new \RuntimeException('Database version metadata is missing.');
				}

				$this->getDatabaseRecoveryPlan($database_version);
				throw new \RuntimeException('Restore recovery requires an explicit backed-up recovery operation.');
			} catch (\Throwable $throwable) {
				$this->log->write('OpenCore restore recovery planning blocked: ' . $throwable->getMessage());

				return ['success' => false, 'status' => 'DATABASE_RECOVERY_REQUIRED'];
			}
		}

		try {
			$state = $this->readState($state_file);
			$journal = $this->readState($journal_file);
			$this->assertLockOwner($lock_file, $version);

			if (($state['status'] ?? '') !== 'DATABASE_PENDING' || ($state['target_version'] ?? '') !== $version || ($state['source_version'] ?? '') !== $current_version || ($journal['status'] ?? '') !== 'DATABASE_PENDING' || ($journal['target_version'] ?? '') !== $version || ($journal['source_version'] ?? '') !== $current_version) {
				throw new \RuntimeException('Database continuation state is inconsistent.');
			}

			$database = $journal['database'] ?? null;

			if (!is_array($database) || ($database['required'] ?? null) !== true || ($database['status'] ?? '') !== 'DATABASE_PENDING' || ($database['source_database_version'] ?? '') !== $current_version || $this->getDatabaseVersion() !== $current_version) {
				throw new \RuntimeException('Database continuation precondition failed.');
			}

			$updates = $this->validateDatabaseIdentifiers($database['updates'] ?? [], $current_version, $version);
			if ($updates !== ($state['updates'] ?? null) || !is_array($database['backup'] ?? null)) {
				throw new \RuntimeException('Database continuation identifiers are inconsistent.');
			}

			$this->validateDatabaseBackupEvidence($root, $database['backup']);
			$metadata = $database['backup']['metadata'] ?? null;
			if (!is_array($metadata) || ($metadata['source_version'] ?? '') !== $current_version || ($metadata['source_database_version'] ?? '') !== $current_version || ($metadata['target_version'] ?? '') !== $version || ($metadata['updates'] ?? null) !== $updates || ($state['backup'] ?? '') !== 'backup/database' || ($state['backup_sha256'] ?? '') !== ($database['backup']['evidence_sha256'] ?? '')) {
				throw new \RuntimeException('Database backup identity does not match the handoff.');
			}
			$this->validateDatabaseHandoff($root, $journal);

			foreach ($updates as $identifier) {
				if (!isset(self::DATABASE_UPDATE_HANDLERS[$identifier])) {
					throw new \RuntimeException('Database update identifier is not allowed by the target release.');
				}
			}

			throw new \RuntimeException('No database update handlers are available in this release.');
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore database continuation blocked for ' . $version . ': ' . $throwable->getMessage());

			try {
				$this->writeState($state_file, ['status' => 'DATABASE_RECOVERY_REQUIRED', 'source_version' => $current_version, 'target_version' => $version, 'failed_at' => gmdate('c')]);
			} catch (\Throwable) {
			}

			return ['success' => false, 'status' => 'DATABASE_RECOVERY_REQUIRED'];
		}
	}

	public function recover(string $version): array {
		if ($this->normalizeVersion($version) !== $version) {
			return ['success' => false, 'status' => 'RECOVERY_FAILED'];
		}

		$update_root = rtrim(DIR_STORAGE, '/\\') . '/updates/';
		$root = $update_root . $version . '/';
		$state_file = $root . 'state/state.json';
		$lock_file = $update_root . 'apply.lock';
		$state = $this->readState($state_file);

		if (in_array($state['status'] ?? '', ['DATABASE_PENDING', 'DATABASE_APPLYING', 'DATABASE_RECOVERY_REQUIRED', 'DATABASE_RESTORE_REQUIRED', 'DATABASE_RESTORING', 'DATABASE_RESTORE_FAILED', 'DATABASE_RESTORED'], true)) {
			try {
				$this->assertLockOwner($lock_file, $version);

				if (($state['status'] ?? '') === 'DATABASE_PENDING') {
					$journal = $this->readState($root . 'state/journal.json');
					if (!is_array($journal['database']['backup'] ?? null)) {
						throw new \RuntimeException('Database backup evidence is missing.');
					}
					$this->validateDatabaseBackupEvidence($root, $journal['database']['backup']);
					if (($state['source_version'] ?? '') !== ($journal['source_version'] ?? '') || ($state['source_database_version'] ?? '') !== ($journal['database']['source_database_version'] ?? '') || ($state['updates'] ?? null) !== ($journal['database']['updates'] ?? null) || ($state['backup'] ?? '') !== 'backup/database' || ($state['backup_sha256'] ?? '') !== ($journal['database']['backup']['evidence_sha256'] ?? '')) {
						throw new \RuntimeException('Database recovery evidence does not match the handoff.');
					}
					$this->validateDatabaseHandoff($root, $journal);

					return ['success' => true, 'status' => 'DATABASE_PENDING', 'version' => $version];
				}

				if (($state['status'] ?? '') === 'DATABASE_RESTORE_REQUIRED') {
					$journal = $this->readState($root . 'state/journal.json');
					if (!is_array($journal['database']['backup'] ?? null)) {
						throw new \RuntimeException('Database restore evidence is missing.');
					}
					$this->validateDatabaseBackupEvidence($root, $journal['database']['backup']);
					$this->writeState($state_file, ['status' => 'DATABASE_RESTORING', 'source_version' => $journal['source_version'], 'target_version' => $version, 'started_at' => gmdate('c')]);
					try {
						$restored = $this->restoreDatabaseBackup($root);
					} catch (\Throwable $throwable) {
						$this->writeState($state_file, ['status' => 'DATABASE_RESTORE_FAILED', 'source_version' => $journal['source_version'], 'target_version' => $version, 'failed_at' => gmdate('c')]);
						$this->log->write('OpenCore database restore failed for ' . $version . ': ' . $throwable->getMessage());

						return ['success' => false, 'status' => 'DATABASE_RESTORE_FAILED'];
					}
					$this->writeState($state_file, ['status' => 'DATABASE_RESTORED', 'source_version' => $journal['source_version'], 'target_version' => $version, 'database_version' => $restored['database_version'], 'restored_at' => gmdate('c')]);

					return ['success' => true, 'status' => 'DATABASE_RESTORED', 'version' => $version];
				}

				return ['success' => false, 'status' => $state['status']];
			} catch (\Throwable $throwable) {
				$this->log->write('OpenCore database recovery evidence failed for ' . $version . ': ' . $throwable->getMessage());
				$this->writeState($state_file, ['status' => 'DATABASE_RECOVERY_REQUIRED', 'target_version' => $version, 'failed_at' => gmdate('c')]);

				return ['success' => false, 'status' => 'DATABASE_RECOVERY_REQUIRED'];
			}
		}

		if (($state['status'] ?? '') === 'STAGED' && ($state['target_version'] ?? '') === $version && is_file($lock_file)) {
			try {
				$this->assertLockOwner($lock_file, $version);
				$this->discardUnstartedApply($root, $version);
				$this->releaseLock($lock_file, $version);
				return ['success' => true, 'status' => 'STAGED', 'version' => $version];
			} catch (\Throwable $throwable) {
				$this->log->write('OpenCore unstarted apply recovery failed for ' . $version . ': ' . $throwable->getMessage());
				return ['success' => false, 'status' => 'RECOVERY_FAILED'];
			}
		}

		if (in_array($state['status'] ?? '', ['APPLIED', 'APPLY_FAILED_ROLLED_BACK'], true) && is_file($lock_file)) {
			try {
				$this->assertLockOwner($lock_file, $version);
				$this->validateJournalOutcome($root, $version, $state['status']);
				$this->releaseLock($lock_file, $version);
				return ['success' => true, 'status' => $state['status']];
			} catch (\Throwable $throwable) {
				$this->log->write('OpenCore completed update reconciliation failed for ' . $version . ': ' . $throwable->getMessage());
				return ['success' => false, 'status' => 'RECOVERY_FAILED'];
			}
		}

		if (!in_array($state['status'] ?? '', ['APPLYING', 'ROLLBACK_REQUIRED', 'ROLLBACK_FAILED'], true)) {
			return ['success' => false, 'status' => 'RECOVERY_NOT_REQUIRED'];
		}

		try {
			if (is_file($lock_file)) {
				$this->assertLockOwner($lock_file, $version);
			} else {
				$this->acquireLock($lock_file, $version);
			}

			$this->rollbackJournal($root, $version);
			$this->writeState($state_file, ['status' => 'APPLY_FAILED_ROLLED_BACK', 'target_version' => $version, 'rolled_back_at' => gmdate('c')]);
			$this->releaseLock($lock_file, $version);

			return ['success' => true, 'status' => 'APPLY_FAILED_ROLLED_BACK'];
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore filesystem recovery failed for ' . $version . ': ' . $throwable->getMessage());
			$this->writeState($state_file, ['status' => 'ROLLBACK_FAILED', 'target_version' => $version, 'failed_at' => gmdate('c')]);
			return ['success' => false, 'status' => 'ROLLBACK_FAILED'];
		}
	}

	private function revalidateStagedState(string $root, array $state, string $version, string $current_version): void {
		$artifact = $root . 'download/opencore-' . $version . '.zip';
		$hash = (string)($state['artifact_sha256'] ?? '');

		if (!preg_match('/^[a-f0-9]{64}$/', $hash) || !is_file($artifact) || !hash_equals($hash, hash_file('sha256', $artifact))) {
			throw new \RuntimeException('Staged artifact identity is invalid.');
		}

		$this->validateStaging($root . 'staging/', $version, $current_version);
	}

	private function createApplyJournal(string $root, array $manifest, string $version, string $current_version, string $source_database_version): array {
		$backup_root = $root . 'backup/';
		if (file_exists($backup_root)) {
			throw new \RuntimeException('An apply backup already exists.');
		}
		$this->createDirectory($backup_root . 'files/');

		$operations = [];
		$owned = [];
		$version_owned = false;

		foreach ($manifest['application_files'] as $entry) {
			$path = $this->normalizePath((string)$entry['path']);
			if ($this->isProtectedPath($path)) {
				throw new \RuntimeException('Protected application path in apply inventory.');
			}
			$live = $this->resolveLivePath($path);
			$exists = is_file($live);
			if (file_exists($live) && !$exists) {
				throw new \RuntimeException('Application destination is not a regular file.');
			}
			$backup = null;
			$old_hash = $exists ? hash_file('sha256', $live) : null;
			if ($exists) {
				$backup = 'backup/files/' . $path;
				$this->copyFileVerified($live, $root . $backup, $old_hash);
			}
			$operations[] = [
				'path'       => $path,
				'operation'  => $exists ? 'REPLACE' : 'ADD',
				'old_exists' => $exists,
				'old_hash'   => $old_hash,
				'new_hash'   => (string)$entry['sha256'],
				'backup'     => $backup,
				'swap'       => '.opencore-update-old-' . hash('sha256', $version . ':' . $path),
				'status'     => 'PENDING'
			];
			$owned[strtolower($path)] = true;
			$version_owned = $version_owned || strtolower($path) === 'system/version.php';
		}

		if (!$version_owned) {
			throw new \RuntimeException('Canonical version file is missing from the release inventory.');
		}

		foreach ($manifest['application_removals'] as $removal) {
			$path = $this->normalizePath((string)$removal);
			if ($this->isProtectedPath($path) || isset($owned[strtolower($path)])) {
				throw new \RuntimeException('Invalid application removal path.');
			}
			$live = $this->resolveLivePath($path);
			$exists = is_file($live);
			if (file_exists($live) && !$exists) {
				throw new \RuntimeException('Application removal target is not a regular file.');
			}
			$backup = null;
			$old_hash = $exists ? hash_file('sha256', $live) : null;
			if ($exists) {
				$backup = 'backup/files/' . $path;
				$this->copyFileVerified($live, $root . $backup, $old_hash);
			}
			$operations[] = ['path' => $path, 'operation' => 'REMOVE', 'old_exists' => $exists, 'old_hash' => $old_hash, 'new_hash' => null, 'backup' => $backup, 'swap' => null, 'status' => 'PENDING'];
		}

		usort($operations, static fn(array $first, array $second): int => ($first['path'] === 'system/version.php' ? 1 : 0) <=> ($second['path'] === 'system/version.php' ? 1 : 0));

		$vendor = $this->createVendorJournal($root, $manifest['vendor']);

		$database = [
			'required'                => (bool)$manifest['database']['required'],
			'updates'                 => $manifest['database']['updates'],
			'source_database_version' => $source_database_version,
			'backup'                  => null,
			'status'                  => $manifest['database']['required'] ? 'DATABASE_BACKUP_PENDING' : 'METADATA_PENDING',
			'handlers'                => []
		];

		return ['status' => 'PLANNED', 'target_version' => $version, 'source_version' => $current_version, 'created_at' => gmdate('c'), 'operations' => $operations, 'vendor' => $vendor, 'database' => $database];
	}

	private function createVendorJournal(string $root, array $manifest_vendor): array {
		if (!$manifest_vendor['included']) {
			return ['included' => false, 'status' => 'NOT_INCLUDED'];
		}

		$live = $this->getLiveVendorRoot();
		$candidate = $root . 'vendor-candidate/';
		$backup = $root . 'backup/vendor/';
		$swap = $root . 'vendor-swap/';
		if (file_exists($candidate) || file_exists($backup) || file_exists($swap)) {
			throw new \RuntimeException('Vendor apply workspace already exists.');
		}

		$this->copyVendorInventory($root . 'staging/payload/vendor/', $candidate, $manifest_vendor['files']);
		$new_identity = $this->getTreeIdentity($candidate);
		$live_exists = is_dir($live);
		if (file_exists($live) && !$live_exists) {
			throw new \RuntimeException('Live vendor root is not a directory.');
		}

		$old_identity = null;
		if ($live_exists) {
			$old_identity = $this->getTreeIdentity($live);
			$this->copyTreeVerified($live, $backup, $old_identity);
		}

		return [
			'included'     => true,
			'identity'     => (string)$manifest_vendor['identity'],
			'live_existed' => $live_exists,
			'old_identity' => $old_identity,
			'new_identity' => $new_identity,
			'backup'       => 'backup/vendor',
			'candidate'    => 'vendor-candidate',
			'swap'         => 'vendor-swap',
			'status'       => 'VENDOR_PENDING'
		];
	}

	private function assertLivePrecondition(array $operation): void {
		$live = $this->resolveLivePath($this->validateJournalOperation($operation));
		$exists = is_file($live);
		if ($exists !== $operation['old_exists'] || ($exists && !hash_equals((string)$operation['old_hash'], hash_file('sha256', $live)))) {
			throw new \RuntimeException('Live file changed after backup preflight.');
		}
	}

	private function applyOperation(string $root, array $operation): void {
		$path = $this->validateJournalOperation($operation);
		$live = $this->resolveLivePath($path);

		if ($operation['operation'] === 'REMOVE') {
			if ($operation['old_exists'] && !unlink($live)) {
				throw new \RuntimeException('Application file removal failed.');
			}
			return;
		}

		$source = $root . 'staging/payload/application/' . $path;
		if (!is_file($source) || !hash_equals((string)$operation['new_hash'], hash_file('sha256', $source))) {
			throw new \RuntimeException('Staged application source changed.');
		}

		$this->createSafeParentDirectory(dirname($live));
		$temp = $live . '.opencore-update-' . bin2hex(random_bytes(8)) . '.tmp';
		$this->copyFileVerified($source, $temp, (string)$operation['new_hash']);

		if ($operation['operation'] === 'ADD') {
			if (file_exists($live) || !rename($temp, $live)) {
				@unlink($temp);
				throw new \RuntimeException('Application file add failed.');
			}
		} else {
			$swap = dirname($live) . '/' . $operation['swap'];
			if (file_exists($swap) || !rename($live, $swap)) {
				@unlink($temp);
				throw new \RuntimeException('Application file replacement could not preserve the prior file.');
			}
			if (!rename($temp, $live)) {
				rename($swap, $live);
				@unlink($temp);
				throw new \RuntimeException('Application file replacement failed.');
			}
			if (!unlink($swap)) {
				throw new \RuntimeException('Application replacement swap cleanup failed.');
			}
		}

		if (!hash_equals((string)$operation['new_hash'], hash_file('sha256', $live))) {
			throw new \RuntimeException('Applied application file hash mismatch.');
		}
	}

	private function applyVendor(string $root, array &$journal, string $journal_file): void {
		$vendor = $this->validateVendorJournal($journal['vendor']);
		$live = $this->getLiveVendorRoot();
		$candidate = $root . $vendor['candidate'];
		$swap = $root . $vendor['swap'];

		$this->assertVendorIdentity($candidate, $vendor['new_identity']);
		if ($vendor['live_existed']) {
			$this->assertVendorIdentity($live, $vendor['old_identity']);
		} elseif (file_exists($live)) {
			throw new \RuntimeException('Live vendor appeared after backup preflight.');
		}

		$journal['vendor']['status'] = 'VENDOR_BACKED_UP';
		$this->writeState($journal_file, $journal);
		if ($vendor['live_existed']) {
			if (file_exists($swap) || !@rename(rtrim($live, '/\\'), rtrim($swap, '/\\'))) {
				throw new \RuntimeException('Live vendor could not be preserved for swap.');
			}
		}

		$journal['vendor']['status'] = 'VENDOR_LIVE_MOVED';
		$this->writeState($journal_file, $journal);
		if (!@rename(rtrim($candidate, '/\\'), rtrim($live, '/\\'))) {
			if ($vendor['live_existed']) {
				@rename(rtrim($swap, '/\\'), rtrim($live, '/\\'));
			}
			throw new \RuntimeException('Vendor candidate activation failed.');
		}

		$journal['vendor']['status'] = 'VENDOR_SWAPPED';
		$this->writeState($journal_file, $journal);
		$this->assertVendorIdentity($live, $vendor['new_identity']);
		$this->verifyVendorRuntime($live);
		$journal['vendor']['status'] = 'VENDOR_VERIFIED';
		$this->writeState($journal_file, $journal);

		if (is_dir($swap)) {
			$this->removeVendorTree($swap, $root);
		}
	}

	private function verifyVendorRuntime(string $vendor_root): void {
		$autoload = rtrim($vendor_root, '/\\') . '/autoload.php';
		$installed_php = rtrim($vendor_root, '/\\') . '/composer/installed.php';
		$installed_json = rtrim($vendor_root, '/\\') . '/composer/installed.json';
		if (!is_file($autoload) || (!is_file($installed_php) && !is_file($installed_json))) {
			throw new \RuntimeException('Vendor bootstrap or Composer metadata is missing.');
		}

		$metadata = is_file($installed_json) ? json_decode((string)file_get_contents($installed_json), true) : require $installed_php;
		$loader = require $autoload;

		$twig = $loader instanceof \Composer\Autoload\ClassLoader ? $loader->findFile('Twig\\Environment') : false;
		$twig_real = is_string($twig) ? realpath($twig) : false;
		$expected_twig_real = realpath(rtrim($vendor_root, '/\\') . '/twig/twig/src/Environment.php');

		if (!is_array($metadata) || !$loader instanceof \Composer\Autoload\ClassLoader || !is_file(rtrim($vendor_root, '/\\') . '/composer/InstalledVersions.php') || $twig_real === false || $expected_twig_real === false || str_replace('\\', '/', $twig_real) !== str_replace('\\', '/', $expected_twig_real)) {
			throw new \RuntimeException('Vendor runtime probe failed.');
		}
	}

	private function rollbackJournal(string $root, string $version): void {
		$journal_file = $root . 'state/journal.json';
		$journal = $this->readState($journal_file);
		if (($journal['target_version'] ?? '') !== $version || !isset($journal['operations']) || !is_array($journal['operations'])) {
			throw new \RuntimeException('Apply journal is missing or invalid.');
		}

		if (($journal['vendor']['included'] ?? false) === true) {
			try {
				$this->rollbackVendor($root, $journal['vendor']);
				$journal['vendor']['status'] = 'VENDOR_ROLLED_BACK';
				$this->writeState($journal_file, $journal);
			} catch (\Throwable $throwable) {
				$journal['vendor']['status'] = 'VENDOR_ROLLBACK_FAILED';
				$this->writeState($journal_file, $journal);
				throw $throwable;
			}
		}

		for ($index = count($journal['operations']) - 1; $index >= 0; $index--) {
			$operation = $journal['operations'][$index];
			if (!in_array($operation['status'] ?? '', ['COMPLETED', 'MUTATING', 'ROLLED_BACK'], true)) {
				continue;
			}
			$this->rollbackOperation($root, $operation);
			$journal['operations'][$index]['status'] = 'ROLLED_BACK';
			$this->writeState($journal_file, $journal);
		}

		if (($journal['database']['required'] ?? null) === false) {
			$source_database_version = (string)($journal['database']['source_database_version'] ?? '');
			if ($this->normalizeVersion($source_database_version) !== $source_database_version) {
				throw new \RuntimeException('Rollback database version metadata is invalid.');
			}

			$database_version = $this->getDatabaseVersion();
			if ($database_version === $version) {
				$this->setDatabaseVersion($source_database_version, $version);
			} elseif ($database_version !== $source_database_version) {
				throw new \RuntimeException('Rollback database version metadata is inconsistent.');
			}

			$journal['database']['status'] = 'METADATA_ROLLED_BACK';
			$this->writeState($journal_file, $journal);
		}

		$journal['status'] = 'ROLLED_BACK';
		$journal['rolled_back_at'] = gmdate('c');
		$this->writeState($journal_file, $journal);
	}

	private function rollbackVendor(string $root, array $vendor): void {
		$vendor = $this->validateVendorJournal($vendor);
		$live = $this->getLiveVendorRoot();
		$backup = $root . $vendor['backup'];
		$swap = $root . $vendor['swap'];
		$candidate = $root . $vendor['candidate'];

		if ($vendor['live_existed']) {
			$this->assertVendorIdentity($backup, $vendor['old_identity']);
			if (is_dir($live) && $this->treeIdentityMatches($live, $vendor['old_identity'])) {
				$this->cleanupVendorTransients($candidate, $swap, $root);
				return;
			}
			if (is_dir($swap) && $this->treeIdentityMatches($swap, $vendor['old_identity']) && !file_exists($live)) {
				if (!@rename(rtrim($swap, '/\\'), rtrim($live, '/\\'))) {
					throw new \RuntimeException('Interrupted vendor swap restoration failed.');
				}
				$this->assertVendorIdentity($live, $vendor['old_identity']);
				$this->cleanupVendorTransients($candidate, $swap, $root);
				return;
			}

			$restore = $root . 'vendor-restore/';
			if (file_exists($restore)) {
				throw new \RuntimeException('Vendor restore candidate already exists.');
			}
			$this->copyTreeVerified($backup, $restore, $vendor['old_identity']);
			$failed = $root . 'vendor-failed/';
			if (file_exists($failed)) {
				throw new \RuntimeException('Vendor failed-tree evidence already exists.');
			}
			if (is_dir($live) && !@rename(rtrim($live, '/\\'), rtrim($failed, '/\\'))) {
				$this->removeVendorTree($restore, $root);
				throw new \RuntimeException('Failed vendor could not be preserved for rollback.');
			}
			if (!@rename(rtrim($restore, '/\\'), rtrim($live, '/\\'))) {
				if (is_dir($failed)) {
					@rename(rtrim($failed, '/\\'), rtrim($live, '/\\'));
				}
				if (is_dir($restore)) {
					$this->removeVendorTree($restore, $root);
				}
				throw new \RuntimeException('Vendor rollback activation failed.');
			}
			$this->assertVendorIdentity($live, $vendor['old_identity']);
			if (is_dir($failed)) {
				$this->removeVendorTree($failed, $root);
			}
		} elseif (is_dir($live)) {
			if (!$this->treeIdentityMatches($live, $vendor['new_identity'])) {
				throw new \RuntimeException('Unowned live vendor blocks rollback.');
			}
			$this->removeVendorTree($live, rtrim(DIR_STORAGE, '/\\') . '/');
		} elseif (file_exists($live)) {
			throw new \RuntimeException('Vendor rollback destination is unsafe.');
		}

		$this->cleanupVendorTransients($candidate, $swap, $root);
	}

	private function rollbackOperation(string $root, array $operation): void {
		$path = $this->validateJournalOperation($operation);
		$live = $this->resolveLivePath($path);

		if (!$operation['old_exists']) {
			if (is_file($live)) {
				if (!$operation['new_hash'] || !hash_equals((string)$operation['new_hash'], hash_file('sha256', $live)) || !unlink($live)) {
					throw new \RuntimeException('Rollback cannot remove the added file safely.');
				}
			}
			return;
		}

		$backup = $root . (string)$operation['backup'];
		if (!is_file($backup) || !hash_equals((string)$operation['old_hash'], hash_file('sha256', $backup))) {
			throw new \RuntimeException('Rollback backup is missing or corrupt.');
		}

		if (is_file($live) && hash_equals((string)$operation['old_hash'], hash_file('sha256', $live))) {
			$this->cleanupRollbackSwap($live, $operation);
			return;
		}
		if (file_exists($live) && !is_file($live)) {
			throw new \RuntimeException('Rollback destination is unsafe.');
		}
		if (is_file($live) && $operation['new_hash'] && !hash_equals((string)$operation['new_hash'], hash_file('sha256', $live))) {
			throw new \RuntimeException('Rollback destination has an unexpected hash.');
		}

		$this->createSafeParentDirectory(dirname($live));
		$temp = $live . '.opencore-rollback-' . bin2hex(random_bytes(8)) . '.tmp';
		$this->copyFileVerified($backup, $temp, (string)$operation['old_hash']);
		if (is_file($live) && !unlink($live)) {
			@unlink($temp);
			throw new \RuntimeException('Rollback destination replacement failed.');
		}
		if (!rename($temp, $live)) {
			@unlink($temp);
			throw new \RuntimeException('Rollback restore failed.');
		}

		$this->cleanupRollbackSwap($live, $operation);
	}

	private function cleanupRollbackSwap(string $live, array $operation): void {
		if ($operation['swap']) {
			$swap = dirname($live) . '/' . $operation['swap'];
			if (is_file($swap) && (!hash_equals((string)$operation['old_hash'], hash_file('sha256', $swap)) || !unlink($swap))) {
				throw new \RuntimeException('Rollback swap cleanup failed.');
			}
		}
	}

	private function validateJournalOutcome(string $root, string $version, string $state): void {
		$journal = $this->readState($root . 'state/journal.json');
		$expected_journal = $state === 'APPLIED' ? 'APPLIED' : 'ROLLED_BACK';
		if (($journal['target_version'] ?? '') !== $version || ($journal['status'] ?? '') !== $expected_journal || !isset($journal['operations']) || !is_array($journal['operations'])) {
			throw new \RuntimeException('Completed update journal is inconsistent.');
		}
		foreach ($journal['operations'] as $operation) {
			$path = $this->validateJournalOperation($operation);
			$live = $this->resolveLivePath($path);
			if ($state === 'APPLIED') {
				if ($operation['operation'] === 'REMOVE') {
					if (file_exists($live)) throw new \RuntimeException('Applied removal evidence is inconsistent.');
				} elseif (!is_file($live) || !hash_equals((string)$operation['new_hash'], hash_file('sha256', $live))) {
					throw new \RuntimeException('Applied file evidence is inconsistent.');
				}
			} elseif ($operation['old_exists']) {
				if (!is_file($live) || !hash_equals((string)$operation['old_hash'], hash_file('sha256', $live))) throw new \RuntimeException('Rollback evidence is inconsistent.');
			} elseif (file_exists($live)) {
				throw new \RuntimeException('Rollback add evidence is inconsistent.');
			}
		}
		if (($journal['vendor']['included'] ?? false) === true) {
			$vendor = $this->validateVendorJournal($journal['vendor']);
			$expected_vendor = $state === 'APPLIED' ? 'VENDOR_VERIFIED' : 'VENDOR_ROLLED_BACK';
			if ($vendor['status'] !== $expected_vendor) {
				throw new \RuntimeException('Completed vendor journal is inconsistent.');
			}
			$identity = $state === 'APPLIED' ? $vendor['new_identity'] : $vendor['old_identity'];
			if ($state === 'APPLIED' || $vendor['live_existed']) {
				$this->assertVendorIdentity($this->getLiveVendorRoot(), $identity);
			} elseif (file_exists($this->getLiveVendorRoot())) {
				throw new \RuntimeException('Rolled-back vendor evidence is inconsistent.');
			}
		}

		if (($journal['database']['required'] ?? null) === false) {
			$expected_database_version = $state === 'APPLIED' ? $version : (string)($journal['database']['source_database_version'] ?? '');
			$expected_database_status = $state === 'APPLIED' ? 'METADATA_VERIFIED' : 'METADATA_ROLLED_BACK';
			if (($journal['database']['status'] ?? '') !== $expected_database_status || $this->getDatabaseVersion() !== $expected_database_version) {
				throw new \RuntimeException('Completed database version metadata is inconsistent.');
			}
		}
	}

	private function validateDatabaseHandoff(string $root, array $journal): void {
		if (($journal['status'] ?? '') !== 'DATABASE_PENDING' || !isset($journal['operations'], $journal['vendor']) || !is_array($journal['operations']) || !is_array($journal['vendor'])) {
			throw new \RuntimeException('Database handoff journal is invalid.');
		}

		foreach ($journal['operations'] as $operation) {
			$path = $this->validateJournalOperation($operation);
			$live = $this->resolveLivePath($path);

			if ($path === 'system/version.php') {
				if (($operation['status'] ?? '') !== 'PENDING' || !$operation['old_exists'] || !is_file($live) || !hash_equals((string)$operation['old_hash'], hash_file('sha256', $live))) {
					throw new \RuntimeException('Canonical version file advanced before database completion.');
				}
			} elseif (($operation['status'] ?? '') !== 'COMPLETED') {
				throw new \RuntimeException('Target application handoff is incomplete.');
			} elseif ($operation['operation'] === 'REMOVE') {
				if (file_exists($live)) {
					throw new \RuntimeException('Target application removal is incomplete.');
				}
			} elseif (!is_file($live) || !hash_equals((string)$operation['new_hash'], hash_file('sha256', $live))) {
				throw new \RuntimeException('Target application file identity is invalid.');
			}
		}

		if (($journal['vendor']['included'] ?? false) === true) {
			$vendor = $this->validateVendorJournal($journal['vendor']);
			if ($vendor['status'] !== 'VENDOR_VERIFIED') {
				throw new \RuntimeException('Target vendor handoff is incomplete.');
			}
			$this->assertVendorIdentity($this->getLiveVendorRoot(), $vendor['new_identity']);
		}
	}

	private function discardUnstartedApply(string $root, string $version): void {
		$journal_file = $root . 'state/journal.json';
		if (is_file($journal_file)) {
			$journal = $this->readState($journal_file);
			if (($journal['target_version'] ?? '') !== $version || ($journal['status'] ?? '') !== 'PLANNED' || !isset($journal['operations'], $journal['vendor']) || !is_array($journal['operations']) || !is_array($journal['vendor'])) {
				throw new \RuntimeException('Unstarted apply journal is inconsistent.');
			}
			foreach ($journal['operations'] as $operation) {
				if (($operation['status'] ?? '') !== 'PENDING') {
					throw new \RuntimeException('Unstarted apply contains a mutation marker.');
				}
				$this->assertLivePrecondition($operation);
			}
			if (($journal['vendor']['included'] ?? false) === true) {
				$vendor = $this->validateVendorJournal($journal['vendor']);
				if ($vendor['status'] !== 'VENDOR_PENDING') {
					throw new \RuntimeException('Unstarted vendor apply contains a mutation marker.');
				}
				if ($vendor['live_existed']) {
					$this->assertVendorIdentity($this->getLiveVendorRoot(), $vendor['old_identity']);
				} elseif (file_exists($this->getLiveVendorRoot())) {
					throw new \RuntimeException('Live vendor changed after backup preflight.');
				}
			}
		}

		$this->removeUpdaterDirectory($root . 'backup/', $root);
		$this->removeUpdaterDirectory($root . 'vendor-candidate/', $root);
		$this->removeUpdaterDirectory($root . 'vendor-swap/', $root);
		$this->removeUpdaterDirectory($root . 'vendor-restore/', $root);
		$this->removeUpdaterDirectory($root . 'vendor-failed/', $root);
		foreach (array_merge([$journal_file, $journal_file . '.previous'], glob($journal_file . '.tmp-*') ?: []) as $file) {
			if (is_file($file) && !unlink($file)) {
				throw new \RuntimeException('Unstarted apply journal cleanup failed.');
			}
		}
	}

	private function validateVendorJournal(array $vendor): array {
		$required = ['included', 'identity', 'live_existed', 'old_identity', 'new_identity', 'backup', 'candidate', 'swap', 'status'];
		foreach ($required as $key) {
			if (!array_key_exists($key, $vendor)) {
				throw new \RuntimeException('Vendor journal is incomplete.');
			}
		}
		if ($vendor['included'] !== true || !is_bool($vendor['live_existed']) || !is_string($vendor['identity']) || $vendor['identity'] === '' || !preg_match('/^[a-f0-9]{64}$/', (string)$vendor['new_identity'])) {
			throw new \RuntimeException('Vendor journal is invalid.');
		}
		if ($vendor['live_existed'] && !preg_match('/^[a-f0-9]{64}$/', (string)$vendor['old_identity'])) {
			throw new \RuntimeException('Vendor prior identity is invalid.');
		}
		if (!$vendor['live_existed'] && $vendor['old_identity'] !== null) {
			throw new \RuntimeException('Vendor prior identity is inconsistent.');
		}
		foreach (['backup' => 'backup/vendor', 'candidate' => 'vendor-candidate', 'swap' => 'vendor-swap'] as $key => $expected) {
			if ($vendor[$key] !== $expected) {
				throw new \RuntimeException('Vendor journal path is invalid.');
			}
		}
		return $vendor;
	}

	private function getLiveVendorRoot(): string {
		$storage = rtrim(str_replace('\\', '/', (string)realpath(DIR_STORAGE)), '/') . '/';
		if ($storage === '/' || !is_dir($storage)) {
			throw new \RuntimeException('Runtime storage root is invalid.');
		}
		return rtrim(DIR_STORAGE, '/\\') . '/vendor/';
	}

	private function copyVendorInventory(string $source_root, string $destination_root, array $inventory): void {
		$this->createDirectory($destination_root);
		$owned = [];
		foreach ($inventory as $entry) {
			if (!is_array($entry) || !isset($entry['path'], $entry['sha256'], $entry['size'])) {
				throw new \RuntimeException('Vendor inventory entry is invalid.');
			}
			$path = $this->normalizePath((string)$entry['path']);
			$key = strtolower($path);
			if (isset($owned[$key]) || !preg_match('/^[a-f0-9]{64}$/', (string)$entry['sha256']) || !is_int($entry['size']) || $entry['size'] < 0) {
				throw new \RuntimeException('Vendor inventory entry is invalid.');
			}
			$source = rtrim($source_root, '/\\') . '/' . $path;
			$destination = rtrim($destination_root, '/\\') . '/' . $path;
			if (!is_file($source) || is_link($source) || filesize($source) !== $entry['size']) {
				throw new \RuntimeException('Staged vendor source is invalid.');
			}
			$this->copyFileVerified($source, $destination, (string)$entry['sha256']);
			$owned[$key] = true;
		}
		if (!$this->treeInventoryMatches($destination_root, $owned)) {
			throw new \RuntimeException('Vendor candidate inventory is inconsistent.');
		}
	}

	private function copyTreeVerified(string $source, string $destination, string $identity): void {
		if (!is_dir($source) || is_link($source) || file_exists($destination)) {
			throw new \RuntimeException('Vendor tree copy boundary is invalid.');
		}
		$this->createDirectory($destination);
		$source_real = rtrim(str_replace('\\', '/', (string)realpath($source)), '/') . '/';
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $entry) {
			if ($entry->isLink()) {
				throw new \RuntimeException('Vendor tree contains a link.');
			}
			$real = str_replace('\\', '/', (string)$entry->getRealPath());
			if (!str_starts_with($real . ($entry->isDir() ? '/' : ''), $source_real)) {
				throw new \RuntimeException('Vendor tree escapes its root.');
			}
			$relative = substr(str_replace('\\', '/', $entry->getPathname()), strlen(rtrim(str_replace('\\', '/', $source), '/')) + 1);
			$target = rtrim($destination, '/\\') . '/' . $relative;
			if ($entry->isDir()) {
				$this->createDirectory($target);
			} elseif ($entry->isFile()) {
				$this->copyFileVerified($entry->getPathname(), $target, hash_file('sha256', $entry->getPathname()));
			} else {
				throw new \RuntimeException('Vendor tree contains an unsupported entry.');
			}
		}
		$this->assertVendorIdentity($destination, $identity);
	}

	private function getTreeIdentity(string $root): string {
		if (!is_dir($root) || is_link($root)) {
			throw new \RuntimeException('Vendor identity root is invalid.');
		}
		$root_normalized = rtrim(str_replace('\\', '/', (string)realpath($root)), '/') . '/';
		$files = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
		foreach ($iterator as $entry) {
			if ($entry->isLink() || !$entry->isFile()) {
				throw new \RuntimeException('Vendor identity contains an unsupported entry.');
			}
			$real = str_replace('\\', '/', (string)$entry->getRealPath());
			if (!str_starts_with($real, $root_normalized)) {
				throw new \RuntimeException('Vendor identity path escapes its root.');
			}
			$path = substr($real, strlen($root_normalized));
			$key = strtolower($path);
			if (isset($files[$key])) {
				throw new \RuntimeException('Vendor tree contains a case-colliding path.');
			}
			$files[$key] = $path . "\0" . $entry->getSize() . "\0" . hash_file('sha256', $entry->getPathname());
		}
		ksort($files, SORT_STRING);
		return hash('sha256', implode("\n", $files));
	}

	private function assertVendorIdentity(string $root, ?string $identity): void {
		if (!$identity || !hash_equals($identity, $this->getTreeIdentity($root))) {
			throw new \RuntimeException('Vendor tree identity mismatch.');
		}
	}

	private function treeIdentityMatches(string $root, ?string $identity): bool {
		try {
			$this->assertVendorIdentity($root, $identity);
			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	private function treeInventoryMatches(string $root, array $expected): bool {
		$actual = [];
		$root_normalized = rtrim(str_replace('\\', '/', (string)realpath($root)), '/') . '/';
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
		foreach ($iterator as $entry) {
			if ($entry->isLink() || !$entry->isFile()) {
				return false;
			}
			$real = str_replace('\\', '/', (string)$entry->getRealPath());
			if (!str_starts_with($real, $root_normalized)) {
				return false;
			}
			$actual[strtolower(substr($real, strlen($root_normalized)))] = true;
		}
		ksort($actual);
		ksort($expected);
		return array_keys($actual) === array_keys($expected);
	}

	private function cleanupVendorTransients(string $candidate, string $swap, string $root): void {
		if (is_dir($candidate)) {
			$this->removeVendorTree($candidate, $root);
		}
		if (is_dir($swap)) {
			$this->removeVendorTree($swap, $root);
		}
	}

	private function removeVendorTree(string $directory, string $allowed_root): void {
		$this->removeUpdaterDirectory($directory, $allowed_root);
	}

	private function removeUpdaterDirectory(string $directory, string $release_root): void {
		if (!is_dir($directory)) {
			return;
		}
		$release_real = rtrim(str_replace('\\', '/', realpath($release_root)), '/') . '/';
		$directory_real = str_replace('\\', '/', (string)realpath($directory));
		if (!$directory_real || !str_starts_with($directory_real . '/', $release_real) || is_link($directory)) {
			throw new \RuntimeException('Updater cleanup path is unsafe.');
		}
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $entry) {
			if ($entry->isLink()) {
				throw new \RuntimeException('Updater cleanup contains a link.');
			}
			$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
		}
		if (!rmdir($directory)) {
			throw new \RuntimeException('Updater cleanup directory cannot be removed.');
		}
	}

	private function validateJournalOperation(array $operation): string {
		$required = ['path', 'operation', 'old_exists', 'old_hash', 'new_hash', 'backup', 'swap', 'status'];
		foreach ($required as $key) {
			if (!array_key_exists($key, $operation)) {
				throw new \RuntimeException('Journal operation is incomplete.');
			}
		}
		$path = $this->normalizePath((string)$operation['path']);
		if ($this->isProtectedPath($path) || !in_array($operation['operation'], ['ADD', 'REPLACE', 'REMOVE'], true) || !is_bool($operation['old_exists'])) {
			throw new \RuntimeException('Journal operation is invalid.');
		}
		return $path;
	}

	private function copyFileVerified(string $source, string $destination, string $hash): void {
		$this->createDirectory(dirname($destination));
		$input = fopen($source, 'rb');
		$output = fopen($destination, 'xb');
		if (!$input || !$output || stream_copy_to_stream($input, $output) === false) {
			if (is_resource($input)) fclose($input);
			if (is_resource($output)) fclose($output);
			@unlink($destination);
			throw new \RuntimeException('Verified file copy failed.');
		}
		fclose($input);
		fclose($output);
		if (!hash_equals($hash, hash_file('sha256', $destination))) {
			@unlink($destination);
			throw new \RuntimeException('Verified file copy hash mismatch.');
		}
	}

	private function resolveLivePath(string $path): string {
		$normalized = $this->normalizePath($path);
		if ($this->isProtectedPath($normalized)) {
			throw new \RuntimeException('Protected live path.');
		}
		$root = rtrim(str_replace('\\', '/', realpath(DIR_OPENCART)), '/') . '/';
		$current = rtrim(DIR_OPENCART, '/\\');
		$parts = explode('/', $normalized);
		array_pop($parts);
		foreach ($parts as $part) {
			$current .= '/' . $part;
			if (is_link($current)) {
				throw new \RuntimeException('Application path contains a link.');
			}
			if (file_exists($current)) {
				$real = str_replace('\\', '/', (string)realpath($current));
				if (!str_starts_with($real . '/', $root)) {
					throw new \RuntimeException('Application path escapes the live root.');
				}
			}
		}
		return rtrim(DIR_OPENCART, '/\\') . '/' . $normalized;
	}

	private function createSafeParentDirectory(string $directory): void {
		$root = rtrim(str_replace('\\', '/', realpath(DIR_OPENCART)), '/');
		$target = rtrim(str_replace('\\', '/', $directory), '/');
		if (!str_starts_with($target . '/', $root . '/')) {
			throw new \RuntimeException('Application parent path escapes the live root.');
		}
		if ($target === $root) {
			return;
		}
		$this->createDirectory($directory);
		$this->resolveLivePath(substr(str_replace('\\', '/', $directory), strlen($root) + 1) . '/placeholder');
	}

	private function validateInstalledVersion(string $version): void {
		$file = $this->resolveLivePath('system/version.php');
		$content = is_file($file) ? file_get_contents($file) : false;
		if ($content === false || !preg_match("/define\\('VERSION',\\s*'" . preg_quote($version, '/') . "'\\);/", $content)) {
			throw new \RuntimeException('Canonical installed version did not advance to the target release.');
		}
	}

	private function acquireLock(string $file, string $version): void {
		$this->createDirectory(dirname($file));
		$handle = fopen($file, 'xb');
		if (!$handle) {
			throw new \RuntimeException('Updater lock is already held.');
		}
		fwrite($handle, json_encode(['version' => $version, 'created_at' => gmdate('c')], JSON_UNESCAPED_SLASHES));
		fflush($handle);
		fclose($handle);
	}

	private function assertLockOwner(string $file, string $version): void {
		$lock = $this->readState($file);
		if (($lock['version'] ?? '') !== $version) {
			throw new \RuntimeException('Updater lock belongs to another update.');
		}
	}

	private function releaseLock(string $file, string $version): void {
		$this->assertLockOwner($file, $version);
		if (!unlink($file)) {
			throw new \RuntimeException('Updater lock could not be released.');
		}
	}

	private function findUnresolvedUpdate(string $update_root): bool {
		foreach (glob(rtrim($update_root, '/\\') . '/*/state/state.json') ?: [] as $state_file) {
			$state = $this->readState($state_file);
			if (in_array($state['status'] ?? '', ['APPLYING', 'ROLLBACK_REQUIRED', 'ROLLBACK_FAILED', 'DATABASE_PENDING', 'DATABASE_APPLYING', 'DATABASE_RECOVERY_REQUIRED', 'DATABASE_RESTORE_REQUIRED', 'DATABASE_RESTORING', 'DATABASE_RESTORE_FAILED', 'DATABASE_RESTORED'], true)) {
				return true;
			}
		}
		foreach (glob(rtrim($update_root, '/\\') . '/*/state/journal.json') ?: [] as $journal_file) {
			$journal = $this->readState($journal_file);
			if (!in_array($journal['status'] ?? '', ['APPLIED', 'ROLLED_BACK'], true)) {
				return true;
			}
		}
		return false;
	}

	public function selectAssets(array $release, string $version): ?array {
		$artifact_name = 'opencore-' . $version . '.zip';
		$checksum_name = $artifact_name . '.sha256';
		$matches = ['artifact' => [], 'checksum' => []];

		foreach (($release['assets'] ?? []) as $asset) {
			if (!is_array($asset)) {
				continue;
			}

			$name = (string)($asset['name'] ?? '');
			$url = (string)($asset['browser_download_url'] ?? '');
			$expected_prefix = self::DOWNLOAD_URL_PREFIX . ($release['tag_name'] ?? '') . '/';

			if (!str_starts_with($url, $expected_prefix) || filter_var($url, FILTER_VALIDATE_URL) === false) {
				continue;
			}

			if ($name === $artifact_name) {
				$matches['artifact'][] = ['name' => $name, 'url' => $url];
			} elseif ($name === $checksum_name) {
				$matches['checksum'][] = ['name' => $name, 'url' => $url];
			}
		}

		return count($matches['artifact']) === 1 && count($matches['checksum']) === 1 ? [
			'artifact' => $matches['artifact'][0],
			'checksum' => $matches['checksum'][0]
		] : null;
	}

	public function validateStaging(string $staging_root, string $version, string $current_version): array {
		$manifest_file = rtrim($staging_root, '/\\') . '/manifest.json';

		if (!is_file($manifest_file)) {
			throw new \RuntimeException('Manifest is missing.');
		}

		$manifest = json_decode((string)file_get_contents($manifest_file), true, 512, JSON_THROW_ON_ERROR);

		if (!is_array($manifest) || ($manifest['contract_version'] ?? null) !== self::CONTRACT_VERSION || ($manifest['protected_paths_version'] ?? null) !== self::PROTECTED_PATHS_VERSION || ($manifest['application'] ?? '') !== 'opencore') {
			throw new \RuntimeException('Manifest identity is invalid.');
		}

		$this->assertKeys($manifest, ['contract_version', 'protected_paths_version', 'application', 'version', 'release', 'compatible_source_versions', 'application_files', 'application_removals', 'composer_lock_sha256', 'vendor', 'database']);

		if ($this->normalizeVersion((string)($manifest['version'] ?? '')) !== $version || !is_array($manifest['compatible_source_versions'] ?? null) || !$manifest['compatible_source_versions'] || !in_array($current_version, $manifest['compatible_source_versions'], true)) {
			throw new \RuntimeException('Manifest version compatibility is invalid.');
		}

		$source_versions = [];
		foreach ($manifest['compatible_source_versions'] as $source_version) {
			if (!is_string($source_version) || $this->normalizeVersion($source_version) !== $source_version || isset($source_versions[$source_version])) {
				throw new \RuntimeException('Compatible source version is invalid.');
			}
			$source_versions[$source_version] = true;
		}

		if (!isset($manifest['release']['built_at']) || !is_string($manifest['release']['built_at']) || $this->normalizeVersion((string)($manifest['release']['tag'] ?? '')) !== $version || !isset($manifest['application_files']) || !is_array($manifest['application_files']) || !isset($manifest['application_removals']) || !is_array($manifest['application_removals']) || !isset($manifest['vendor']) || !is_array($manifest['vendor']) || !isset($manifest['database']) || !is_array($manifest['database'])) {
			throw new \RuntimeException('Manifest structure is incomplete.');
		}

		$this->assertKeys($manifest['release'], ['tag', 'built_at']);
		if (strtotime($manifest['release']['built_at']) === false) {
			throw new \RuntimeException('Release build time is invalid.');
		}

		if (!isset($manifest['database']['required']) || !is_bool($manifest['database']['required']) || !isset($manifest['database']['updates']) || !is_array($manifest['database']['updates'])) {
			throw new \RuntimeException('Database update contract is invalid.');
		}

		$this->assertKeys($manifest['database'], ['required', 'updates']);
		$manifest['database']['updates'] = $this->validateDatabaseIdentifiers($manifest['database']['updates'], $current_version, $version);
		if ($manifest['database']['required'] !== (bool)$manifest['database']['updates']) {
			throw new \RuntimeException('Database update requirement is inconsistent.');
		}

		$owned = [];
		$declared_archive = ['manifest.json' => true];

		$this->validateFileInventory($manifest['application_files'], 'payload/application/', $staging_root, $owned, $declared_archive);

		$removals = [];
		foreach ($manifest['application_removals'] as $path) {
			$normalized = $this->normalizePath((string)$path);
			$key = strtolower($normalized);
			if ($this->isProtectedPath($normalized) || isset($owned[$key]) || isset($removals[$key])) {
				throw new \RuntimeException('Application removal path is invalid.');
			}
			$removals[$key] = true;
		}

		$vendor = $manifest['vendor'];
		if (!isset($vendor['included']) || !is_bool($vendor['included']) || !isset($vendor['files']) || !is_array($vendor['files'])) {
			throw new \RuntimeException('Vendor contract is invalid.');
		}
		$this->assertKeys($vendor, ['included', 'identity', 'files']);

		if ($vendor['included']) {
			if (!is_string($vendor['identity'] ?? null) || $vendor['identity'] === '' || !$vendor['files'] || !preg_match('/^[a-f0-9]{64}$/', (string)($manifest['composer_lock_sha256'] ?? ''))) {
				throw new \RuntimeException('Vendor identity is invalid.');
			}
			$vendor_owned = [];
			$this->validateFileInventory($vendor['files'], 'payload/vendor/', $staging_root, $vendor_owned, $declared_archive, false);
		} elseif ($vendor['files'] || ($vendor['identity'] ?? null) !== null) {
			throw new \RuntimeException('Undeclared vendor payload is invalid.');
		}

		$composer_lock = rtrim($staging_root, '/\\') . '/payload/application/composer.lock';
		if (!is_file($composer_lock) || !hash_equals((string)($manifest['composer_lock_sha256'] ?? ''), hash_file('sha256', $composer_lock))) {
			throw new \RuntimeException('Composer lock identity is invalid.');
		}

		$actual = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($staging_root, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->isLink() || !$file->isFile()) {
				throw new \RuntimeException('Unsupported staged entry.');
			}
			$relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($staging_root, '/\\')) + 1));
			$actual[$this->normalizePath($relative)] = true;
		}

		if (array_keys($actual) !== array_keys($declared_archive) || count($actual) !== count($declared_archive)) {
			ksort($actual);
			ksort($declared_archive);
			if (array_keys($actual) !== array_keys($declared_archive)) {
				throw new \RuntimeException('Archive contains undeclared or missing payload files.');
			}
		}

		return $manifest;
	}

	public function normalizePath(string $path): string {
		if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/^[a-zA-Z]:/', $path) || str_contains($path, '//')) {
			throw new \RuntimeException('Unsafe path.');
		}

		$parts = explode('/', $path);
		foreach ($parts as $part) {
			if ($part === '' || $part === '.' || $part === '..' || str_contains($part, ':')) {
				throw new \RuntimeException('Unsafe path component.');
			}
		}

		return implode('/', $parts);
	}

	public function isProtectedPath(string $path): bool {
		$key = strtolower($path);
		return $key === 'config.php' || $key === 'ocadmin/config.php' || $key === '.env' || str_starts_with($key, '.env.') || $key === '.git' || str_starts_with($key, '.git/') || $key === 'storage' || str_starts_with($key, 'storage/');
	}

	public function extractArchive(string $archive, string $staging_root): void {
		$zip = new \ZipArchive();
		if ($zip->open($archive, \ZipArchive::RDONLY) !== true) {
			throw new \RuntimeException('Artifact archive cannot be opened.');
		}

		try {
			$manifest_index = $zip->locateName('manifest.json', \ZipArchive::FL_NOCASE);
			if ($manifest_index === false || $zip->getNameIndex($manifest_index) !== 'manifest.json') {
				throw new \RuntimeException('Archive manifest entry is missing or ambiguous.');
			}

			$manifest_stat = $zip->statIndex($manifest_index);
			if (!$manifest_stat || $manifest_stat['size'] < 1 || $manifest_stat['size'] > self::MAX_MANIFEST_SIZE) {
				throw new \RuntimeException('Archive manifest size is invalid.');
			}

			$manifest = json_decode((string)$zip->getFromIndex($manifest_index), true, 512, JSON_THROW_ON_ERROR);
			$declared = $this->getDeclaredArchivePaths($manifest);
			$entries = [];
			$total_size = 0;

			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = $zip->getNameIndex($index);
				$is_directory = str_ends_with($name, '/');
				$normalized = $this->normalizePath(rtrim($name, '/'));
				$key = strtolower($normalized);
				$zip->getExternalAttributesIndex($index, $operations, $attributes);
				$type = ($attributes >> 16) & 0170000;
				$stat = $zip->statIndex($index);

				if (!$stat || isset($entries[$key]) || ($type && $type !== 0100000 && $type !== 0040000)) {
					throw new \RuntimeException('Archive contains a duplicate or special entry.');
				}

				if ($is_directory) {
					$owned_directory = false;
					foreach ($declared as $declared_path => $size) {
						if (str_starts_with($declared_path, $key . '/')) {
							$owned_directory = true;
							break;
						}
					}
					if (!$owned_directory) {
						throw new \RuntimeException('Archive contains an undeclared directory.');
					}
				} elseif (!array_key_exists($key, $declared) || ($declared[$key] !== null && $stat['size'] !== $declared[$key])) {
					throw new \RuntimeException('Archive contains an undeclared file or size mismatch.');
				}

				$total_size += $stat['size'];
				if ($total_size > self::MAX_STAGING_SIZE) {
					throw new \RuntimeException('Archive expanded size exceeds the staging limit.');
				}
				$entries[$key] = true;
			}

			if (array_diff_key($declared, $entries)) {
				throw new \RuntimeException('Archive is missing declared files.');
			}

			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = $zip->getNameIndex($index);
				$is_directory = str_ends_with($name, '/');
				$normalized = $this->normalizePath(rtrim($name, '/'));

				$destination = rtrim($staging_root, '/\\') . '/' . $normalized;
				if ($is_directory) {
					$this->createDirectory($destination);
					continue;
				}

				$this->createDirectory(dirname($destination));
				$source = $zip->getStream($name);
				$target = fopen($destination, 'xb');
				if (!$source || !$target || stream_copy_to_stream($source, $target) === false) {
					throw new \RuntimeException('Archive entry extraction failed.');
				}
				fclose($source);
				fclose($target);
			}
		} finally {
			$zip->close();
		}
	}

	public function normalizeVersion(string $tag): ?string {
		if (!preg_match('/^v?(\d{4})\.(0[1-9]|1[0-2])\.([1-9]\d*)$/', $tag, $matches)) {
			return null;
		}
		return $matches[1] . '.' . $matches[2] . '.' . $matches[3];
	}

	public function validateDatabaseIdentifiers(array $identifiers, string $source_version, string $target_version): array {
		if ($this->normalizeVersion($source_version) !== $source_version || $this->normalizeVersion($target_version) !== $target_version || $this->compareVersions($source_version, $target_version) >= 0) {
			throw new \RuntimeException('Database update version boundary is invalid.');
		}

		$pattern = '/^(\d{4}\.(?:0[1-9]|1[0-2])\.[1-9]\d*)\.(00[1-9]|0[1-9][0-9]|[1-9][0-9]{2})$/';
		$validated = [];
		$previous_version = null;
		$previous_sequence = 0;

		foreach ($identifiers as $identifier) {
			if (!is_string($identifier) || !preg_match($pattern, $identifier, $matches)) {
				throw new \RuntimeException('Database update identifier is malformed.');
			}

			$step_version = $matches[1];
			$sequence = (int)$matches[2];

			if ($this->normalizeVersion($step_version) !== $step_version || $this->compareVersions($step_version, $source_version) <= 0 || $this->compareVersions($step_version, $target_version) > 0) {
				throw new \RuntimeException('Database update step version is outside the source and target boundary.');
			}

			$version_order = $previous_version === null ? 1 : $this->compareVersions($step_version, $previous_version);

			if (isset($validated[$identifier]) || $version_order < 0 || ($version_order === 0 && $sequence <= $previous_sequence)) {
				throw new \RuntimeException('Database update identifiers are duplicated or out of order.');
			}

			$validated[$identifier] = $identifier;
			$previous_version = $step_version;
			$previous_sequence = $sequence;
		}

		return array_values($validated);
	}

	public function compareVersions(string $version1, string $version2): int {
		return array_map('intval', explode('.', $version1)) <=> array_map('intval', explode('.', $version2));
	}

	private function requestReleases(): ?array {
		$curl = curl_init(self::RELEASES_URL);
		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'OpenCore/' . VERSION, CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28']]);
		$response = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if ($response === false || $status < 200 || $status >= 300) {
			$this->log->write('OpenCore release discovery failed with HTTP status ' . $status . ($error ? ': ' . $error : '.'));
			return null;
		}

		$releases = json_decode($response, true);
		if (!is_array($releases) || !array_is_list($releases)) {
			$this->log->write('OpenCore release discovery returned an invalid JSON response.');
			return null;
		}
		return $releases;
	}

	private function download(string $url, string $destination, int $limit): void {
		$handle = fopen($destination, 'xb');
		if (!$handle) {
			throw new \RuntimeException('Download destination cannot be created.');
		}
		$size = 0;
		$curl = curl_init($url);
		curl_setopt_array($curl, [
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 300,
			CURLOPT_USERAGENT      => 'OpenCore/' . VERSION,
			CURLOPT_HTTPHEADER     => ['Accept: application/octet-stream'],
			CURLOPT_WRITEFUNCTION  => function ($curl, string $data) use ($handle, $limit, &$size): int {
				$size += strlen($data);
				if ($size > $limit) {
					return 0;
				}
				return fwrite($handle, $data);
			}
		]);
		$result = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
		$error = curl_error($curl);
		curl_close($curl);
		fclose($handle);

		$host = strtolower((string)parse_url($effective_url, PHP_URL_HOST));
		if ($result === false || $status < 200 || $status >= 300 || $size < 1 || !in_array($host, ['github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'], true)) {
			@unlink($destination);
			throw new \RuntimeException('Release download failed with HTTP status ' . $status . ($error ? ': ' . $error : '.'));
		}
	}

	private function validateFileInventory(array $inventory, string $prefix, string $root, array &$owned, array &$archive, bool $protect = true): void {
		foreach ($inventory as $entry) {
			if (!is_array($entry) || !isset($entry['path'], $entry['size'], $entry['sha256']) || !is_int($entry['size']) || $entry['size'] < 0 || !preg_match('/^[a-f0-9]{64}$/', (string)$entry['sha256'])) {
				throw new \RuntimeException('File inventory entry is invalid.');
			}
			$this->assertKeys($entry, ['path', 'size', 'sha256']);
			$path = $this->normalizePath((string)$entry['path']);
			$key = strtolower($path);
			if (($protect && $this->isProtectedPath($path)) || isset($owned[$key])) {
				throw new \RuntimeException('File inventory path is invalid.');
			}
			$file = rtrim($root, '/\\') . '/' . $prefix . $path;
			if (!is_file($file) || filesize($file) !== $entry['size'] || !hash_equals((string)$entry['sha256'], hash_file('sha256', $file))) {
				throw new \RuntimeException('Payload file validation failed.');
			}
			$owned[$key] = true;
			$archive[$prefix . $path] = true;
		}
	}

	private function parseChecksum(string $content, string $filename): ?string {
		return preg_match('/^([a-fA-F0-9]{64})[ \t]+[*]?' . preg_quote($filename, '/') . '\s*$/', $content, $matches) ? strtolower($matches[1]) : null;
	}

	private function getDeclaredArchivePaths(array $manifest): array {
		if (!isset($manifest['application_files']) || !is_array($manifest['application_files']) || !isset($manifest['vendor']['files']) || !is_array($manifest['vendor']['files'])) {
			throw new \RuntimeException('Archive manifest inventory is invalid.');
		}

		$declared = ['manifest.json' => null];
		foreach ([['payload/application/', $manifest['application_files'], true], ['payload/vendor/', $manifest['vendor']['files'], false]] as [$prefix, $inventory, $protect]) {
			foreach ($inventory as $entry) {
				if (!is_array($entry) || !isset($entry['path'], $entry['size']) || !is_int($entry['size']) || $entry['size'] < 0) {
					throw new \RuntimeException('Archive manifest file entry is invalid.');
				}
				$path = $this->normalizePath((string)$entry['path']);
				if ($protect && $this->isProtectedPath($path)) {
					throw new \RuntimeException('Archive declares a protected path.');
				}
				$key = strtolower($prefix . $path);
				if (array_key_exists($key, $declared)) {
					throw new \RuntimeException('Archive manifest contains duplicate paths.');
				}
				$declared[$key] = $entry['size'];
			}
		}

		return $declared;
	}

	private function assertKeys(array $data, array $keys): void {
		$actual = array_keys($data);
		sort($actual);
		sort($keys);
		if ($actual !== $keys) {
			throw new \RuntimeException('Manifest contains missing or unknown contract fields.');
		}
	}

	private function createDirectory(string $directory): void {
		if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
			throw new \RuntimeException('Staging directory cannot be created.');
		}
	}

	private function readState(string $file): array {
		$previous = $file . '.previous';
		$current_state = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
		$previous_state = is_file($previous) ? json_decode((string)file_get_contents($previous), true) : null;

		if (is_array($current_state)) {
			if (is_array($previous_state) && !unlink($previous)) {
				throw new \RuntimeException('Interrupted state cleanup failed.');
			}
			return $current_state;
		}

		if (is_array($previous_state)) {
			if (is_file($file) && !unlink($file)) {
				throw new \RuntimeException('Invalid interrupted state cannot be removed.');
			}
			if (!rename($previous, $file)) {
				throw new \RuntimeException('Interrupted state cannot be restored.');
			}
			return $previous_state;
		}

		return [];
	}

	private function writeState(string $file, array $state): void {
		$this->createDirectory(dirname($file));
		$content = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$temp = $file . '.tmp-' . bin2hex(random_bytes(8));
		$handle = fopen($temp, 'xb');
		if (!$handle || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
			if (is_resource($handle)) {
				fclose($handle);
			}
			@unlink($temp);
			throw new \RuntimeException('Update state cannot be written.');
		}
		if (function_exists('fsync')) {
			fsync($handle);
		}
		fclose($handle);

		$previous = $file . '.previous';
		if (is_file($previous)) {
			@unlink($temp);
			throw new \RuntimeException('An interrupted state write requires reconciliation.');
		}

		if (is_file($file) && !rename($file, $previous)) {
			@unlink($temp);
			throw new \RuntimeException('Prior update state cannot be preserved.');
		}

		if (!rename($temp, $file)) {
			if (is_file($previous)) {
				rename($previous, $file);
			}
			@unlink($temp);
			throw new \RuntimeException('Update state cannot be activated.');
		}

		if (is_file($previous) && !unlink($previous)) {
			throw new \RuntimeException('Prior update state cleanup failed.');
		}
	}
}
