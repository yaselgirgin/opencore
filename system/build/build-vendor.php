<?php
declare(strict_types=1);

const COMPOSER_VERSION = '2.10.2';
const MANIFEST_SCHEMA = 'opencore-vendor-artifact/v1';

function fail(string $message): never {
	fwrite(STDERR, "ERROR: $message\n");
	exit(1);
}

function removeTree(string $path): void {
	if (!file_exists($path) && !is_link($path)) {
		return;
	}

	if (is_file($path) || is_link($path)) {
		@unlink($path);
		return;
	}

	$items = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
	foreach ($items as $item) {
		removeTree($item->getPathname());
	}
	@rmdir($path);
}

function absolutePath(string $path): string {
	if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/]{1,2})~', $path)) {
		$path = getcwd() . DIRECTORY_SEPARATOR . $path;
	}

	$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
	$prefix = '';
	if (preg_match('~^[A-Za-z]:~', $path, $match)) {
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

	return $prefix . implode(DIRECTORY_SEPARATOR, $parts);
}

function run(array $command, string $cwd, array $environment): string {
	$descriptor = [
		0 => ['file', 'NUL', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	];
	$process = proc_open($command, $descriptor, $pipes, $cwd, $environment, ['bypass_shell' => true]);
	if (!is_resource($process)) {
		throw new RuntimeException('Cannot start child process: ' . (string) ($command[0] ?? 'unknown'));
	}
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exitCode = proc_close($process);
	if ($exitCode !== 0) {
		throw new RuntimeException('Child process failed (' . $exitCode . '): ' . trim($stderr . "\n" . $stdout));
	}
	return trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
}

function composerCommand(string $composer, ?string $caFile, array $arguments): array {
	$command = str_ends_with(strtolower($composer), '.phar')
		? [PHP_BINARY]
		: [$composer];

	if ($command[0] === PHP_BINARY && $caFile !== null) {
		$command[] = '-d';
		$command[] = 'curl.cainfo=' . $caFile;
		$command[] = '-d';
		$command[] = 'openssl.cafile=' . $caFile;
	}
	if ($command[0] === PHP_BINARY) {
		$command[] = $composer;
	}

	return array_merge($command, $arguments);
}

function readJson(string $path): array {
	$content = @file_get_contents($path);
	if ($content === false) {
		throw new RuntimeException("Cannot read JSON file: $path");
	}
	$data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($data)) {
		throw new RuntimeException("JSON root must be an object: $path");
	}
	return $data;
}

function packageGraph(array $packages): array {
	$graph = [];
	foreach ($packages as $package) {
		if (!is_array($package) || !isset($package['name'])) {
			throw new RuntimeException('Package metadata contains an invalid entry');
		}
		$name = (string) $package['name'];
		$graph[$name] = [
			'name'             => $name,
			'version'          => $package['version'] ?? null,
			'source_reference' => $package['source']['reference'] ?? null,
			'dist_reference'   => $package['dist']['reference'] ?? null,
			'require'          => $package['require'] ?? [],
			'autoload'         => $package['autoload'] ?? []
		];
	}
	ksort($graph, SORT_STRING);
	return $graph;
}

function verifyInstalledGraph(array $lock, string $installedPath): array {
	$installed = readJson($installedPath);
	$installedPackages = isset($installed['packages']) && is_array($installed['packages'])
		? $installed['packages']
		: $installed;
	$expected = packageGraph($lock['packages']);
	$actual = packageGraph($installedPackages);
	if ($expected !== $actual) {
		$missing = array_diff_key($expected, $actual);
		$extra = array_diff_key($actual, $expected);
		throw new RuntimeException(
			'Installed package graph differs from composer.lock'
			. ($missing ? '; missing: ' . implode(', ', array_keys($missing)) : '')
			. ($extra ? '; extra: ' . implode(', ', array_keys($extra)) : '')
		);
	}
	return $actual;
}

function createSmokeScript(string $path, string $autoloadPath): void {
	$code = <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1];
$required = [
	'Twig\\Environment',
	'Twig\\Loader\\ArrayLoader'
];
foreach ($required as $symbol) {
	if (!class_exists($symbol) && !interface_exists($symbol)) {
		throw new RuntimeException("Autoload failed: $symbol");
	}
}
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader(['test' => 'Hello {{ name }}']));
if ($twig->render('test', ['name' => 'OpenCore']) !== 'Hello OpenCore') {
	throw new RuntimeException('Twig functional smoke failed');
}
echo "AUTOLOAD_SMOKE_OK\n";
PHP;
	if (file_put_contents($path, $code) === false) {
		throw new RuntimeException('Cannot create isolated autoload smoke script');
	}
	$environment = getenv();
	$output = run([PHP_BINARY, $path, $autoloadPath], dirname($path), is_array($environment) ? $environment : []);
	if (!str_contains($output, 'AUTOLOAD_SMOKE_OK')) {
		throw new RuntimeException('Isolated autoload smoke did not report success');
	}
}

function inventory(string $vendor): array {
	$rows = [];
	$totalBytes = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($vendor, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if (!$file->isFile()) {
			continue;
		}
		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($vendor) + 1));
		$size = $file->getSize();
		$rows[$relative] = ['path' => $relative, 'size' => $size, 'sha256' => hash_file('sha256', $file->getPathname())];
		$totalBytes += $size;
	}
	uksort($rows, 'strcmp');
	$canonical = '';
	foreach ($rows as $row) {
		$canonical .= $row['path'] . "\0" . $row['size'] . "\0" . $row['sha256'] . "\n";
	}
	return [array_values($rows), $totalBytes, hash('sha256', $canonical)];
}

$options = getopt('', ['artifact-dir:', 'composer:', 'ca-file:']);
if (!isset($options['artifact-dir']) || !is_string($options['artifact-dir']) || $options['artifact-dir'] === '') {
	fail('Usage: php system/build/build-vendor.php --artifact-dir=<path> [--composer=<executable-or-phar>] [--ca-file=<path>]');
}

$repo = realpath(dirname(__DIR__, 2));
if ($repo === false) {
	fail('Cannot resolve repository root');
}
$artifact = absolutePath($options['artifact-dir']);
$composer = isset($options['composer']) && is_string($options['composer']) ? $options['composer'] : 'composer';
$caFile = isset($options['ca-file']) && is_string($options['ca-file']) ? absolutePath($options['ca-file']) : null;
$repoPrefix = rtrim(strtolower($repo), '\\/') . DIRECTORY_SEPARATOR;
if (strtolower($artifact) === strtolower($repo) || str_starts_with(strtolower($artifact) . DIRECTORY_SEPARATOR, $repoPrefix)) {
	fail('Artifact directory must be outside the repository');
}
if (file_exists($artifact) || is_link($artifact)) {
	fail("Artifact directory already exists; refusing to overwrite: $artifact");
}
$parent = realpath(dirname($artifact));
if ($parent === false || !is_dir($parent) || !is_writable($parent)) {
	fail('Artifact parent directory must already exist and be writable');
}
if ($caFile !== null && (!is_file($caFile) || !is_readable($caFile))) {
	fail('CA file does not exist or is not readable');
}

$sources = [
	'composer.json' => $repo . DIRECTORY_SEPARATOR . 'composer.json',
	'composer.lock' => $repo . DIRECTORY_SEPARATOR . 'composer.lock'
];
foreach ($sources as $label => $path) {
	if (!is_file($path) || !is_readable($path)) {
		fail("Required source is missing or unreadable ($label): $path");
	}
}

try {
	$composerJson = readJson($sources['composer.json']);
	$lock = readJson($sources['composer.lock']);
} catch (Throwable $exception) {
	fail($exception->getMessage());
}
if (!isset($lock['content-hash']) || !is_string($lock['content-hash']) || $lock['content-hash'] === '') {
	fail('composer.lock has no content-hash');
}
if (!isset($lock['packages']) || !is_array($lock['packages'])) {
	fail('composer.lock has no production packages list');
}
$workspace = $parent . DIRECTORY_SEPARATOR . '.opencore-vendor-build-' . bin2hex(random_bytes(8));
$ready = $workspace . DIRECTORY_SEPARATOR . 'artifact-ready';
$work = $workspace . DIRECTORY_SEPARATOR . 'work';
$inheritedEnvironment = getenv();
$environment = array_merge(is_array($inheritedEnvironment) ? $inheritedEnvironment : [], [
	'COMPOSER_HOME' => $workspace . DIRECTORY_SEPARATOR . 'composer-home',
	'COMPOSER_CACHE_DIR' => $workspace . DIRECTORY_SEPARATOR . 'composer-cache'
]);
if ($caFile !== null) {
	$environment['COMPOSER_CAFILE'] = $caFile;
}

try {
	if (!mkdir($work, 0700, true) || !mkdir($ready, 0700, true)) {
		throw new RuntimeException('Cannot create disposable workspace');
	}
	copy($sources['composer.json'], $work . DIRECTORY_SEPARATOR . 'composer.json') || throw new RuntimeException('Cannot copy composer.json');
	copy($sources['composer.lock'], $work . DIRECTORY_SEPARATOR . 'composer.lock') || throw new RuntimeException('Cannot copy composer.lock');
	$lockHashBefore = hash_file('sha256', $work . DIRECTORY_SEPARATOR . 'composer.lock');

	$versionOutput = run(composerCommand($composer, $caFile, ['--version', '--no-ansi']), $work, $environment);
	if (!preg_match('/Composer version\s+([0-9]+(?:\.[0-9]+){2})\b/', $versionOutput, $match) || $match[1] !== COMPOSER_VERSION) {
		throw new RuntimeException('Composer version mismatch: expected ' . COMPOSER_VERSION . ', got ' . $versionOutput);
	}

	run(composerCommand($composer, $caFile, [
		'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-plugins', '--no-scripts', '--no-progress', '--no-ansi'
	]), $work, $environment);
	if (hash_file('sha256', $work . DIRECTORY_SEPARATOR . 'composer.lock') !== $lockHashBefore) {
		throw new RuntimeException('composer.lock changed during install');
	}

	$installedPath = $work . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
	$packages = verifyInstalledGraph($lock, $installedPath);

	$autoload = $work . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
	if (!is_file($autoload) || !is_dir($work . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer')) {
		throw new RuntimeException('Composer autoload metadata is incomplete');
	}
	createSmokeScript($workspace . DIRECTORY_SEPARATOR . 'autoload-smoke.php', $autoload);

	if (!rename($work . DIRECTORY_SEPARATOR . 'vendor', $ready . DIRECTORY_SEPARATOR . 'vendor')) {
		throw new RuntimeException('Cannot prepare vendor artifact for publication');
	}
	[$files, $totalBytes, $inventoryHash] = inventory($ready . DIRECTORY_SEPARATOR . 'vendor');
	$packageSummary = [];
	foreach ($packages as $package) {
		$packageSummary[] = [
			'name' => $package['name'],
			'version' => $package['version'],
			'source_reference' => $package['source_reference'],
			'dist_reference' => $package['dist_reference']
		];
	}
	$manifest = [
		'schema' => MANIFEST_SCHEMA,
		'composer_version' => COMPOSER_VERSION,
		'php_version' => PHP_VERSION,
		'sources' => [
			'composer_json_sha256' => hash_file('sha256', $sources['composer.json']),
			'composer_lock_sha256' => hash_file('sha256', $sources['composer.lock'])
		],
		'inventory' => [
			'algorithm' => 'sha256(path NUL decimal-size NUL lowercase-sha256 LF), paths bytewise-sorted',
			'file_count' => count($files),
			'total_bytes' => $totalBytes,
			'sha256' => $inventoryHash,
			'files' => $files
		],
		'packages' => $packageSummary
	];
	$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	if (file_put_contents($ready . DIRECTORY_SEPARATOR . 'vendor-manifest.json', $json) === false) {
		throw new RuntimeException('Cannot write vendor-manifest.json');
	}
	readJson($ready . DIRECTORY_SEPARATOR . 'vendor-manifest.json');
	if (file_exists($artifact) || !rename($ready, $artifact)) {
		throw new RuntimeException('Atomic artifact publication failed');
	}
	removeTree($workspace);
	echo json_encode([
		'status' => 'ARTIFACT_READY',
		'packages' => count($packages),
		'files' => count($files),
		'bytes' => $totalBytes,
		'inventory_sha256' => $inventoryHash
	], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
	removeTree($workspace);
	fail($exception->getMessage());
}
