<?php

// Name this file as "include.php" to enable it.

//header('Strict-Transport-Security: max-age=31536000');

/**
 * Uncomment to use gzip compressed output
 */
//define('USE_GZIP', 1);

/**
 * Uncomment to use brotli compressed output
 */
//define('USE_BROTLI', 1);

/**
 * Uncomment to enable multiple domain installation.
 */
//define('MULTIDOMAIN', 1);

/**
 * Uncomment to disable APCU.
 */
//define('APP_USE_APCU_CACHE', false);

/**
 * Custom 'data' folder path
 */
if (class_exists('OC')) {
	$config = \OC::$server->get(\OCP\IConfig::class);
	define('APP_DATA_FOLDER_PATH', \rtrim(\trim($config->getSystemValue('datadirectory', '')), '\\/').'/appdata_nextsnapmail/');
} else {
	http_response_code(400);
	header($_SERVER['SERVER_PROTOCOL'].' 400 Bad Request', true, 400);
	error_log("NextSnapMail outside Nextcloud {$_SERVER['REQUEST_URI']}");
	exit('Not inside Nextcloud');
//	define('APP_DATA_FOLDER_PATH', dirname(__DIR__) . '/nextsnapmail-data/');
//	define('APP_DATA_FOLDER_PATH', '/var/external-nextsnapmail-data-folder/');
}

/**
 * Additional configuration file name
 */
//define('APP_CONFIGURATION_NAME', $_SERVER['HTTP_HOST'].'.ini');

/**
 * Also update extensions on upgrade
 */
define('SNAPPYMAIL_UPDATE_PLUGINS', 0);

/**
 * Keep the NextSnapMail release separate from the embedded upstream base.
 * APP_VERSION remains the technical SnappyMail directory/API version for
 * compatibility with existing assets, plugins and stored data.
 */
if (!defined('SNAPPYMAIL_BASE_VERSION')) {
	define('SNAPPYMAIL_BASE_VERSION', defined('APP_VERSION') ? APP_VERSION : '2.38.2');
}
if (!defined('NEXTSNAPMAIL_VERSION')) {
	$nextSnapMailVersionFile = dirname(__DIR__) . '/VERSION';
	$nextSnapMailVersion = is_file($nextSnapMailVersionFile)
		? trim((string) file_get_contents($nextSnapMailVersionFile))
		: '';
	define('NEXTSNAPMAIL_VERSION', $nextSnapMailVersion ?: 'unknown');
	unset($nextSnapMailVersionFile, $nextSnapMailVersion);
}
