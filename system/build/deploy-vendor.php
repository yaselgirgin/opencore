<?php
declare(strict_types=1);

const VENDOR_MANIFEST_SCHEMA = 'opencore-vendor-artifact/v1';
const VENDOR_INVENTORY_ALGORITHM = 'sha256(path NUL decimal-size NUL lowercase-sha256 LF), paths bytewise-sorted';

final class DeploymentException extends RuntimeException {
	public function __construct(public readonly string $status, string $message) {
		parent::__construct($message);
	}
}

class DeploymentFilesystem {
	public function rename(string $from, string $to): bool {
		return @rename($from, $to);
	}

	public function copy(string $from, string $to): bool {
		return @copy($from, $to);
	}

	public function mkdir(string $path): bool {
		return @mkdir($path, 0700);
	}

	public function unlink(string $path): bool {
		return @unlink($path);
	}

	public function rmdir(string $path): bool {
		return @rmdir($path);
	}
}

final class VendorDeployment {
	private string $repo;
	private string $storage = '';
	private array $ownedPaths = [];

	public function __construct(private readonly DeploymentFilesystem $filesystem) {
		$repo = realpath(dirname(__DIR__, 2));

		if ($repo === false) {
			throw new DeploymentException('PATH_SAFETY_FAILED', 'Cannot resolve repository root');
		}

		$this->repo = $this->normalize($repo);
	}

	public function deploy(string $artifactInput, string $storageInput, bool $dryRun, bool $keepBackup, bool $quiescent): array {
		$artifact = $this->existingDirectory($artifactInput, 'artifact');
		$this->storage = $this->existingDirectory($storageInput, 'storage');
		$target = $this->normalize($this->storage . DIRECTORY_SEPARATOR . 'vendor');

		$this->assertPathSafety($artifactInput, $storageInput, $artifact, $target);
		$this->assertTreeSafe($artifact, 'artifact');

		$manifestPath = $artifact . DIRECTORY_SEPARATOR . 'vendor-manifest.json';
		$vendor = $artifact . DIRECTORY_SEPARATOR . 'vendor';
		$manifest = $this->validateManifest($manifestPath, $vendor);
		$this->verifyReleaseCompatibility($manifest);
		$artifactInventory = $this->verifyInventory($vendor, $manifest);
		$this->verifyPackageGraph($vendor, $manifest);

		if (is_dir($target) && !$this->isLinkOrReparse($target)) {
			$this->assertTreeSafe($target, 'active vendor');
			$activeInventory = $this->inventory($target);

			if ($activeInventory['sha256'] === $artifactInventory['sha256']
				&& $activeInventory['file_count'] === $artifactInventory['file_count']
				&& $activeInventory['total_bytes'] === $artifactInventory['total_bytes']) {
				return ['status' => 'ALREADY_ACTIVE', 'inventory_sha256' => $artifactInventory['sha256']];
			}
		} elseif (file_exists($target) || is_link($target)) {
			throw new DeploymentException('PATH_SAFETY_FAILED', 'Target vendor path is not a regular directory');
		}

		if ($dryRun) {
			return ['status' => 'DRY_RUN_OK', 'inventory_sha256' => $artifactInventory['sha256']];
		}

		if (!$quiescent) {
			throw new DeploymentException(
				'QUIESCENCE_CONFIRMATION_REQUIRED',
				'Web and cron traffic must be stopped/quiescent before vendor activation; pass --confirm-quiescent after doing so'
			);
		}

		$nonce = bin2hex(random_bytes(12));
		$stage = $this->ownedPath('.opencore-vendor-stage-' . $nonce);
		$backup = $this->ownedPath('.opencore-vendor-backup-' . $nonce);
		$failed = $this->ownedPath('.opencore-vendor-failed-' . $nonce);
		$hadTarget = is_dir($target);

		try {
			$this->copyTree($vendor, $stage);
			$this->assertTreeSafe($stage, 'staged vendor');
			$stageInventory = $this->inventory($stage);

			if ($stageInventory !== $artifactInventory) {
				throw new DeploymentException('STAGE_VERIFICATION_FAILED', 'Staged vendor inventory differs from verified artifact');
			}
		} catch (Throwable $exception) {
			$this->cleanupOwned($stage);
			throw $exception;
		}

		if ($hadTarget && !$this->filesystem->rename($target, $backup)) {
			$this->cleanupOwned($stage);
			throw new DeploymentException('FIRST_RENAME_FAILED', 'Cannot move active vendor to the current-run backup path');
		}

		if (!$this->filesystem->rename($stage, $target)) {
			if ($hadTarget) {
				if ($this->filesystem->rename($backup, $target)) {
					$this->cleanupOwned($stage);
					throw new DeploymentException('ACTIVATION_FAILED_ROLLED_BACK', 'Cannot activate staged vendor; previous vendor was restored');
				}

				throw new DeploymentException('ROLLBACK_FAILED', 'Cannot activate staged vendor and cannot restore the previous vendor; backup was preserved');
			}

			$this->cleanupOwned($stage);
			throw new DeploymentException('ACTIVATION_FAILED', 'Cannot activate staged vendor; no previous vendor existed');
		}

		try {
			$this->smoke($target);
		} catch (Throwable $exception) {
			if (!$this->filesystem->rename($target, $failed)) {
				throw new DeploymentException('ROLLBACK_FAILED', 'Post-activation smoke failed and the failed vendor cannot be quarantined');
			}

			if ($hadTarget && !$this->filesystem->rename($backup, $target)) {
				throw new DeploymentException('ROLLBACK_FAILED', 'Post-activation smoke failed and the previous vendor cannot be restored; backup was preserved');
			}

			$this->cleanupOwned($failed);
			throw new DeploymentException(
				$hadTarget ? 'POST_SMOKE_FAILED_ROLLED_BACK' : 'POST_SMOKE_FAILED_TARGET_REMOVED',
				'Post-activation smoke failed' . ($hadTarget ? '; previous vendor was restored' : '; failed vendor was removed')
			);
		}

		if ($hadTarget && !$keepBackup && !$this->cleanupOwned($backup)) {
			return [
				'status' => 'SUCCESS_WITH_BACKUP_CLEANUP_WARNING',
				'inventory_sha256' => $artifactInventory['sha256'],
				'backup' => $backup
			];
		}

		$result = ['status' => 'ACTIVATION_OK', 'inventory_sha256' => $artifactInventory['sha256']];

		if ($hadTarget && $keepBackup) {
			$result['backup'] = $backup;
		}

		return $result;
	}

	private function validateManifest(string $manifestPath, string $vendor): array {
		if (!is_file($manifestPath) || !is_readable($manifestPath)) {
			throw new DeploymentException('MANIFEST_INVALID', 'vendor-manifest.json is missing or unreadable');
		}

		try {
			$manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			throw new DeploymentException('MANIFEST_INVALID', 'vendor-manifest.json is not valid JSON');
		}

		if (!is_array($manifest) || ($manifest['schema'] ?? null) !== VENDOR_MANIFEST_SCHEMA) {
			throw new DeploymentException('MANIFEST_INVALID', 'Manifest schema is missing or unsupported');
		}

		if (!is_string($manifest['composer_version'] ?? null)
			|| !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+].+)?$/', $manifest['composer_version'])) {
			throw new DeploymentException('MANIFEST_INVALID', 'Manifest Composer version is invalid');
		}

		if (!is_string($manifest['php_version'] ?? null)
			|| !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+].+)?$/', $manifest['php_version'])) {
			throw new DeploymentException('MANIFEST_INVALID', 'Manifest PHP version is invalid');
		}

		if (!is_array($manifest['sources'] ?? null)
			|| !$this->isSha256($manifest['sources']['composer_json_sha256'] ?? null)
			|| !$this->isSha256($manifest['sources']['composer_lock_sha256'] ?? null)) {
			throw new DeploymentException('MANIFEST_INVALID', 'Manifest source hashes are invalid');
		}

		$inventory = $manifest['inventory'] ?? null;

		if (!is_array($inventory)
			|| ($inventory['algorithm'] ?? null) !== VENDOR_INVENTORY_ALGORITHM
			|| !is_int($inventory['file_count'] ?? null) || $inventory['file_count'] < 0
			|| !is_int($inventory['total_bytes'] ?? null) || $inventory['total_bytes'] < 0
			|| !$this->isSha256($inventory['sha256'] ?? null)
			|| !is_array($inventory['files'] ?? null)) {
			throw new DeploymentException('MANIFEST_INVALID', 'Manifest inventory metadata is invalid');
		}

		foreach ($inventory['files'] as $file) {
			if (!is_array($file)
				|| !is_string($file['path'] ?? null) || !$this->safeRelativePath($file['path'])
				|| !is_int($file['size'] ?? null) || $file['size'] < 0
				|| !$this->isSha256($file['sha256'] ?? null)) {
				throw new DeploymentException('MANIFEST_INVALID', 'Manifest contains an invalid inventory row');
			}
		}

		if (!is_array($manifest['packages'] ?? null)) {
			throw new DeploymentException('MANIFEST_INVALID', 'Manifest package summary is missing');
		}

		foreach ($manifest['packages'] as $package) {
			if (!is_array($package)
				|| !is_string($package['name'] ?? null) || $package['name'] === ''
				|| !is_string($package['version'] ?? null) || $package['version'] === ''
				|| !array_key_exists('source_reference', $package)
				|| (!is_string($package['source_reference']) && $package['source_reference'] !== null)
				|| !array_key_exists('dist_reference', $package)
				|| (!is_string($package['dist_reference']) && $package['dist_reference'] !== null)) {
				throw new DeploymentException('MANIFEST_INVALID', 'Manifest contains an invalid package row');
			}
		}

		if (!is_dir($vendor) || !is_file($vendor . DIRECTORY_SEPARATOR . 'autoload.php')) {
			throw new DeploymentException('MANIFEST_INVALID', 'Artifact vendor/autoload.php is missing');
		}

		return $manifest;
	}

	private function verifyReleaseCompatibility(array $manifest): void {
		$composerJson = $this->repo . DIRECTORY_SEPARATOR . 'composer.json';
		$composerLock = $this->repo . DIRECTORY_SEPARATOR . 'composer.lock';

		if (!is_file($composerJson) || !is_file($composerLock)) {
			throw new DeploymentException('RELEASE_COMPATIBILITY_FAILED', 'Current repository Composer sources are missing');
		}

		if (!hash_equals(strtolower($manifest['sources']['composer_json_sha256']), hash_file('sha256', $composerJson))
			|| !hash_equals(strtolower($manifest['sources']['composer_lock_sha256']), hash_file('sha256', $composerLock))) {
			throw new DeploymentException('RELEASE_COMPATIBILITY_FAILED', 'Artifact Composer source hashes do not match the current release');
		}
	}

	private function verifyInventory(string $vendor, array $manifest): array {
		$actual = $this->inventory($vendor);
		$expected = [
			'files' => $manifest['inventory']['files'],
			'file_count' => $manifest['inventory']['file_count'],
			'total_bytes' => $manifest['inventory']['total_bytes'],
			'sha256' => strtolower($manifest['inventory']['sha256'])
		];

		if ($actual !== $expected) {
			throw new DeploymentException('INVENTORY_MISMATCH', 'Artifact vendor inventory does not match its manifest');
		}

		return $actual;
	}

	private function verifyPackageGraph(string $vendor, array $manifest): void {
		$installedPath = $vendor . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
		$lockPath = $this->repo . DIRECTORY_SEPARATOR . 'composer.lock';

		try {
			$installed = json_decode((string)file_get_contents($installedPath), true, 512, JSON_THROW_ON_ERROR);
			$lock = json_decode((string)file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			throw new DeploymentException('PACKAGE_GRAPH_MISMATCH', 'Composer package metadata is missing or invalid');
		}

		if (!is_array($installed) || !is_array($lock)
			|| !is_array($lock['packages'] ?? null)
			|| !is_array($lock['packages-dev'] ?? null)
			|| $lock['packages-dev'] !== []) {
			throw new DeploymentException('PACKAGE_GRAPH_MISMATCH', 'Current lock production/dev graph is invalid');
		}

		$installedPackages = isset($installed['packages']) && is_array($installed['packages']) ? $installed['packages'] : $installed;
		$actual = $this->packageSummary($installedPackages);
		$expected = $this->packageSummary($lock['packages']);
		$declared = $this->packageSummary($manifest['packages']);

		if ($actual !== $expected || $declared !== $expected) {
			throw new DeploymentException('PACKAGE_GRAPH_MISMATCH', 'Artifact, manifest and current composer.lock package graphs differ');
		}
	}

	private function packageSummary(array $packages): array {
		$summary = [];

		foreach ($packages as $package) {
			if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null)) {
				throw new DeploymentException('PACKAGE_GRAPH_MISMATCH', 'Package metadata contains an invalid row');
			}

			$name = $package['name'];

			if (isset($summary[$name])) {
				throw new DeploymentException('PACKAGE_GRAPH_MISMATCH', 'Package metadata contains a duplicate package');
			}

			$summary[$name] = [
				'name' => $name,
				'version' => $package['version'],
				'source_reference' => $package['source_reference'] ?? ($package['source']['reference'] ?? null),
				'dist_reference' => $package['dist_reference'] ?? ($package['dist']['reference'] ?? null)
			];
		}

		ksort($summary, SORT_STRING);

		return array_values($summary);
	}

	private function inventory(string $root): array {
		$this->assertTreeSafe($root, 'inventory tree');
		$rows = [];
		$totalBytes = 0;
		$queue = [$root];

		while ($queue) {
			$directory = array_pop($queue);
			$iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

			foreach ($iterator as $item) {
				$path = $item->getPathname();

				if ($item->isDir()) {
					$queue[] = $path;
					continue;
				}

				if (!$item->isFile()) {
					throw new DeploymentException('UNSAFE_TREE_ENTRY', 'Inventory contains a non-regular filesystem entry');
				}

				$relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
				$size = $item->getSize();
				$rows[$relative] = ['path' => $relative, 'size' => $size, 'sha256' => hash_file('sha256', $path)];
				$totalBytes += $size;
			}
		}

		uksort($rows, 'strcmp');
		$canonical = '';

		foreach ($rows as $row) {
			$canonical .= $row['path'] . "\0" . $row['size'] . "\0" . $row['sha256'] . "\n";
		}

		return [
			'files' => array_values($rows),
			'file_count' => count($rows),
			'total_bytes' => $totalBytes,
			'sha256' => hash('sha256', $canonical)
		];
	}

	private function assertTreeSafe(string $root, string $label): void {
		if ($this->isLinkOrReparse($root)) {
			throw new DeploymentException('UNSAFE_TREE_ENTRY', ucfirst($label) . ' root is a link or reparse point');
		}

		$rootReal = realpath($root);

		if ($rootReal === false) {
			throw new DeploymentException('UNSAFE_TREE_ENTRY', 'Cannot resolve ' . $label);
		}

		$rootReal = $this->normalize($rootReal);
		$queue = [$rootReal];

		while ($queue) {
			$directory = array_pop($queue);
			$iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

			foreach ($iterator as $item) {
				$path = $item->getPathname();

				if ($item->isLink() || $this->isLinkOrReparse($path)) {
					throw new DeploymentException('UNSAFE_TREE_ENTRY', ucfirst($label) . ' contains a link or reparse point');
				}

				$real = realpath($path);

				if ($real === false || !$this->isWithin($this->normalize($real), $rootReal)) {
					throw new DeploymentException('UNSAFE_TREE_ENTRY', ucfirst($label) . ' contains an escaping or unresolved entry');
				}

				if ($item->isDir()) {
					$queue[] = $path;
				} elseif (!$item->isFile()) {
					throw new DeploymentException('UNSAFE_TREE_ENTRY', ucfirst($label) . ' contains a non-regular entry');
				}
			}
		}
	}

	private function copyTree(string $source, string $destination): void {
		if (!$this->filesystem->mkdir($destination)) {
			throw new DeploymentException('STAGE_COPY_FAILED', 'Cannot create current-run staging directory');
		}

		$queue = [[$source, $destination]];

		while ($queue) {
			[$from, $to] = array_pop($queue);
			$iterator = new FilesystemIterator($from, FilesystemIterator::SKIP_DOTS);

			foreach ($iterator as $item) {
				$sourcePath = $item->getPathname();
				$targetPath = $to . DIRECTORY_SEPARATOR . $item->getFilename();

				if ($item->isLink() || $this->isLinkOrReparse($sourcePath)) {
					throw new DeploymentException('STAGE_COPY_FAILED', 'Refusing to copy a link or reparse point');
				}

				if ($item->isDir()) {
					if (!$this->filesystem->mkdir($targetPath)) {
						throw new DeploymentException('STAGE_COPY_FAILED', 'Cannot create staged directory');
					}

					$queue[] = [$sourcePath, $targetPath];
				} elseif ($item->isFile()) {
					if (!$this->filesystem->copy($sourcePath, $targetPath)) {
						throw new DeploymentException('STAGE_COPY_FAILED', 'Cannot copy artifact file into staging');
					}
				} else {
					throw new DeploymentException('STAGE_COPY_FAILED', 'Artifact contains a non-regular entry');
				}
			}
		}
	}

	private function smoke(string $vendor): void {
		$code = <<<'PHP'
require $argv[1];
if (!class_exists('Twig\\Environment') || !class_exists('Twig\\Loader\\ArrayLoader')) {
	throw new RuntimeException('Twig autoload symbols are unavailable');
}
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader(['test' => 'Hello {{ name }}']));
if ($twig->render('test', ['name' => 'OpenCore']) !== 'Hello OpenCore') {
	throw new RuntimeException('Twig render smoke failed');
}
echo 'VENDOR_SMOKE_OK';
PHP;
		$command = [PHP_BINARY, '-r', $code, $vendor . DIRECTORY_SEPARATOR . 'autoload.php'];
		$descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
		$process = proc_open($command, $descriptor, $pipes, $this->repo, null, ['bypass_shell' => true]);

		if (!is_resource($process)) {
			throw new DeploymentException('POST_SMOKE_FAILED', 'Cannot start isolated vendor smoke process');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		if ($exitCode !== 0 || !str_contains($stdout, 'VENDOR_SMOKE_OK')) {
			throw new DeploymentException('POST_SMOKE_FAILED', 'Isolated vendor smoke failed: ' . trim($stderr . "\n" . $stdout));
		}
	}

	private function assertPathSafety(string $artifactInput, string $storageInput, string $artifact, string $target): void {
		$this->assertInputHasNoAlias($artifactInput, $artifact, 'artifact');
		$this->assertInputHasNoAlias($storageInput, $this->storage, 'storage');

		if ($this->samePath($artifact, $this->storage)
			|| $this->samePath($artifact, $target)
			|| $this->isWithin($artifact, $target)
			|| $this->isWithin($target, $artifact)
			|| $this->isWithin($this->storage, $this->repo)
			|| $this->isWithin($artifact, $this->repo)
			|| $this->isWithin($target, $this->repo)) {
			throw new DeploymentException('PATH_SAFETY_FAILED', 'Artifact, storage, target and repository paths overlap unsafely');
		}

		if (!is_writable($this->storage)) {
			throw new DeploymentException('PATH_SAFETY_FAILED', 'Storage directory is not writable');
		}
	}

	private function assertInputHasNoAlias(string $input, string $resolved, string $label): void {
		$lexical = $this->absolutePath($input);

		if (!$this->samePath($lexical, $resolved) || $this->isLinkOrReparse($lexical)) {
			throw new DeploymentException('PATH_SAFETY_FAILED', ucfirst($label) . ' path contains a symlink, reparse point or unresolved alias');
		}
	}

	private function isLinkOrReparse(string $path): bool {
		$linkTarget = @readlink($path);

		if (is_link($path)
			|| ($linkTarget !== false && !$this->samePath($this->normalize($linkTarget), $this->normalize($path)))) {
			return true;
		}

		$stat = @lstat($path);

		return is_array($stat) && (($stat['mode'] & 0170000) === 0120000);
	}

	private function existingDirectory(string $path, string $label): string {
		$real = realpath($path);

		if ($real === false || !is_dir($real)) {
			throw new DeploymentException('PATH_SAFETY_FAILED', ucfirst($label) . ' directory does not exist');
		}

		return $this->normalize($real);
	}

	private function ownedPath(string $basename): string {
		$path = $this->normalize($this->storage . DIRECTORY_SEPARATOR . $basename);

		if (!$this->isWithin($path, $this->storage) || file_exists($path) || is_link($path)) {
			throw new DeploymentException('PATH_SAFETY_FAILED', 'Cannot allocate unique current-run storage path');
		}

		$this->ownedPaths[$path] = true;

		return $path;
	}

	private function cleanupOwned(string $path): bool {
		$path = $this->normalize($path);
		$basename = basename($path);

		if (!isset($this->ownedPaths[$path])
			|| !$this->isWithin($path, $this->storage)
			|| !preg_match('/^\.opencore-vendor-(?:stage|backup|failed)-[a-f0-9]{24}$/', $basename)) {
			return false;
		}

		return $this->removeTree($path);
	}

	private function removeTree(string $path): bool {
		if (!file_exists($path) && !is_link($path)) {
			return true;
		}

		if ($this->isLinkOrReparse($path)) {
			return false;
		}

		if (is_file($path)) {
			return $this->filesystem->unlink($path);
		}

		$iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);

		foreach ($iterator as $item) {
			if (!$this->removeTree($item->getPathname())) {
				return false;
			}
		}

		return $this->filesystem->rmdir($path);
	}

	private function safeRelativePath(string $path): bool {
		if ($path === '' || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
			return false;
		}

		foreach (explode('/', $path) as $part) {
			if ($part === '' || $part === '.' || $part === '..') {
				return false;
			}
		}

		return true;
	}

	private function isSha256(mixed $value): bool {
		return is_string($value) && (bool)preg_match('/^[a-f0-9]{64}$/i', $value);
	}

	private function isWithin(string $path, string $parent): bool {
		$path = $this->comparePath($path);
		$parent = rtrim($this->comparePath($parent), '/') . '/';

		return str_starts_with($path . '/', $parent);
	}

	private function samePath(string $first, string $second): bool {
		return $this->comparePath($first) === $this->comparePath($second);
	}

	private function comparePath(string $path): string {
		$path = str_replace('\\', '/', rtrim($path, '\\/'));

		return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
	}

	private function normalize(string $path): string {
		return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), '\\/');
	}

	private function absolutePath(string $path): string {
		if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/]{1,2})~', $path)) {
			$path = getcwd() . DIRECTORY_SEPARATOR . $path;
		}

		$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$prefix = '';

		if (preg_match('/^[A-Za-z]:/', $path, $match)) {
			$prefix = strtoupper($match[0]) . DIRECTORY_SEPARATOR;
			$path = substr($path, 2);
		} elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
			$prefix = DIRECTORY_SEPARATOR;
		}

		$parts = [];

		foreach (explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR)) as $part) {
			if ($part === '' || $part === '.') {
				continue;
			}

			if ($part === '..') {
				array_pop($parts);
				continue;
			}

			$parts[] = $part;
		}

		return $this->normalize($prefix . implode(DIRECTORY_SEPARATOR, $parts));
	}
}

function deploymentMain(array $argv, ?DeploymentFilesystem $filesystem = null): int {
	$options = getopt('', ['artifact-dir:', 'storage-dir:', 'confirm-quiescent', 'dry-run', 'keep-backup']);

	if (!isset($options['artifact-dir'], $options['storage-dir'])
		|| !is_string($options['artifact-dir']) || $options['artifact-dir'] === ''
		|| !is_string($options['storage-dir']) || $options['storage-dir'] === '') {
		fwrite(STDERR, "Usage: php system/build/deploy-vendor.php --artifact-dir=<path> --storage-dir=<path> --confirm-quiescent [--dry-run] [--keep-backup]\n");
		return 2;
	}

	try {
		$deployment = new VendorDeployment($filesystem ?? new DeploymentFilesystem());
		$result = $deployment->deploy(
			$options['artifact-dir'],
			$options['storage-dir'],
			isset($options['dry-run']),
			isset($options['keep-backup']),
			isset($options['confirm-quiescent'])
		);

		echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

		return 0;
	} catch (DeploymentException $exception) {
		fwrite(STDERR, json_encode([
			'status' => $exception->status,
			'error' => $exception->getMessage()
		], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

		return 1;
	} catch (Throwable $exception) {
		fwrite(STDERR, json_encode([
			'status' => 'DEPLOYMENT_FAILED',
			'error' => $exception->getMessage()
		], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

		return 1;
	}
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	exit(deploymentMain($argv));
}
