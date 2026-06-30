<?php

namespace SnappyMail;

abstract class Repository
{
	// snappyMailRepo
	const BASE_URL = 'https://snappymail.eu/repository/v2/';

	private static function get(string $path) : string
	{
		$oHTTP = HTTP\Request::factory(/*'socket' or 'curl'*/);
		$oHTTP->proxy = \RainLoop\Api::Config()->Get('labs', 'curl_proxy', '');
		$oHTTP->proxy_auth = \RainLoop\Api::Config()->Get('labs', 'curl_proxy_auth', '');
		$oHTTP->max_response_kb = 0;
		$oHTTP->timeout = 15; // timeout in seconds.
		$oResponse = $oHTTP->doRequest('GET', static::BASE_URL . $path);
		if (!$oResponse) {
			throw new \Exception('No HTTP response from repository');
		}
		if (200 !== $oResponse->status) {
			throw new \Exception(static::body2plain($oResponse->body), $oResponse->status);
		}
		return $oResponse->body;
	}

//	$aRep = \json_decode($sRep);

	private static function download(string $path) : string
	{
		$sTmp = APP_PRIVATE_DATA . \md5(\microtime(true).$path) . \preg_replace('/^.*?(\\.[a-z\\.]+)$/Di', '$1', $path);
		$pDest = \fopen($sTmp, 'w+b');
		if (!$pDest) {
			throw new \Exception('Cannot create temp file: '.$sTmp);
		}

		$oHTTP = HTTP\Request::factory(/*'socket' or 'curl'*/);
		$oHTTP->proxy = \RainLoop\Api::Config()->Get('labs', 'curl_proxy', '');
		$oHTTP->proxy_auth = \RainLoop\Api::Config()->Get('labs', 'curl_proxy_auth', '');
		$oHTTP->max_response_kb = 0;
		$oHTTP->timeout = 15; // timeout in seconds.
		$oHTTP->streamBodyTo($pDest);
		\set_time_limit(120);
		$oResponse = $oHTTP->doRequest('GET', static::BASE_URL . $path);
		\fclose($pDest);
		if (!$oResponse) {
			\unlink($sTmp);
			throw new \Exception('No HTTP response from repository');
		}
		if (200 !== $oResponse->status) {
			$body = \file_get_contents($sTmp);
			\unlink($sTmp);
			throw new \Exception(static::body2plain($body), $oResponse->status);
		}
		return $sTmp;
	}

	private static function body2plain(string $body) : string
	{
		return \trim(\strip_tags(
			\preg_match('@<body[^>]*>(.*)</body@si', $body, $match) ? $match[1] : $body
		));
	}

	private static function getRepositoryDataByUrl(bool &$bReal = false) : array
	{
		$bReal = false;
		$aRep = null;

		$sRepoFile = 'packages.json';

		$oCache = \RainLoop\Api::Actions()->Cacher();

		$sCacheKey = '/RepositoryCache/Repo/' . static::BASE_URL . '/File/' . $sRepoFile;
		$sRep = $oCache->Get($sCacheKey);
		$iRepTime = $sRep ? $oCache->GetTimer($sCacheKey) : 0;

		if (!$sRep || !$iRepTime || \time() - 3600 > $iRepTime) {
			$sRep = static::get($sRepoFile);
			if ($sRep) {
				$aRep = \json_decode($sRep);
				$bReal = \is_array($aRep) && \count($aRep);
				if ($bReal) {
					$oCache->Set($sCacheKey, $sRep);
					$oCache->SetTimer($sCacheKey);
				}
			} else {
				throw new \Exception('Cannot read remote repository file: '.$sRepoFile);
			}
		} else if ($sRep) {
			$aRep = \json_decode($sRep, false, 10);
			$bReal = \is_array($aRep) && \count($aRep);
		}

		return \is_array($aRep) ? $aRep : [];
	}

	private static function getRepositoryData(bool &$bReal, string &$sError) : array
	{
		$aResult = array();
		foreach (static::getBundledPackages() as $sId => $aItem) {
			$aResult[$sId] = $aItem;
		}
		$bReal = true;
		return $aResult;

		try {
			foreach (static::getRepositoryDataByUrl($bReal) as $oItem) {
				if ($oItem
				 && isset($oItem->type, $oItem->id, $oItem->name, $oItem->version, $oItem->release, $oItem->file, $oItem->description)
				 && 'plugin' === $oItem->type
				 // is this entry newer then an already defined one
				 && (empty($aResult[$oItem->id]) || \version_compare($aResult[$oItem->id]['version'], $oItem->version, '<'))
				 // does this entry require same or older app version
				 && (SNAPPYMAIL_DEV || empty($oItem->required) || \version_compare(APP_VERSION, $oItem->required, '>='))
				 // is this entry not deprecated for current app version?
				 && (SNAPPYMAIL_DEV || empty($oItem->deprecated) || \version_compare(APP_VERSION, $oItem->deprecated, '<'))
				) {
					$aResult[$oItem->id] = array(
						'type' => $oItem->type,
						'id' => $oItem->id,
						'name' => $oItem->name,
						'installed' => '',
						'enabled' => true,
						'version' => $oItem->version,
						'file' => $oItem->file,
						'release' => $oItem->release,
						'desc' => $oItem->description,
						'canBeDeleted' => false,
						'canBeUpdated' => true
					);
				}
			}
		} catch (\Throwable $e) {
			\SnappyMail\Log::error('INSTALLER', "{$e->getCode()} {$e->getMessage()}");
		}
		return $aResult;
	}

	private static function getBundledPackages() : array
	{
		$aResult = array();
		$sPath = APP_INDEX_ROOT_PATH . 'bundled-plugins';
		if (!\is_dir($sPath)) {
			return $aResult;
		}

		foreach (new \DirectoryIterator($sPath) as $oItem) {
			if ($oItem->isDot() || !$oItem->isDir()) {
				continue;
			}

			$sId = $oItem->getFilename();
			$aResult[$sId] = array(
				'type' => 'plugin',
				'id' => $sId,
				'name' => static::pluginNameFromId($sId),
				'installed' => '',
				'enabled' => false,
				'version' => '',
				'file' => $sId,
				'release' => '',
				'desc' => 'Bundled extension',
				'canBeDeleted' => false,
				'canBeUpdated' => false
			);
		}

		\uksort($aResult, 'strcasecmp');
		return $aResult;
	}

	private static function pluginNameFromId(string $sId) : string
	{
		return \implode(' ', \array_map('ucfirst', \explode(' ', \preg_replace('/[^a-z0-9]+/i', ' ', $sId))));
	}

	private static function copyDirectory(string $sSource, string $sDestination) : void
	{
		if (!\is_dir($sDestination) && !\mkdir($sDestination, 0755, true) && !\is_dir($sDestination)) {
			throw new \RuntimeException("Could not create directory {$sDestination}");
		}

		$oIterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($sSource, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$iPrefixLength = \strlen($sSource) + 1;

		foreach ($oIterator as $oItem) {
			$sTarget = $sDestination . '/' . \substr($oItem->getPathname(), $iPrefixLength);
			if ($oItem->isDir()) {
				if (!\is_dir($sTarget) && !\mkdir($sTarget, 0755, true) && !\is_dir($sTarget)) {
					throw new \RuntimeException("Could not create directory {$sTarget}");
				}
			} elseif (!\copy($oItem->getPathname(), $sTarget)) {
				throw new \RuntimeException("Could not copy {$sTarget}");
			}
		}
	}

	/**
	 * return object
			$info->version
			$info->file
			$info->warnings
	 */
	public static function getLatestCoreInfo()
	{
		\RainLoop\Api::Actions()->IsAdminLoggined();
		return (object) array(
			'version' => APP_VERSION,
			'file' => '',
			'warnings' => array()
		);
	}

	public static function downloadCore() : ?string
	{
		return null;
	}

	public static function canUpdateCore() : bool
	{
		return false;
	}

	public static function getEnabledPackagesNames() : array
	{
		return \array_map('trim',
			\explode(',', \strtolower(\RainLoop\Api::Config()->Get('plugins', 'enabled_list', '')))
		);
	}

	public static function enablePackage(string $sName, bool $bEnable = true) : bool
	{
		if (!\strlen($sName)) {
			return false;
		}

		$oConfig = \RainLoop\Api::Config();

		$aEnabledPlugins = static::getEnabledPackagesNames();

		$aNewEnabledPlugins = array();
		if ($bEnable) {
			$aNewEnabledPlugins = $aEnabledPlugins;
			$aNewEnabledPlugins[] = $sName;
		} else {
			foreach ($aEnabledPlugins as $sPlugin) {
				if ($sName !== $sPlugin && \strlen($sPlugin)) {
					$aNewEnabledPlugins[] = $sPlugin;
				}
			}
		}

		$oConfig->Set('plugins', 'enabled_list', \trim(\implode(',', \array_unique($aNewEnabledPlugins)), ' ,'));

		return $oConfig->Save();
	}

	public static function getPackagesList() : array
	{
		empty($_ENV['SNAPPYMAIL_INCLUDE_AS_API']) && \RainLoop\Api::Actions()->IsAdminLoggined();

		$bReal = false;
		$sError = '';
		$aList = static::getRepositoryData($bReal, $sError);

		$aEnabledPlugins = static::getEnabledPackagesNames();

		$aInstalled = \RainLoop\Api::Actions()->Plugins()->InstalledPlugins();
		foreach ($aInstalled as $aItem) {
			if ($aItem) {
				if (isset($aList[$aItem[0]])) {
					$aList[$aItem[0]]['installed'] = $aItem[1];
					$aList[$aItem[0]]['enabled'] = \in_array(\strtolower($aItem[0]), $aEnabledPlugins);
					$aList[$aItem[0]]['canBeDeleted'] = true;
					$aList[$aItem[0]]['canBeUpdated'] = \version_compare($aItem[1], $aList[$aItem[0]]['version'], '<');
				} else {
					\array_push($aList, array(
						'type' => 'plugin',
						'id' => $aItem[0],
						'name' => $aItem[2],
						'installed' => $aItem[1],
						'enabled' => \in_array(\strtolower($aItem[0]), $aEnabledPlugins),
						'version' => '',
						'file' => '',
						'release' => '',
						'desc' => $aItem[3],
						'canBeDeleted' => true,
						'canBeUpdated' => false
					));
				}
			}
		}

		$aList = \array_values($aList);
		\usort($aList, static function($a, $b) {
			return ((int) !empty($b['enabled']) <=> (int) !empty($a['enabled']))
				?: \strcasecmp($a['name'] ?: $a['id'], $b['name'] ?: $b['id']);
		});

		return array(
			 'Real' => $bReal,
			 'List' => $aList,
			 'Error' => $sError
		);
	}

	public static function deletePackage(string $sId) : bool
	{
		\RainLoop\Api::Actions()->IsAdminLoggined();
		static::enablePackage($sId, false);
		return static::deletePackageDir($sId);
	}

	private static function deletePackageDir(string $sId) : bool
	{
		$sPath = APP_PLUGINS_PATH.$sId;
		return (!\is_dir($sPath) || \MailSo\Base\Utils::RecRmDir($sPath))
			&& (!\is_file("{$sPath}.phar") || \unlink("{$sPath}.phar"));
	}

	public static function installPackage(string $sType, string $sId, string $sFile = '') : bool
	{
		empty($_ENV['SNAPPYMAIL_INCLUDE_AS_API']) && \RainLoop\Api::Actions()->IsAdminLoggined();

		\SnappyMail\Log::info('INSTALLER', 'Start package install: '.$sId.' ('.$sType.')');

		$sRealFile = '';

		$bResult = false;
		$sTmp = null;
		try {
			if ('plugin' === $sType) {
				$sBundledPlugin = APP_INDEX_ROOT_PATH . 'bundled-plugins/' . $sId;
				if (\preg_match('/^[a-z0-9\-]+$/', $sId) && \is_dir($sBundledPlugin)) {
					if (!static::deletePackageDir($sId)) {
						throw new \Exception('Cannot remove previous plugin folder: '.$sId);
					}
					static::copyDirectory($sBundledPlugin, APP_PLUGINS_PATH . $sId);
					return true;
				}
			}

			if ('plugin' === $sType) {
				$bReal = false;
				$sError = '';
				$aList = static::getRepositoryData($bReal, $sError);
				if ($sError) {
					throw new \Exception($sError);
				}
				if (isset($aList[$sId]) && (!$sFile || $sFile === $aList[$sId]['file'])) {
					$sRealFile = $aList[$sId]['file'];
					$sTmp = static::download($aList[$sId]['file']);
				}
			}

			if ($sTmp) {
				if (!static::deletePackageDir($sId)) {
					throw new \Exception('Cannot remove previous plugin folder: '.$sId);
				}
				if ('.phar' === \substr($sRealFile, -5)) {
					$bResult = \copy($sTmp, APP_PLUGINS_PATH . \basename($sRealFile));
				} else {
					if (\class_exists('PharData')) {
						$oArchive = new \PharData($sTmp, 0, $sRealFile);
					} else {
//						throw new \Exception('PHP Phar is disabled, you must enable it');
						$oArchive = new \SnappyMail\TAR($sTmp);
					}
					$bResult = $oArchive->extractTo(\rtrim(APP_PLUGINS_PATH, '\\/'));
				}
				if (!$bResult) {
					throw new \Exception('Cannot extract package files');
				}
			}
		} catch (\Throwable $e) {
			\SnappyMail\Log::error('INSTALLER', "Install package {$sRealFile} failed: {$e->getMessage()}");
			throw $e;
		} finally {
			$sTmp && \unlink($sTmp);
		}

		return $bResult;
	}

}
