<?php

declare(strict_types=1);

const PACKAGE_NAME = 'scssphp/scssphp';
const PACKAGE_VERSION = 'v1.13.0';
const PACKAGE_REFERENCE = '63d1157457e5554edf00b0c1fabab4c1511d2520';
const PATCH_SOURCE = 'OpenCart 8c9afc5a137aa22739c0a9479e70fa86f77d22ad';

$files = [
    'src/Compiler.php' => [
        'base' => '34627a4fb7181c233b769e9941100c38d38ad52348ed2b1a3c292e1f09540e92',
        'target' => '2e86289ec7fdc2e10975abb2c8835287c9cb2eb833e936921293e487161d94cf',
        'replacements' => [
            'protected function multiplyMedia(Environment $env = null, $childQueries = null)' => 'protected function multiplyMedia(?Environment $env = null, $childQueries = null)',
            'protected function pushEnv(Block $block = null)' => 'protected function pushEnv(?Block $block = null)',
            'protected function set($name, $value, $shadow = false, Environment $env = null, $valueUnreduced = null)' => 'protected function set($name, $value, $shadow = false, ?Environment $env = null, $valueUnreduced = null)',
            'public function get($name, $shouldThrow = true, Environment $env = null, $unreduced = false)' => 'public function get($name, $shouldThrow = true, ?Environment $env = null, $unreduced = false)',
            'protected function has($name, Environment $env = null)' => 'protected function has($name, ?Environment $env = null)',
        ],
    ],
    'src/Formatter.php' => [
        'base' => 'f335014486cf1b2ee7cc3d2512090cd9fec4c87805c52015e390c9b9822e2f0d',
        'target' => 'e614238cf2c64b763d4ed80d36ec47f49d7af6129a4dc3312df151b26624b9b1',
        'replacements' => [
            'public function format(OutputBlock $block, SourceMapGenerator $sourceMapGenerator = null)' => 'public function format(OutputBlock $block, ?SourceMapGenerator $sourceMapGenerator = null)',
        ],
    ],
    'src/Parser.php' => [
        'base' => '15b54c210fce738531d92026deab0dd37f8a8f01d30d05b8a3115470f0f32cf4',
        'target' => '4730ab987128239c837e0f67ce5a3cd7ad63a75ce7ea2ccf10632cefef4fa87f',
        'replacements' => [
            "public function __construct(\$sourceName, \$sourceIndex = 0, \$encoding = 'utf-8', Cache \$cache = null, \$cssOnly = false, LoggerInterface \$logger = null)" => "public function __construct(\$sourceName, \$sourceIndex = 0, \$encoding = 'utf-8', ?Cache \$cache = null, \$cssOnly = false, ?LoggerInterface \$logger = null)",
        ],
    ],
    'src/Warn.php' => [
        'base' => '824ba35e262ac7ae155b3dc2635292039d42f29e3adc48bf1431ea5636323d98',
        'target' => '2e279b292430c952f645aeaf2b5019995c007b183b774c7c2c4cdbb2982a1948',
        'replacements' => [
            'public static function setCallback(callable $callback = null)' => 'public static function setCallback(?callable $callback = null)',
        ],
    ],
    'src/Node/Number.php' => [
        'base' => '01bafd5e5ba91dfb32b484475152d8e14e1856d045ce3ecb2c0fab0b52eb7544',
        'target' => '460cd6327c29a4d747cd7783fa6596815bf99815cc49517a0619401e952fcc21',
        'replacements' => [
            'public function output(Compiler $compiler = null)' => 'public function output(?Compiler $compiler = null)',
        ],
    ],
];

function fail(string $message): never {
    fwrite(STDERR, "ERROR: $message\n");
    exit(1);
}

$options = getopt('', ['vendor-dir:', 'check']);
$vendorDir = $options['vendor-dir'] ?? null;
$check = array_key_exists('check', $options);

if (!is_string($vendorDir) || $vendorDir === '') {
    fail('Usage: php build/apply-vendor-patches.php --vendor-dir=<path> [--check]');
}

$vendorReal = realpath($vendorDir);
if ($vendorReal === false || !is_dir($vendorReal)) {
    fail("Vendor directory does not exist: $vendorDir");
}

$installedPath = $vendorReal . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
$installed = json_decode((string) @file_get_contents($installedPath), true);
if (!is_array($installed)) {
    fail("Cannot read Composer package metadata: $installedPath");
}

$packages = isset($installed['packages']) && is_array($installed['packages']) ? $installed['packages'] : $installed;
$package = null;
foreach ($packages as $candidate) {
    if (is_array($candidate) && ($candidate['name'] ?? null) === PACKAGE_NAME) {
        $package = $candidate;
        break;
    }
}

if ($package === null) {
    fail('Package identity mismatch: scssphp/scssphp is not installed');
}

$sourceReference = $package['source']['reference'] ?? null;
$distReference = $package['dist']['reference'] ?? null;
if (
    ($package['version'] ?? null) !== PACKAGE_VERSION
    || ($sourceReference === null && $distReference === null)
    || ($sourceReference !== null && $sourceReference !== PACKAGE_REFERENCE)
    || ($distReference !== null && $distReference !== PACKAGE_REFERENCE)
) {
    fail('Package identity mismatch: expected scssphp/scssphp v1.13.0 at reference ' . PACKAGE_REFERENCE);
}

$packageRoot = $vendorReal . DIRECTORY_SEPARATOR . 'scssphp' . DIRECTORY_SEPARATOR . 'scssphp';
$states = [];
$contents = [];
foreach ($files as $relative => $spec) {
    $path = $packageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $content = @file_get_contents($path);
    if ($content === false) {
        fail("Cannot read package source file: $relative");
    }

    $hash = hash('sha256', $content);
    $states[$relative] = $hash === $spec['base'] ? 'BASE' : ($hash === $spec['target'] ? 'TARGET' : 'UNKNOWN');
    $contents[$relative] = $content;
}

$uniqueStates = array_values(array_unique($states));
if (count($uniqueStates) !== 1 || $uniqueStates[0] === 'UNKNOWN') {
    foreach ($states as $relative => $state) {
        fwrite(STDERR, "$relative: $state\n");
    }
    fail('Patch source drift detected; all files must be exact BASE or exact TARGET content');
}

if ($uniqueStates[0] === 'TARGET') {
    echo "PATCH_ALREADY_APPLIED\n";
    exit(0);
}

if ($check) {
    echo "PATCH_REQUIRED\n";
    exit(0);
}

$patched = [];
foreach ($files as $relative => $spec) {
    $content = $contents[$relative];
    foreach ($spec['replacements'] as $before => $after) {
        if (substr_count($content, $before) !== 1) {
            fail("Expected source signature does not occur exactly once: $relative");
        }
        $content = str_replace($before, $after, $content);
    }
    if (hash('sha256', $content) !== $spec['target']) {
        fail("Generated target hash mismatch: $relative");
    }
    $patched[$relative] = $content;
}

$transaction = bin2hex(random_bytes(8));
$prepared = [];
$replaced = [];
try {
    foreach ($patched as $relative => $content) {
        $path = $packageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $temp = $path . '.opencore-' . $transaction . '.tmp';
        if (@file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException("Cannot write temporary file: $relative");
        }
        $permissions = fileperms($path);
        if ($permissions !== false) {
            @chmod($temp, $permissions & 0777);
        }
        $prepared[$relative] = [$path, $temp, $path . '.opencore-' . $transaction . '.bak'];
    }

    foreach ($prepared as $relative => [$path, $temp, $backup]) {
        if (!rename($path, $backup)) {
            throw new RuntimeException("Cannot create rollback file: $relative");
        }
        if (!rename($temp, $path)) {
            rename($backup, $path);
            throw new RuntimeException("Cannot install patched file: $relative");
        }
        $replaced[$relative] = [$path, $backup];
    }
} catch (Throwable $exception) {
    foreach (array_reverse($replaced) as [$path, $backup]) {
        if (is_file($backup)) {
            @unlink($path);
            @rename($backup, $path);
        }
    }
    foreach ($prepared as [$path, $temp, $backup]) {
        @unlink($temp);
        if (is_file($backup) && !is_file($path)) {
            @rename($backup, $path);
        }
    }
    fail($exception->getMessage());
}

foreach ($files as $relative => $spec) {
    $path = $packageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (@hash_file('sha256', $path) !== $spec['target']) {
        fail("Installed target hash mismatch: $relative");
    }
}

$cleanupFailures = [];
foreach ($replaced as [$path, $backup]) {
    if (!@unlink($backup)) {
        $cleanupFailures[] = $backup;
    }
}

if ($cleanupFailures) {
    fail('Patch committed with valid TARGET files, but rollback-file cleanup failed: ' . implode(', ', $cleanupFailures));
}

echo 'PATCH_APPLIED (' . PATCH_SOURCE . ")\n";
