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
