<?php
declare(strict_types=1);

const CONTRACT_VERSION = 1;
const PROTECTED_PATHS_VERSION = 1;

function fail(string $message): never {
	fwrite(STDERR, 'Release build failed: ' . $message . PHP_EOL);
	exit(1);
}

function normalizeVersion(string $version): ?string {
	if (!preg_match('/^(\d{4})\.(0[1-9]|1[0-2])\.([1-9]\d*)$/', $version, $matches)) {
		return null;
	}

	return $matches[1] . '.' . $matches[2] . '.' . $matches[3];
}

function compareVersions(string $first, string $second): int {
	return version_compare($first, $second);
}

function normalizePath(string $path): string {
	$path = str_replace('\\', '/', $path);

	if ($path === '' || str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path) || str_contains($path, "\0")) {
		throw new RuntimeException('Invalid release path: ' . $path);
	}

	$parts = explode('/', $path);
	foreach ($parts as $part) {
		if ($part === '' || $part === '.' || $part === '..') {
			throw new RuntimeException('Invalid release path: ' . $path);
		}
	}

	return implode('/', $parts);
}

function isProtectedPath(string $path): bool {
	$key = strtolower($path);

	return $key === 'config.php'
		|| $key === 'ocadmin/config.php'
		|| $key === '.env'
		|| str_starts_with($key, '.env.')
		|| $key === '.git'
		|| str_starts_with($key, '.git/')
		|| $key === 'storage'
		|| str_starts_with($key, 'storage/')
		|| $key === 'ui-sample'
		|| str_starts_with($key, 'ui-sample/')
		|| $key === 'build/releases'
		|| str_starts_with($key, 'build/releases/');
}

function isRepositoryOnlyPath(string $path): bool {
	$key = strtolower($path);

	return $key === '.gitattributes'
		|| $key === '.gitignore'
		|| $key === 'agents.md'
		|| $key === 'docs'
		|| str_starts_with($key, 'docs/')
		|| $key === 'tools/release'
		|| str_starts_with($key, 'tools/release/')
		|| $key === 'ui-sample'
		|| str_starts_with($key, 'ui-sample/');
}

function run(array $command, string $working_directory, ?array $environment = null): string {
	$descriptors = [
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	];
	$process = proc_open($command, $descriptors, $pipes, $working_directory, $environment, ['bypass_shell' => true]);

	if (!is_resource($process)) {
		throw new RuntimeException('Process could not be started.');
	}

	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit_code = proc_close($process);

	if ($exit_code !== 0) {
		throw new RuntimeException(trim($error ?: $output) ?: 'Process failed with exit code ' . $exit_code . '.');
	}

	return (string)$output;
}

function optionList(array $options, string $name): array {
	if (!array_key_exists($name, $options)) {
		return [];
	}

	return is_array($options[$name]) ? $options[$name] : [$options[$name]];
}

function createDirectory(string $directory): void {
	if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
		throw new RuntimeException('Directory could not be created: ' . $directory);
	}
}

function removeTree(string $directory, string $allowed_root): void {
	if (!is_dir($directory)) {
		return;
	}

	$root = rtrim(str_replace('\\', '/', (string)realpath($allowed_root)), '/') . '/';
	$real = str_replace('\\', '/', (string)realpath($directory));

	if ($real === '' || !str_starts_with($real . '/', $root) || is_link($directory)) {
		throw new RuntimeException('Unsafe build cleanup path.');
	}

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $entry) {
		if ($entry->isLink()) {
			throw new RuntimeException('Build workspace contains a link.');
		}

		$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
	}

	if (!rmdir($directory)) {
		throw new RuntimeException('Build workspace could not be removed.');
	}
}

function inventory(string $root): array {
	$root = rtrim(str_replace('\\', '/', (string)realpath($root)), '/') . '/';
	$files = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);

	foreach ($iterator as $entry) {
		if ($entry->isLink() || !$entry->isFile()) {
			throw new RuntimeException('Payload contains an unsupported entry.');
		}

		$real = str_replace('\\', '/', (string)$entry->getRealPath());
		if (!str_starts_with($real, $root)) {
			throw new RuntimeException('Payload path escapes its root.');
		}

		$path = normalizePath(substr($real, strlen($root)));
		$key = strtolower($path);
		if (isset($files[$key])) {
			throw new RuntimeException('Payload contains a case-colliding path: ' . $path);
		}

		$files[$key] = [
			'path'   => $path,
			'size'   => $entry->getSize(),
			'sha256' => hash_file('sha256', $entry->getPathname())
		];
	}

	ksort($files, SORT_STRING);

	return array_values($files);
}

function treeIdentity(array $inventory): string {
	$files = [];
	foreach ($inventory as $entry) {
		$files[strtolower($entry['path'])] = $entry['path'] . "\0" . $entry['size'] . "\0" . $entry['sha256'];
	}
	ksort($files, SORT_STRING);

	return hash('sha256', implode("\n", $files));
}

function validateComposerInstall(string $lock_file, string $vendor): void {
	$lock = json_decode((string)file_get_contents($lock_file), true, 512, JSON_THROW_ON_ERROR);
	$installed_file = $vendor . '/composer/installed.json';
	if (!is_file($installed_file) || !is_file($vendor . '/autoload.php') || !is_file($vendor . '/composer/installed.php') || !is_file($vendor . '/composer/InstalledVersions.php')) {
		throw new RuntimeException('Composer vendor runtime is incomplete.');
	}

	$installed = json_decode((string)file_get_contents($installed_file), true, 512, JSON_THROW_ON_ERROR);
	$installed_packages = isset($installed['packages']) ? $installed['packages'] : $installed;
	$expected = [];
	foreach (($lock['packages'] ?? []) as $package) {
		$expected[strtolower((string)$package['name'])] = [
			'version'   => (string)$package['version'],
			'reference' => (string)($package['dist']['reference'] ?? $package['source']['reference'] ?? '')
		];
	}

	$actual = [];
	foreach ($installed_packages as $package) {
		$name = strtolower((string)($package['name'] ?? ''));
		if ($name !== '') {
			$actual[$name] = [
				'version'   => (string)($package['version'] ?? ''),
				'reference' => (string)($package['dist']['reference'] ?? $package['source']['reference'] ?? '')
			];
		}
	}

	ksort($expected);
	ksort($actual);
	if ($expected !== $actual) {
		throw new RuntimeException('Installed Composer package set does not match composer.lock.');
	}

	$probe = <<<'PHP'
$vendor = $argv[1];
$loader = require $vendor . '/autoload.php';
$twig = (new ReflectionClass(Twig\Environment::class))->getFileName();
if (!$loader instanceof Composer\Autoload\ClassLoader || !is_string($twig) || str_replace('\\', '/', $twig) !== str_replace('\\', '/', $vendor . '/twig/twig/src/Environment.php')) exit(1);
PHP;
	run([PHP_BINARY, '-r', $probe, $vendor], dirname($vendor));
}

function validateDatabaseIdentifiers(array $identifiers, array $sources, string $target): void {
	$pattern = '/^(\d{4}\.(?:0[1-9]|1[0-2])\.[1-9]\d*)\.(00[1-9]|0[1-9][0-9]|[1-9][0-9]{2})$/';
	foreach ($sources as $source) {
		$previous_version = null;
		$previous_sequence = 0;
		$seen = [];
		foreach ($identifiers as $identifier) {
			if (!preg_match($pattern, $identifier, $matches) || compareVersions($matches[1], $source) <= 0 || compareVersions($matches[1], $target) > 0 || isset($seen[$identifier])) {
				throw new RuntimeException('Database update identifier is invalid: ' . $identifier);
			}
			$sequence = (int)$matches[2];
			if ($previous_version !== null && (compareVersions($matches[1], $previous_version) < 0 || ($matches[1] === $previous_version && $sequence <= $previous_sequence))) {
				throw new RuntimeException('Database update identifiers are not ordered.');
			}
			$seen[$identifier] = true;
			$previous_version = $matches[1];
			$previous_sequence = $sequence;
		}
	}
}

function addZipFile(ZipArchive $zip, string $archive_path, string $source, int $timestamp): void {
	if (!$zip->addFile($source, $archive_path)) {
		throw new RuntimeException('ZIP entry could not be added: ' . $archive_path);
	}
	if (method_exists($zip, 'setMtimeName')) {
		$zip->setMtimeName($archive_path, $timestamp);
	}
}

$options = getopt('', ['source-version:', 'remove:', 'database-update:', 'composer:', 'output:', 'allow-dirty', 'help']);
if (isset($options['help'])) {
	echo "Usage: php tools/release/build.php --source-version=<YYYY.MM.RELEASE> [--source-version=...] [--remove=<path>] [--database-update=<version.NNN>] [--composer=<composer.phar>] [--output=<directory>] [--allow-dirty]\n";
	exit(0);
}

$root = dirname(__DIR__, 2);
$workspace = null;
$temporary_zip = null;
$temporary_checksum = null;

try {
	$version_file = $root . '/system/version.php';
	$version_source = is_file($version_file) ? file_get_contents($version_file) : false;
	if ($version_source === false || !preg_match("/define\('VERSION',\s*'(\d{4}\.\d{2}\.\d+)'\);/", $version_source, $matches)) {
		throw new RuntimeException('Canonical version source is missing or invalid.');
	}
	$version = normalizeVersion($matches[1]);
	if ($version === null) {
		throw new RuntimeException('Canonical OpenCore version is invalid.');
	}

	$sources = optionList($options, 'source-version');
	if (!$sources) {
		throw new RuntimeException('At least one --source-version is required.');
	}
	$sources = array_values(array_unique($sources));
	foreach ($sources as $source) {
		if (normalizeVersion($source) !== $source || compareVersions($source, $version) >= 0) {
			throw new RuntimeException('Compatible source version is invalid: ' . $source);
		}
	}
	sort($sources, SORT_STRING);

	$removals = optionList($options, 'remove');
	$removal_keys = [];
	foreach ($removals as &$removal) {
		$removal = normalizePath($removal);
		$key = strtolower($removal);
		if (isProtectedPath($removal) || isset($removal_keys[$key])) {
			throw new RuntimeException('Application removal is invalid: ' . $removal);
		}
		$removal_keys[$key] = true;
	}
	unset($removal);
	sort($removals, SORT_STRING);

	$database_updates = optionList($options, 'database-update');
	validateDatabaseIdentifiers($database_updates, $sources, $version);

	$composer_lock = $root . '/composer.lock';
	if (!is_file($composer_lock)) {
		throw new RuntimeException('composer.lock is missing.');
	}

	$status = trim(run(['git', 'status', '--porcelain'], $root));
	if ($status !== '' && !isset($options['allow-dirty'])) {
		throw new RuntimeException('Git working tree is not clean. Commit the release source or use --allow-dirty for an isolated test build.');
	}

	$tracked_output = run(['git', 'ls-files', '--cached', '-z'], $root);
	$application_paths = array_values(array_filter(explode("\0", $tracked_output), static fn(string $path): bool => $path !== ''));
	sort($application_paths, SORT_STRING);
	$application_inventory = [];
	$application_keys = [];
	foreach ($application_paths as $path) {
		$path = normalizePath($path);
		if (isRepositoryOnlyPath($path)) {
			continue;
		}
		$key = strtolower($path);
		if (isProtectedPath($path)) {
			throw new RuntimeException('Protected application path would be packaged: ' . $path);
		}
		if (isset($application_keys[$key])) {
			throw new RuntimeException('Application path collision: ' . $path);
		}
		$file = $root . '/' . $path;
		if (!is_file($file) || is_link($file)) {
			throw new RuntimeException('Application source is not a regular file: ' . $path);
		}
		$application_inventory[] = ['path' => $path, 'size' => filesize($file), 'sha256' => hash_file('sha256', $file)];
		$application_keys[$key] = true;
	}
	foreach (['composer.lock', 'system/version.php'] as $required) {
		if (!isset($application_keys[$required])) {
			throw new RuntimeException('Required application file is missing: ' . $required);
		}
	}
	foreach ($removal_keys as $key => $_) {
		if (isset($application_keys[$key])) {
			throw new RuntimeException('Removal path is also present in the application payload: ' . $key);
		}
	}

	$output = isset($options['output']) ? (string)$options['output'] : $root . '/build/releases';
	if (!preg_match('~^(?:[a-zA-Z]:[\\\\/]|/)~', $output)) {
		$output = $root . '/' . $output;
	}
	createDirectory($output);
	$output_real = (string)realpath($output);
	$workspace = $output_real . '/.work-' . bin2hex(random_bytes(8));
	createDirectory($workspace);

	$composer = isset($options['composer']) ? (string)$options['composer'] : (string)getenv('COMPOSER_PHAR');
	if ($composer === '' || !is_file($composer)) {
		throw new RuntimeException('Composer PHAR is required via --composer or COMPOSER_PHAR.');
	}
	$vendor = $workspace . '/vendor';
	$composer_home = $workspace . '/composer-home';
	$temporary = $workspace . '/temp';
	createDirectory($composer_home);
	createDirectory($temporary);
	$process_environment = getenv();
	$environment = array_merge(is_array($process_environment) ? $process_environment : $_ENV, [
		'COMPOSER_VENDOR_DIR'      => $vendor,
		'COMPOSER_HOME'            => $composer_home,
		'COMPOSER_ALLOW_SUPERUSER' => '1',
		'TEMP'                     => $temporary,
		'TMP'                      => $temporary,
		'TMPDIR'                   => $temporary
	]);
	run([PHP_BINARY, $composer, 'install', '--working-dir=' . $root, '--no-dev', '--prefer-dist', '--optimize-autoloader', '--classmap-authoritative', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins'], $root, $environment);
	validateComposerInstall($composer_lock, $vendor);
	$vendor_inventory = inventory($vendor);
	$vendor_identity = treeIdentity($vendor_inventory);

	$timestamp = getenv('SOURCE_DATE_EPOCH') !== false ? (int)getenv('SOURCE_DATE_EPOCH') : time();
	if ($timestamp < 1) {
		throw new RuntimeException('SOURCE_DATE_EPOCH is invalid.');
	}
	$built_at = gmdate('c', $timestamp);
	$manifest = [
		'contract_version'          => CONTRACT_VERSION,
		'protected_paths_version'   => PROTECTED_PATHS_VERSION,
		'application'               => 'opencore',
		'version'                   => $version,
		'release'                   => ['tag' => 'v' . $version, 'built_at' => $built_at],
		'compatible_source_versions'=> $sources,
		'application_files'         => $application_inventory,
		'application_removals'      => $removals,
		'composer_lock_sha256'      => hash_file('sha256', $composer_lock),
		'vendor'                    => ['included' => true, 'identity' => $vendor_identity, 'files' => $vendor_inventory],
		'database'                  => ['required' => (bool)$database_updates, 'updates' => $database_updates]
	];
	$manifest_json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

	$filename = 'opencore-' . $version . '.zip';
	$checksum_filename = $filename . '.sha256';
	$final_zip = $output_real . '/' . $filename;
	$final_checksum = $output_real . '/' . $checksum_filename;
	if (file_exists($final_zip) || file_exists($final_checksum)) {
		throw new RuntimeException('Release output already exists.');
	}
	$temporary_zip = $output_real . '/.' . $filename . '.tmp-' . bin2hex(random_bytes(4));
	$temporary_checksum = $output_real . '/.' . $checksum_filename . '.tmp-' . bin2hex(random_bytes(4));
	$zip = new ZipArchive();
	if ($zip->open($temporary_zip, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
		throw new RuntimeException('Release ZIP could not be created.');
	}
	if (!$zip->addFromString('manifest.json', $manifest_json)) {
		throw new RuntimeException('Manifest could not be added to ZIP.');
	}
	if (method_exists($zip, 'setMtimeName')) {
		$zip->setMtimeName('manifest.json', $timestamp);
	}
	foreach ($application_inventory as $entry) {
		addZipFile($zip, 'payload/application/' . $entry['path'], $root . '/' . $entry['path'], $timestamp);
	}
	foreach ($vendor_inventory as $entry) {
		addZipFile($zip, 'payload/vendor/' . $entry['path'], $vendor . '/' . $entry['path'], $timestamp);
	}
	if (!$zip->close() || !is_file($temporary_zip)) {
		throw new RuntimeException('Release ZIP finalization failed.');
	}

	$artifact_hash = hash_file('sha256', $temporary_zip);
	if (!preg_match('/^[a-f0-9]{64}$/', $artifact_hash)) {
		throw new RuntimeException('Release ZIP hash failed.');
	}
	if (file_put_contents($temporary_checksum, $artifact_hash . '  ' . $filename . "\n", LOCK_EX) === false) {
		throw new RuntimeException('Checksum sidecar could not be written.');
	}

	require_once $root . '/system/engine/registry.php';
	require_once $root . '/system/engine/model.php';
	require_once $root . '/ocadmin/model/tool/upgrade.php';
	$updater = new Opencart\Admin\Model\Tool\Upgrade(new Opencart\System\Engine\Registry());
	$staging = $workspace . '/staging/';
	$updater->extractArchive($temporary_zip, $staging);
	$validated = $updater->validateStaging($staging, $version, $sources[0]);
	if ($validated !== $manifest) {
		throw new RuntimeException('Updater staging altered the manifest contract.');
	}

	if (!rename($temporary_zip, $final_zip)) {
		throw new RuntimeException('Release ZIP could not be activated.');
	}
	$temporary_zip = null;
	if (!rename($temporary_checksum, $final_checksum)) {
		@unlink($final_zip);
		throw new RuntimeException('Release checksum could not be activated.');
	}
	$temporary_checksum = null;

	echo json_encode([
		'success'                   => true,
		'version'                   => $version,
		'artifact'                  => $final_zip,
		'artifact_size'             => filesize($final_zip),
		'artifact_sha256'           => $artifact_hash,
		'checksum'                  => $final_checksum,
		'application_file_count'    => count($application_inventory),
		'vendor_file_count'         => count($vendor_inventory),
		'application_removal_count' => count($removals),
		'database_required'         => (bool)$database_updates,
		'database_updates'          => $database_updates,
		'updater_staging'           => 'ACCEPTED'
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $throwable) {
	if ($temporary_zip && is_file($temporary_zip)) @unlink($temporary_zip);
	if ($temporary_checksum && is_file($temporary_checksum)) @unlink($temporary_checksum);
	if ($workspace && is_dir($workspace)) {
		try {
			removeTree($workspace, dirname($workspace));
		} catch (Throwable $cleanup) {
			fwrite(STDERR, 'Build cleanup failed: ' . $cleanup->getMessage() . PHP_EOL);
		}
	}
	fail($throwable->getMessage());
} finally {
	if ($workspace && is_dir($workspace)) {
		removeTree($workspace, dirname($workspace));
	}
}
