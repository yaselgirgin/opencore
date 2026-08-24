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
				return ['success' => false, 'status' => 'RECOVERY_FAILED'];
			}
		}

		if (($state['status'] ?? '') !== 'STAGED' || ($state['target_version'] ?? '') !== $version || is_file($lock_file) || $this->findUnresolvedUpdate($update_root)) {
			return ['success' => false, 'status' => 'RECOVERY_REQUIRED'];
		}

		try {
			$this->revalidateStagedState($root, $state, $version, $current_version);
			$manifest = $this->validateStaging($root . 'staging/', $version, $current_version);

			if ($manifest['database']['required']) {
				return ['success' => false, 'status' => 'DATABASE_UPDATE_NOT_SUPPORTED'];
			}
			if ($manifest['vendor']['included']) {
				return ['success' => false, 'status' => 'VENDOR_APPLY_NOT_SUPPORTED'];
			}
		} catch (\Throwable $throwable) {
			$this->log->write('OpenCore filesystem apply preflight failed for ' . $version . ': ' . $throwable->getMessage());
			return ['success' => false, 'status' => 'PREFLIGHT_FAILED'];
		}

		try {
			$this->acquireLock($lock_file, $version);
			$journal = $this->createApplyJournal($root, $manifest, $version, $current_version);
			$this->writeState($journal_file, $journal);
			$this->writeState($state_file, ['status' => 'APPLYING', 'target_version' => $version, 'started_at' => gmdate('c')]);

			foreach ($journal['operations'] as $index => $operation) {
				$this->assertLivePrecondition($operation);
				$journal['operations'][$index]['status'] = 'MUTATING';
				$this->writeState($journal_file, $journal);
				$this->applyOperation($root, $journal['operations'][$index]);
				$journal['operations'][$index]['status'] = 'COMPLETED';
				$this->writeState($journal_file, $journal);
			}

			$this->validateInstalledVersion($version);
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

	public function recover(string $version): array {
		if ($this->normalizeVersion($version) !== $version) {
			return ['success' => false, 'status' => 'RECOVERY_FAILED'];
		}

		$update_root = rtrim(DIR_STORAGE, '/\\') . '/updates/';
		$root = $update_root . $version . '/';
		$state_file = $root . 'state/state.json';
		$lock_file = $update_root . 'apply.lock';
		$state = $this->readState($state_file);

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

	private function createApplyJournal(string $root, array $manifest, string $version, string $current_version): array {
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

		return ['status' => 'PLANNED', 'target_version' => $version, 'source_version' => $current_version, 'created_at' => gmdate('c'), 'operations' => $operations];
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

	private function rollbackJournal(string $root, string $version): void {
		$journal_file = $root . 'state/journal.json';
		$journal = $this->readState($journal_file);
		if (($journal['target_version'] ?? '') !== $version || !isset($journal['operations']) || !is_array($journal['operations'])) {
			throw new \RuntimeException('Apply journal is missing or invalid.');
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

		$journal['status'] = 'ROLLED_BACK';
		$journal['rolled_back_at'] = gmdate('c');
		$this->writeState($journal_file, $journal);
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
	}

	private function discardUnstartedApply(string $root, string $version): void {
		$journal_file = $root . 'state/journal.json';
		if (is_file($journal_file)) {
			$journal = $this->readState($journal_file);
			if (($journal['target_version'] ?? '') !== $version || ($journal['status'] ?? '') !== 'PLANNED' || !isset($journal['operations']) || !is_array($journal['operations'])) {
				throw new \RuntimeException('Unstarted apply journal is inconsistent.');
			}
			foreach ($journal['operations'] as $operation) {
				if (($operation['status'] ?? '') !== 'PENDING') {
					throw new \RuntimeException('Unstarted apply contains a mutation marker.');
				}
				$this->assertLivePrecondition($operation);
			}
		}

		$this->removeUpdaterDirectory($root . 'backup/', $root);
		foreach (array_merge([$journal_file, $journal_file . '.previous'], glob($journal_file . '.tmp-*') ?: []) as $file) {
			if (is_file($file) && !unlink($file)) {
				throw new \RuntimeException('Unstarted apply journal cleanup failed.');
			}
		}
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
			if (in_array($state['status'] ?? '', ['APPLYING', 'ROLLBACK_REQUIRED', 'ROLLBACK_FAILED'], true)) {
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
		foreach ($manifest['database']['updates'] as $update) {
			if (!is_string($update) || !preg_match('/^[a-z0-9][a-z0-9._-]*$/', $update)) {
				throw new \RuntimeException('Database update identifier is invalid.');
			}
		}
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
