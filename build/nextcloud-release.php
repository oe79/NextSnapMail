#!/usr/bin/env php
<?php

define('ROOT_DIR', dirname(__DIR__));
chdir(ROOT_DIR);
error_reporting(E_ALL & ~E_DEPRECATED);

$options = getopt('', [
	'source::',
	'destination::',
	'cert-dir::',
	'sign',
	'help'
]);

if (isset($options['help'])) {
	echo "Build a cleaned NextSnapMail package for the Nextcloud App Store.\n\n";
	echo "Usage:\n";
	echo "  php build/nextcloud-release.php [--source=PATH] [--destination=PATH] [--sign] [--cert-dir=PATH]\n\n";
	echo "Defaults:\n";
	echo "  --source       build/local-nextsnapmail-app/nextsnapmail\n";
	echo "  --destination  build/dist/nextcloud/<version>\n";
	echo "  --cert-dir     ~/.nextcloud/certificates\n\n";
	echo "The --sign option requires nextsnapmail.crt and nextsnapmail.key in the certificate directory.\n";
	exit(0);
}

$appId = 'nextsnapmail';
$package = json_decode(file_get_contents(ROOT_DIR . '/package.json'));
if (!$package || empty($package->version)) {
	fail('Could not read package version from package.json');
}

$version = $package->version;
$source = normalizePath($options['source'] ?? 'build/local-nextsnapmail-app/nextsnapmail');
$destination = normalizePath($options['destination'] ?? "build/dist/nextcloud/{$version}");
$certDir = normalizePath($options['cert-dir'] ?? defaultCertificateDirectory());
$sign = isset($options['sign']);

$stagingRoot = normalizePath("build/tmp/nextcloud-release");
$stagingApp = "{$stagingRoot}/{$appId}";

if (!is_dir($source)) {
	fail("Source app directory does not exist: {$source}");
}

cleanDirectory($stagingRoot);
mkdir($stagingRoot, 0777, true);
copyDirectory($source, $stagingApp);
syncRepositoryMetadata($stagingApp, $version);

validateStagedApp($stagingApp, $appId, $version);

if ($sign) {
	addNextcloudSignature($stagingApp, $appId, $certDir);
	validateStagedApp($stagingApp, $appId, $version, true);
}

is_dir($destination) || mkdir($destination, 0777, true);

$archiveBase = "{$appId}-{$version}-nextcloud" . ($sign ? '' : '-unsigned');
$gzPath = "{$destination}/{$archiveBase}.tar.gz";

@unlink($gzPath);

createTarGz($stagingRoot, $appId, $gzPath);

$archiveHash = hash_file('sha512', $gzPath);

echo "Created {$gzPath}\n";
echo "SHA512 {$archiveHash}\n";
echo $sign ? "Package is signed with appinfo/signature.json\n" : "Package is unsigned; rerun with --sign after the certificate is available\n";

function defaultCertificateDirectory(): string
{
	$home = getenv('HOME') ?: getenv('USERPROFILE');
	if (!$home) {
		return '.nextcloud/certificates';
	}

	return rtrim(str_replace('\\', '/', $home), '/') . '/.nextcloud/certificates';
}

function normalizePath(string $path): string
{
	$path = str_replace('\\', '/', $path);
	if (preg_match('/^[A-Za-z]:\//', $path) || str_starts_with($path, '/')) {
		return rtrim($path, '/');
	}

	return rtrim(str_replace('\\', '/', ROOT_DIR . '/' . $path), '/');
}

function fail(string $message): void
{
	fwrite(STDERR, "Error: {$message}\n");
	exit(1);
}

function cleanDirectory(string $directory): void
{
	$directory = normalizePath($directory);
	$allowedRoot = normalizePath('build/tmp');

	if (!str_starts_with($directory, $allowedRoot . '/')) {
		fail("Refusing to clean directory outside build/tmp: {$directory}");
	}

	if (!is_dir($directory)) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($items as $item) {
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}

	rmdir($directory);
}

function copyDirectory(string $source, string $destination): void
{
	$source = normalizePath($source);
	$destination = normalizePath($destination);

	$sourceLength = strlen($source) + 1;
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	mkdir($destination, 0777, true);

	foreach ($items as $item) {
		$relative = str_replace('\\', '/', substr($item->getPathname(), $sourceLength));
		if (shouldExclude($relative)) {
			continue;
		}

		$target = "{$destination}/{$relative}";
		if ($item->isDir()) {
			is_dir($target) || mkdir($target, 0777, true);
		} else {
			is_dir(dirname($target)) || mkdir(dirname($target), 0777, true);
			copy($item->getPathname(), $target);
		}
	}
}

function syncRepositoryMetadata(string $appPath, string $version): void
{
	copy(ROOT_DIR . '/integrations/nextcloud/nextsnapmail/appinfo/info.xml', "{$appPath}/appinfo/info.xml");
	$infoXml = file_get_contents("{$appPath}/appinfo/info.xml");
	$infoXml = preg_replace('/<version>[^<]*<\/version>/', "<version>{$version}</version>", $infoXml);
	file_put_contents("{$appPath}/appinfo/info.xml", $infoXml);

	copy(ROOT_DIR . '/CHANGELOG.md', "{$appPath}/CHANGELOG.md");
	file_put_contents("{$appPath}/VERSION", $version . "\n");
}

function shouldExclude(string $relativePath): bool
{
	$relativePath = str_replace('\\', '/', $relativePath);
	$basename = basename($relativePath);

	if ($basename === '.DS_Store' || $basename === 'Thumbs.db') {
		return true;
	}

	if ($basename === 'signature.json') {
		return true;
	}

	if (str_starts_with($relativePath, '.git/') || str_starts_with($relativePath, '.github/')) {
		return true;
	}

	if (preg_match('/\.(bak|old)$/i', $basename)) {
		return true;
	}

	return false;
}

function validateStagedApp(string $appPath, string $appId, string $version, bool $expectSignature = false): void
{
	$requiredFiles = [
		'appinfo/info.xml',
		'app/index.php',
		'app/README.md',
		'README.md',
		'CHANGELOG.md',
		'app/bundled-plugins/nextcloud/index.php'
	];

	foreach ($requiredFiles as $file) {
		if (!is_file("{$appPath}/{$file}")) {
			fail("Required file is missing from package: {$file}");
		}
	}

	$coreDirectories = glob("{$appPath}/app/snappymail/v/*", GLOB_ONLYDIR) ?: [];
	if (!$coreDirectories) {
		fail('Compiled SnappyMail core directory is missing below app/snappymail/v/');
	}

	$hasCompiledJavaScript = false;
	foreach ($coreDirectories as $coreDirectory) {
		if (is_file("{$coreDirectory}/static/js/min/app.min.js")) {
			$hasCompiledJavaScript = true;
			break;
		}
	}

	if (!$hasCompiledJavaScript) {
		fail('Compiled JavaScript is missing below app/snappymail/v/*/static/js/min/app.min.js');
	}

	$info = simplexml_load_file("{$appPath}/appinfo/info.xml");
	if (!$info) {
		fail('Could not parse appinfo/info.xml');
	}

	if ((string) $info->id !== $appId) {
		fail("appinfo/info.xml has unexpected app id: {$info->id}");
	}

	if ((string) $info->version !== $version) {
		fail("appinfo/info.xml has version {$info->version}, expected {$version}");
	}

	if ((string) $info->licence !== 'AGPL-3.0-only') {
		fail("appinfo/info.xml has unexpected licence: {$info->licence}");
	}

	$signature = "{$appPath}/appinfo/signature.json";
	if ($expectSignature && !is_file($signature)) {
		fail('Signed package is missing appinfo/signature.json');
	}

	if (!$expectSignature && is_file($signature)) {
		fail('Unsigned package unexpectedly contains appinfo/signature.json');
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($items as $item) {
		$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($appPath) + 1));
		if ($expectSignature && $relative === 'appinfo/signature.json') {
			continue;
		}
		if (shouldExclude($relative)) {
			fail("Excluded file still exists in staging directory: {$relative}");
		}
	}
}

function addNextcloudSignature(string $appPath, string $appId, string $certDir): void
{
	$certFile = "{$certDir}/{$appId}.crt";
	$keyFile = "{$certDir}/{$appId}.key";

	if (!is_file($certFile)) {
		fail("Certificate file is missing: {$certFile}");
	}

	if (!is_file($keyFile)) {
		fail("Private key file is missing: {$keyFile}");
	}

	spl_autoload_register(function($name) {
		$name = preg_replace('/^phpseclib\\\\/', '', $name);
		$file = ROOT_DIR . '/build/phpseclib/' . str_replace('\\', '/', $name) . '.php';
		if (is_file($file)) {
			require $file;
		}
	});

	$hashes = packageHashes($appPath);
	ksort($hashes);

	$cert = file_get_contents($certFile);
	$rsa = new \phpseclib\Crypt\RSA();
	$rsa->loadKey(file_get_contents($keyFile));

	$x509 = new \phpseclib\File\X509();
	$x509->loadX509($cert);
	$x509->setPrivateKey($rsa);

	$rsa->setSignatureMode(\phpseclib\Crypt\RSA::SIGNATURE_PSS);
	$rsa->setMGFHash('sha512');
	$rsa->setSaltLength(0);

	$signature = $rsa->sign(json_encode($hashes));

	file_put_contents("{$appPath}/appinfo/signature.json", json_encode([
		'hashes' => $hashes,
		'signature' => base64_encode($signature),
		'certificate' => $cert
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function packageHashes(string $appPath): array
{
	$hashes = [];
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($items as $item) {
		if (!$item->isFile()) {
			continue;
		}

		$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($appPath) + 1));
		if ($relative === 'appinfo/signature.json') {
			continue;
		}

		$hashes[$relative] = hash_file('sha512', $item->getPathname());
	}

	return $hashes;
}

function createTarGz(string $stagingRoot, string $appId, string $gzPath): void
{
	$command = 'tar -czf '
		. escapeshellarg($gzPath)
		. ' -C '
		. escapeshellarg($stagingRoot)
		. ' '
		. escapeshellarg($appId);

	exec($command . ' 2>&1', $output, $exitCode);
	if ($exitCode !== 0) {
		fail("tar failed with exit code {$exitCode}:\n" . implode("\n", $output));
	}

	if (!is_file($gzPath)) {
		fail("tar did not create expected archive: {$gzPath}");
	}
}
