<?php
echo "\x1b[33;1m === Nextcloud === \x1b[0m\n";

$cert_dir = $_SERVER['HOME'].'/.nextcloud/certificates';

$appId = 'nextsnapmail';
$appPath = "integrations/nextcloud/{$appId}";

$nc_destination = "{$destPath}{$appId}-{$package->version}-nextcloud.tar";

@unlink($nc_destination);
@unlink("{$nc_destination}.gz");

$nc_tar = new PharData($nc_destination);
$hashes = [];

file_put_contents(ROOT_DIR . "/{$appPath}/VERSION", $package->version);
$file = ROOT_DIR . "/{$appPath}/appinfo/info.xml";
file_put_contents($file, preg_replace('/<version>[^<]*</', "<version>{$package->version}<", file_get_contents($file)));

$nc_tar->buildFromDirectory('./integrations/nextcloud', "@{$appPath}/@");
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath));
foreach ($files as $file) {
	if (is_file($file)) {
		$name = str_replace('\\', '/', $file);
		$name = str_replace("{$appPath}/", '', $name);
		$hashes[$name] = hash_file('sha512', $file);
	}
}

// Bundle the available extensions with the app. Fresh installations and
// upgrades must not depend on the external SnappyMail package repository.
$pluginRoot = 'plugins';
$plugins = new DirectoryIterator($pluginRoot);
foreach ($plugins as $plugin) {
	if ($plugin->isDot() || !$plugin->isDir()) {
		continue;
	}

	$pluginName = $plugin->getFilename();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin->getPathname(), RecursiveDirectoryIterator::SKIP_DOTS));
	foreach ($files as $file) {
		if (is_file($file)) {
			$name = str_replace('\\', '/', $file);
			$name = substr($name, strlen($plugin->getPathname()) + 1);
			$archiveName = "{$appId}/app/bundled-plugins/{$pluginName}/{$name}";
			$nc_tar->addFile($file, $archiveName);
			$hashes["app/bundled-plugins/{$pluginName}/{$name}"] = hash_file('sha512', $file);
		}
	}
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('snappymail/v'), RecursiveIteratorIterator::SELF_FIRST);
foreach ($files as $file) {
	if (is_file($file)) {
		$newFile = str_replace('\\', '/', $file);
		$newName = str_replace('/.htaccess', '/_htaccess', $newFile);
		$nc_tar->addFile($file, "{$appId}/app/{$newName}");
		$hashes["app/{$newFile}"] = hash_file('sha512', $file);
	}
}

/*
$nc_tar->addFile('data/.htaccess');
$nc_tar->addFromString('data/VERSION', $package->version);
$nc_tar->addFile('data/README.md');
$nc_tar->addFile('_include.php', 'snappymail/app/_include.php');
*/
$nc_tar->addFile('.htaccess', "{$appId}/app/_htaccess");
$hashes['app/.htaccess'] = hash_file('sha512', '.htaccess');

$index = file_get_contents('index.php');
$index = str_replace('0.0.0', $package->version, $index);
//$index = str_replace('snappymail/v/', '', $index);
$nc_tar->addFromString("{$appId}/app/index.php", $index);
$hashes['app/index.php'] = hash('sha512', $index);

$nc_tar->addFile('README.md', "{$appId}/app/README.md");
$hashes['app/README.md'] = hash_file('sha512', 'README.md');

$nc_tar->addFile('CHANGELOG.md', "{$appId}/CHANGELOG.md");
$hashes['CHANGELOG.md'] = hash_file('sha512', 'CHANGELOG.md');

$data = file_get_contents('dev/serviceworker.js');
$nc_tar->addFromString("{$appId}/app/serviceworker.js", $data);
$hashes['app/serviceworker.js'] = hash('sha512', $data);

spl_autoload_register(function($name){
	$file = __DIR__ . '/' . str_replace('\\', '/', $name) . '.php';
	require $file;
});

ksort($hashes);
$cert = file_get_contents($cert_dir."/{$appId}.crt");
$rsa = new \phpseclib\Crypt\RSA();
$rsa->loadKey(file_get_contents($cert_dir."/{$appId}.key"));
$x509 = new \phpseclib\File\X509();
$x509->loadX509($cert);
$x509->setPrivateKey($rsa);
$rsa->setSignatureMode(\phpseclib\Crypt\RSA::SIGNATURE_PSS);
$rsa->setMGFHash('sha512');
$rsa->setSaltLength(0);
$signature = $rsa->sign(json_encode($hashes));
$nc_tar->addFromString("{$appId}/appinfo/signature.json", json_encode([
	'hashes' => $hashes,
	'signature' => base64_encode($signature),
	'certificate' => $cert
], JSON_PRETTY_PRINT));

$nc_tar->compress(Phar::GZ);
unlink($nc_destination);
$nc_destination .= '.gz';

$signature = shell_exec("openssl dgst -sha512 -sign {$cert_dir}/{$appId}.key {$nc_destination} | openssl base64");
file_put_contents($nc_destination.'.sig', $signature);

echo "{$nc_destination} created\n";
