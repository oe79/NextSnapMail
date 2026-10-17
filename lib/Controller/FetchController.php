<?php

namespace OCA\NextSnapMail\Controller;

use OCA\NextSnapMail\Util\SnappyMailHelper;

use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class FetchController extends Controller {
	private const DELETE_OLD_SNAPPYMAIL_CONFIRMATION = 'DELETE_SNAPPYMAIL_DATA';
	private const RESET_NEXTSNAPMAIL_CONFIRMATION = 'RESET';
	private const MAX_ADDITIONAL_ACCOUNTS_BYTES = 1048576;
	private const MAX_PGP_BACKUP_KEY_BYTES = 1048576;
	private const MAX_PLUGIN_UPLOAD_BYTES = 5242880;

	private IConfig $config;
	private IAppManager $appManager;
	private IL10N $l;

	public function __construct(string $appName, IRequest $request, IAppManager $appManager, IConfig $config, IL10N $l) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->appManager = $appManager;
		$this->l = $l;
	}

	public function upgrade(): JSONResponse {
		$error = 'Upgrade failed';
		try {
			SnappyMailHelper::loadApp();
			if (\SnappyMail\Upgrade::core()) {
				return new JSONResponse([
					'status' => 'success',
					'Message' => $this->l->t('Upgraded successfully')
				]);
			}
		} catch (\Throwable $e) {
			$error .= ': ' . $e->getMessage();
		}
		return new JSONResponse([
			'status' => 'error',
			'Message' => $error
		]);
	}

	public function setAdmin(): JSONResponse {
		try {
			$sUrl = '';
			$sPath = '';

			if (isset($_POST['appname']) && 'nextsnapmail' === $_POST['appname']) {
				if (!empty($_POST['nextsnapmail-list-old-snappymail-accounts'])) {
					return $this->safeJsonResponse(function (): JSONResponse {
						$accounts = $this->getOldSnappyMailAccounts();
						return new JSONResponse([
							'status' => 'success',
							'Message' => $accounts
								? $this->l->t('Old SnappyMail account data found in the Nextcloud database:')
								: $this->l->t('No old SnappyMail account data was found in the Nextcloud database.'),
							'Accounts' => \array_values($accounts),
							'AppData' => $this->scanOldSnappyMailAppData()
						]);
					});
				}

				if (!empty($_POST['nextsnapmail-import-selected-snappymail-accounts'])) {
					return $this->safeJsonResponse(function (): JSONResponse {
						return new JSONResponse([
							'status' => 'success',
							'Message' => \implode("\n", $this->importSelectedOldSnappyMailAccounts()),
							'Accounts' => \array_values($this->getOldSnappyMailAccounts()),
							'AppData' => $this->scanOldSnappyMailAppData()
						]);
					});
				}

				if (!empty($_POST['nextsnapmail-delete-imported-account'])) {
					return $this->safeJsonResponse(function (): JSONResponse {
						return new JSONResponse([
							'status' => 'success',
							'Message' => \implode("\n", $this->deleteNextSnapMailAccountForReimport()),
							'Accounts' => \array_values($this->getOldSnappyMailAccounts()),
							'AppData' => $this->scanOldSnappyMailAppData()
						]);
					});
				}

				if (!empty($_POST['nextsnapmail-delete-old-snappymail-data'])) {
					return $this->safeJsonResponse(function (): JSONResponse {
						return new JSONResponse([
							'status' => 'success',
							'Message' => \implode("\n", $this->deleteOldSnappyMailData()),
							'Accounts' => \array_values($this->getOldSnappyMailAccounts()),
							'AppData' => $this->scanOldSnappyMailAppData()
						]);
					});
				}

				if (!empty($_POST['nextsnapmail-reset-data'])) {
					return $this->safeJsonResponse(function (): JSONResponse {
						return new JSONResponse([
							'status' => 'success',
							'Message' => \implode("\n", $this->resetNextSnapMailData())
						]);
					});
				}

				if (!empty($_POST['nextsnapmail-upload-plugin'])) {
					return $this->safeJsonResponse(function (): JSONResponse {
						return new JSONResponse([
							'status' => 'success',
							'Message' => \implode("\n", $this->installUploadedPluginPackage())
						]);
					});
				}

				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-autologin',
					isset($_POST['nextsnapmail-autologin']) ? '1' === $_POST['nextsnapmail-autologin'] : false);
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-autologin-with-email',
					isset($_POST['nextsnapmail-autologin']) ? '2' === $_POST['nextsnapmail-autologin'] : false);
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-no-embed', isset($_POST['nextsnapmail-no-embed']));
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-autologin-oidc', isset($_POST['nextsnapmail-autologin-oidc']));
				$this->config->setAppValue('nextsnapmail', 'gmail-oauth-client-id',
					\trim((string) ($_POST['nextsnapmail-gmail-oauth-client-id'] ?? '')));
				if (isset($_POST['nextsnapmail-gmail-oauth-client-secret'])
					&& '' !== \trim((string) $_POST['nextsnapmail-gmail-oauth-client-secret'])) {
					$this->config->setAppValue('nextsnapmail', 'gmail-oauth-client-secret',
						SnappyMailHelper::encodePassword(
							\trim((string) $_POST['nextsnapmail-gmail-oauth-client-secret']),
							'nextsnapmail-gmail-oauth-client-secret'
						)
					);
				}
				$this->config->setAppValue('nextsnapmail', 'gmail-oauth-domains',
					\trim((string) ($_POST['nextsnapmail-gmail-oauth-domains'] ?? "gmail.com\ngooglemail.com")));
				$this->config->setAppValue('nextsnapmail', 'gmail-oauth-auto-configure',
					isset($_POST['nextsnapmail-gmail-oauth-auto-configure']) ? '1' : '0');
			} else {
				return new JSONResponse([
					'status' => 'error',
					'Message' => $this->l->t('Invalid argument(s)')
				]);
			}

			SnappyMailHelper::loadApp();

			$oConfig = \RainLoop\Api::Config();
			if (!empty($_POST['nextsnapmail-app_path'])) {
				$oConfig->Set('webmail', 'app_path', $_POST['nextsnapmail-app_path']);
			}
			$oConfig->Set('webmail', 'allow_languages_on_settings', empty($_POST['nextsnapmail-nc-lang']));
			$oConfig->Set('login', 'allow_languages_on_login', empty($_POST['nextsnapmail-nc-lang']));
			$oConfig->Save();

			$oNextcloudPlugin = \RainLoop\Api::Actions()->Plugins()->CreatePluginByName('nextcloud');
			if ($oNextcloudPlugin) {
				$oNextcloudPluginConfig = $oNextcloudPlugin->Config();
				$oNextcloudPluginConfig->Set('plugin', 'suggestions', isset($_POST['nextcloud-plugin-suggestions']));
				$oNextcloudPluginConfig->Set('plugin', 'ignoreSystemAddressbook', isset($_POST['nextcloud-plugin-ignore-system-addressbook']));
				$oNextcloudPluginConfig->Set('plugin', 'calendar', isset($_POST['nextcloud-plugin-calendar']));
				if (!$oNextcloudPluginConfig->Save()) {
					throw new \RuntimeException($this->l->t('Could not save the Nextcloud integration settings.'));
				}
			}

			if (!empty($_POST['import-rainloop'])) {
				return new JSONResponse([
					'status' => 'success',
					'Message' => \implode("\n", \OCA\NextSnapMail\Util\RainLoop::import())
				]);
			}

			$debug = !empty($_POST['nextsnapmail-debug']);
			$oConfig = \RainLoop\Api::Config();
			if ($debug != $oConfig->Get('debug', 'enable', false)) {
				$oConfig->Set('debug', 'enable', $debug);
				$oConfig->Save();
			}

			return new JSONResponse([
				'status' => 'success',
				'Message' => $this->l->t('Saved successfully')
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'status' => 'error',
				'Message' => $e->getMessage()
			]);
		}
	}

	private function getOldSnappyMailAccounts(): array {
		$accounts = [];

		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$qb = $db->getQueryBuilder();
		$query = $qb->select('userid', 'configkey', 'configvalue')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('snappymail')))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('configkey', $qb->createNamedParameter('snappymail-email')),
				$qb->expr()->eq('configkey', $qb->createNamedParameter('passphrase')),
				$qb->expr()->eq('configkey', $qb->createNamedParameter('snappymail-password'))
			))
			->orderBy('userid', 'ASC')
			->addOrderBy('configkey', 'ASC');

		$result = $query->executeQuery();
		while (true) {
			$row = \method_exists($result, 'fetchAssociative') ? $result->fetchAssociative() : $result->fetch();
			if (!$row) {
				break;
			}

			$uid = (string) $row['userid'];
			$key = (string) $row['configkey'];
			$value = (string) $row['configvalue'];

			if (!isset($accounts[$uid])) {
				$accounts[$uid] = [
					'uid' => $uid,
					'email' => '',
					'hasPassphrase' => false,
					'hasLegacyPassword' => false,
					'hasPassword' => false,
					'nextsnapmailExists' => false,
					'nextsnapmailEmailExists' => false,
					'nextsnapmailPassphraseExists' => false
				];
			}

			if ('snappymail-email' === $key) {
				$accounts[$uid]['email'] = $value;
			} else if ('passphrase' === $key && '' !== $value) {
				$accounts[$uid]['hasPassphrase'] = true;
			} else if ('snappymail-password' === $key && '' !== $value) {
				$accounts[$uid]['hasLegacyPassword'] = true;
			}
		}

		if (\method_exists($result, 'closeCursor')) {
			$result->closeCursor();
		} else if (\method_exists($result, 'free')) {
			$result->free();
		}

		foreach ($accounts as $uid => &$account) {
			$account['hasPassword'] = $account['hasPassphrase'] || $account['hasLegacyPassword'];
			$account['nextsnapmailEmailExists'] =
				'' !== $this->config->getUserValue($uid, 'nextsnapmail', 'nextsnapmail-email', '');
			$account['nextsnapmailPassphraseExists'] =
				'' !== $this->config->getUserValue($uid, 'nextsnapmail', 'passphrase', '');
			$account['nextsnapmailExists'] =
				$account['nextsnapmailEmailExists'] || $account['nextsnapmailPassphraseExists'];
		}
		unset($account);

		return $accounts;
	}

	private function getAppDataImportPaths(): array {
		$datadir = \rtrim(\trim($this->config->getSystemValue('datadirectory', '')), '\\/');

		return [
			'source' => $datadir . '/appdata_snappymail',
			'target' => $datadir . '/appdata_nextsnapmail'
		];
	}

	private function normalizePath(string $path): string {
		return \str_replace('\\', '/', \rtrim($path, '\\/'));
	}

	private function realDirectoryPath(string $path): ?string {
		$realPath = \realpath($path);
		if (!\is_string($realPath) || !\is_dir($realPath)) {
			return null;
		}

		return $this->normalizePath($realPath);
	}

	private function ensurePathIsInsideDirectory(string $path, string $baseDirectory): void {
		$normalizedPath = $this->normalizePath($path);
		$normalizedBase = $this->normalizePath($baseDirectory);

		if ($normalizedPath !== $normalizedBase && 0 !== \strpos($normalizedPath . '/', $normalizedBase . '/')) {
			throw new \RuntimeException($this->l->t('Refusing to access unexpected folder') . ': ' . $normalizedPath);
		}
	}

	private function safeJsonResponse(callable $callback): JSONResponse {
		try {
			return $callback();
		} catch (\Throwable $e) {
			\OC::$server->get(\Psr\Log\LoggerInterface::class)->error($e->getMessage(), [
				'app' => 'nextsnapmail',
				'exception' => $e
			]);

			return new JSONResponse([
				'status' => 'error',
				'Message' => $this->l->t('The requested operation failed. Please check the Nextcloud log for details.')
			]);
		}
	}

	private function scanOldSnappyMailAppData(): array {
		$paths = $this->getAppDataImportPaths();
		$scan = [
			'source' => $paths['source'],
			'target' => $paths['target'],
			'sourceExists' => \is_dir($paths['source']),
			'targetExists' => \is_dir($paths['target']),
			'files' => 0,
			'directories' => 0,
			'wouldCopy' => 0,
			'wouldSkip' => 0,
			'wouldCopyBytes' => 0
		];

		if (!$scan['sourceExists']) {
			return $scan;
		}

		$source = \str_replace('\\', '/', \rtrim($paths['source'], '\\/'));
		$target = \str_replace('\\', '/', \rtrim($paths['target'], '\\/'));

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$prefixLength = \strlen($source) + 1;

		foreach ($iterator as $item) {
			if ($item->isLink()) {
				continue;
			}

			$relativePath = \str_replace('\\', '/', \substr($item->getPathname(), $prefixLength));
			$targetPath = $target . '/' . $relativePath;

			if ($item->isDir()) {
				++$scan['directories'];
				continue;
			}

			++$scan['files'];
			if (\is_file($targetPath)) {
				++$scan['wouldSkip'];
				continue;
			}

			++$scan['wouldCopy'];
			$scan['wouldCopyBytes'] += $item->getSize();
		}

		return $scan;
	}

	private function installUploadedPluginPackage(): array {
		SnappyMailHelper::loadApp();

		$file = $this->getUploadedPluginFile();
		if (!$file) {
			throw new \RuntimeException($this->l->t('No plugin package was uploaded.'));
		}

		$name = (string) ($file['name'] ?? '');
		$tmpName = (string) ($file['tmp_name'] ?? '');
		$error = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
		$size = (int) ($file['size'] ?? 0);

		if (\UPLOAD_ERR_OK !== $error || '' === $tmpName || !\is_file($tmpName)) {
			throw new \RuntimeException($this->l->t('The uploaded plugin package could not be read.'));
		}
		if ($size < 1 || $size > self::MAX_PLUGIN_UPLOAD_BYTES) {
			throw new \RuntimeException($this->l->t('The uploaded plugin package is too large.'));
		}
		if (!\preg_match('/\.(?:tgz|tar\.gz|zip)$/i', $name)) {
			throw new \RuntimeException($this->l->t('Only .tgz, .tar.gz and .zip plugin packages are supported.'));
		}

		$base = $this->normalizePath(APP_PRIVATE_DATA . 'plugin-uploads');
		if (!\is_dir($base) && !\mkdir($base, 0700, true) && !\is_dir($base)) {
			throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $base);
		}

		$work = $base . '/' . \bin2hex(\random_bytes(12));
		$extract = $work . '/extract';
		if (!\mkdir($extract, 0700, true) && !\is_dir($extract)) {
			throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $extract);
		}

		$extension = \preg_match('/\.zip$/i', $name) ? '.zip' : '.tar.gz';
		$archive = $work . '/package' . $extension;

		try {
			if (\is_uploaded_file($tmpName)) {
				if (!\move_uploaded_file($tmpName, $archive)) {
					throw new \RuntimeException($this->l->t('The uploaded plugin package could not be stored.'));
				}
			} else if (!\copy($tmpName, $archive)) {
				throw new \RuntimeException($this->l->t('The uploaded plugin package could not be stored.'));
			}

			$this->extractPluginArchive($archive, $extract, '.zip' === $extension);
			$plugin = $this->validateExtractedPluginPackage($extract);
			$pluginId = $plugin['id'];
			$source = $plugin['path'];

			if ('nextcloud' === $pluginId) {
				throw new \RuntimeException($this->l->t('The required Nextcloud extension cannot be replaced by upload.'));
			}

			$target = $this->normalizePath(APP_PLUGINS_PATH . $pluginId);
			$overwrite = !empty($_POST['nextsnapmail-plugin-overwrite']);
			$lines = [];

			if (\is_dir($target)) {
				if (!$overwrite) {
					throw new \RuntimeException($this->l->t('Plugin already exists. Enable overwrite to replace it.') . ': ' . $pluginId);
				}
				$backupRoot = $base . '/backups';
				if (!\is_dir($backupRoot) && !\mkdir($backupRoot, 0700, true) && !\is_dir($backupRoot)) {
					throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $backupRoot);
				}
				$backup = $backupRoot . '/' . $pluginId . '-' . \date('Ymd-His');
				$this->copyDirectoryForReset($target, $backup);
				$this->deleteDirectoryTree($target, APP_PLUGINS_PATH);
				$lines[] = $this->l->t('Existing plugin was backed up before overwrite.') . ': ' . $backup;
			}

			$this->copyDirectoryForReset($source, $target);
			\file_put_contents($target . '/.user-installed-nextsnapmail-plugin', \gmdate('c') . "\n");
			$this->writeUserInstalledPluginRegistry($pluginId);

			$lines[] = $this->l->t('Plugin package was installed.') . ': ' . $pluginId;
			$lines[] = $this->l->t('Open the NextSnapMail admin panel and enable the plugin under Extensions.');

			return $lines;
		} finally {
			if (\is_dir($work)) {
				$this->deleteDirectoryTree($work, $base);
			}
		}
	}

	private function getUploadedPluginFile(): ?array {
		$file = \method_exists($this->request, 'getUploadedFile')
			? $this->request->getUploadedFile('nextsnapmail-plugin-package')
			: null;
		if (\is_array($file)) {
			return $file;
		}
		return isset($_FILES['nextsnapmail-plugin-package']) && \is_array($_FILES['nextsnapmail-plugin-package'])
			? $_FILES['nextsnapmail-plugin-package']
			: null;
	}

	private function extractPluginArchive(string $archive, string $destination, bool $zip): void {
		if ($zip) {
			if (!\class_exists(\ZipArchive::class)) {
				throw new \RuntimeException($this->l->t('ZIP plugin uploads are not supported on this server.'));
			}
			$zipArchive = new \ZipArchive();
			if (true !== $zipArchive->open($archive)) {
				throw new \RuntimeException($this->l->t('Could not open plugin package.'));
			}
			try {
				for ($i = 0; $i < $zipArchive->numFiles; $i++) {
					$name = (string) $zipArchive->getNameIndex($i);
					$this->assertSafeArchivePath($name);
				}
				if (!$zipArchive->extractTo($destination)) {
					throw new \RuntimeException($this->l->t('Could not extract plugin package.'));
				}
			} finally {
				$zipArchive->close();
			}
			return;
		}

		if (!\class_exists(\PharData::class)) {
			throw new \RuntimeException($this->l->t('TAR plugin uploads are not supported on this server.'));
		}

		$phar = new \PharData($archive);
		$iterator = new \RecursiveIteratorIterator($phar);
		foreach ($iterator as $item) {
			$this->assertSafeArchivePath($iterator->getSubPathName());
		}
		$phar->extractTo($destination, null, true);
	}

	private function assertSafeArchivePath(string $path): void {
		$path = \str_replace('\\', '/', $path);
		if ('' === $path || '/' === $path[0] || \str_contains($path, "\0")) {
			throw new \RuntimeException($this->l->t('Unsafe plugin package path rejected.'));
		}
		foreach (\explode('/', $path) as $part) {
			if ('' === $part || '.' === $part || '..' === $part) {
				throw new \RuntimeException($this->l->t('Unsafe plugin package path rejected.'));
			}
		}
	}

	private function validateExtractedPluginPackage(string $extract): array {
		$extract = $this->realDirectoryPath($extract);
		if (null === $extract) {
			throw new \RuntimeException($this->l->t('Could not validate plugin package.'));
		}

		$top = [];
		foreach (new \DirectoryIterator($extract) as $item) {
			if ($item->isDot()) {
				continue;
			}
			if (!$item->isDir() || $item->isLink()) {
				throw new \RuntimeException($this->l->t('Plugin package must contain exactly one plugin folder.'));
			}
			$top[] = $item->getPathname();
		}

		if (1 !== \count($top)) {
			throw new \RuntimeException($this->l->t('Plugin package must contain exactly one plugin folder.'));
		}

		$pluginPath = $this->normalizePath($top[0]);
		$this->ensurePathIsInsideDirectory($pluginPath, $extract);
		$pluginId = \basename($pluginPath);
		if (!\preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $pluginId)) {
			throw new \RuntimeException($this->l->t('Invalid plugin folder name.') . ': ' . $pluginId);
		}
		if (!\is_file($pluginPath . '/index.php')) {
			throw new \RuntimeException($this->l->t('Plugin package must contain an index.php file.'));
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($pluginPath, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ($iterator as $item) {
			$path = $this->normalizePath($item->getPathname());
			$this->ensurePathIsInsideDirectory($path, $pluginPath);
			if ($item->isLink()) {
				throw new \RuntimeException($this->l->t('Plugin package must not contain symbolic links.'));
			}
		}

		$className = $this->pluginClassNameFromId($pluginId);
		$index = (string) \file_get_contents($pluginPath . '/index.php');
		if (!\preg_match('/\bclass\s+' . \preg_quote($className, '/') . '\b/i', $index)) {
			throw new \RuntimeException($this->l->t('Plugin class does not match the plugin folder name.') . ': ' . $className);
		}

		return [
			'id' => $pluginId,
			'path' => $pluginPath
		];
	}

	private function pluginClassNameFromId(string $pluginId): string {
		return \implode('', \array_map('ucfirst', \array_map('strtolower',
			\explode(' ', \preg_replace('/[^a-z0-9]+/', ' ', $pluginId))
		))) . 'Plugin';
	}

	private function writeUserInstalledPluginRegistry(string $pluginId): void {
		$registryFile = APP_PRIVATE_DATA . 'configs/user-installed-plugins.json';
		$registry = [];
		if (\is_file($registryFile)) {
			$data = \json_decode((string) \file_get_contents($registryFile), true);
			if (\is_array($data)) {
				$registry = $data;
			}
		}

		$registry[$pluginId] = [
			'installedAt' => \gmdate('c'),
			'source' => 'manual-upload'
		];

		\RainLoop\Utils::saveFile($registryFile, \json_encode($registry, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
	}

	private function importOldSnappyMailAppData(): array {
		$paths = $this->getAppDataImportPaths();
		$source = $this->realDirectoryPath($paths['source']);
		$target = $this->normalizePath($paths['target']);

		$result = [
			'copiedFiles' => 0,
			'skippedFiles' => 0,
			'createdDirectories' => 0,
			'copiedBytes' => 0
		];

		if (null === $source) {
			return [$this->l->t('Old SnappyMail app data folder was not found.')];
		}

		if (!\is_dir($target) && !\mkdir($target, 0700, true) && !\is_dir($target)) {
			return [$this->l->t('Could not create NextSnapMail app data folder.') . ': ' . $target];
		}
		$target = $this->realDirectoryPath($target);
		if (null === $target) {
			return [$this->l->t('Could not create NextSnapMail app data folder.') . ': ' . $paths['target']];
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$prefixLength = \strlen($source) + 1;

		foreach ($iterator as $item) {
			if ($item->isLink()) {
				continue;
			}

			$relativePath = \str_replace('\\', '/', \substr($item->getPathname(), $prefixLength));
			$targetPath = $target . '/' . $relativePath;
			$this->ensurePathIsInsideDirectory($targetPath, $target);

			if ($item->isDir()) {
				if (!\is_dir($targetPath)) {
					if (!\mkdir($targetPath, $item->getPerms() & 0777, true) && !\is_dir($targetPath)) {
						throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $targetPath);
					}
					$realTargetPath = $this->realDirectoryPath($targetPath);
					if (null === $realTargetPath) {
						throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $targetPath);
					}
					$this->ensurePathIsInsideDirectory($realTargetPath, $target);
					++$result['createdDirectories'];
					@\touch($targetPath, $item->getMTime());
				} else {
					$realTargetPath = $this->realDirectoryPath($targetPath);
					if (null === $realTargetPath) {
						throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $targetPath);
					}
					$this->ensurePathIsInsideDirectory($realTargetPath, $target);
				}
				continue;
			}

			if ($this->isSnappyMailAccountSecretFile($relativePath)) {
				++$result['skippedFiles'];
				continue;
			}

			if (\is_file($targetPath)) {
				++$result['skippedFiles'];
				continue;
			}

			if (!\is_dir(\dirname($targetPath)) && !\mkdir(\dirname($targetPath), 0700, true) && !\is_dir(\dirname($targetPath))) {
				throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . \dirname($targetPath));
			}
			$realTargetDirectory = $this->realDirectoryPath(\dirname($targetPath));
			if (null === $realTargetDirectory) {
				throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . \dirname($targetPath));
			}
			$this->ensurePathIsInsideDirectory($realTargetDirectory, $target);

			if (!\copy($item->getPathname(), $targetPath)) {
				throw new \RuntimeException($this->l->t('Could not copy file') . ': ' . $relativePath);
			}

			@\chmod($targetPath, $item->getPerms() & 0777);
			@\touch($targetPath, $item->getMTime());
			++$result['copiedFiles'];
			$result['copiedBytes'] += $item->getSize();
		}

		return [
			$this->l->t('App data import completed.'),
			$this->l->t('Copied files') . ': ' . $result['copiedFiles'],
			$this->l->t('Skipped existing files') . ': ' . $result['skippedFiles'],
			$this->l->t('Created directories') . ': ' . $result['createdDirectories'],
			$this->l->t('Copied bytes') . ': ' . $result['copiedBytes'],
			$this->l->t('Existing NextSnapMail app data files were not overwritten.')
		];
	}

	private function isSnappyMailAccountSecretFile(string $relativePath): bool {
		$relativePath = \str_replace('\\', '/', $relativePath);
		$fileName = \basename($relativePath);
		return '.cryptkey' === $fileName
			|| 'additionalaccounts' === $fileName
			|| ('.key' === \substr($fileName, -4) && \str_contains($relativePath, '/.pgp/'));
	}

	private function decryptOldSnappyMailPassphrase(string $encryptedPassphrase, string $email): ?string {
		if ('' === $encryptedPassphrase || '' === $email) {
			return null;
		}

		SnappyMailHelper::loadApp();

		$oldSalt = $this->getOldSnappyMailAppSalt();
		if (!$oldSalt) {
			return null;
		}

		$parts = \explode('.', $encryptedPassphrase);
		if (3 !== \count($parts)) {
			return null;
		}

		$parts = \array_map('MailSo\\Base\\Utils::UrlSafeBase64Decode', $parts);
		if (!isset($parts[0], $parts[1], $parts[2]) || '' === $parts[0]) {
			return null;
		}

		$key = \md5($email);
		$json = $this->decryptSnappyMailValueWithSalt((string) $parts[0], (string) $parts[2], (string) $parts[1], $key, $oldSalt);
		if (!\is_string($json)) {
			return null;
		}

		try {
			$value = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
			return \is_string($value) ? $value : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function decryptSnappyMailUrlSafeWithSalt(
		#[\SensitiveParameter] string $encryptedValue,
		#[\SensitiveParameter] string $key,
		string $appSalt
	) {
		$parts = \explode('.', $encryptedValue);
		if (3 !== \count($parts)) {
			return null;
		}

		$parts = \array_map('MailSo\\Base\\Utils::UrlSafeBase64Decode', $parts);
		if (!isset($parts[0], $parts[1], $parts[2]) || '' === $parts[0]) {
			return null;
		}

		$json = $this->decryptSnappyMailValueWithSalt((string) $parts[0], (string) $parts[2], (string) $parts[1], $key, $appSalt);
		if (!\is_string($json)) {
			return null;
		}

		try {
			return \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function getOldSnappyMailAppSalt(): ?string {
		$paths = $this->getAppDataImportPaths();
		$saltFile = \rtrim($paths['source'], '\\/') . '/SALT.php';
		if (!\is_file($saltFile)) {
			return null;
		}

		$salt = \trim((string) \file_get_contents($saltFile));
		if ('' === $salt) {
			return null;
		}

		return \md5($salt . '_default_' . $salt);
	}

	private function getStorageFilePath(string $appDataRoot, string $email, string $key): string {
		SnappyMailHelper::loadApp();

		return $this->getStorageDirectoryPath($appDataRoot, $email)
			. \MailSo\Base\Utils::SecureFileName($key);
	}

	private function getStorageDirectoryPath(string $appDataRoot, string $email, string $subFolder = ''): string {
		SnappyMailHelper::loadApp();

		$emailParts = \explode('@', $email ?: 'nobody@unknown.tld');
		$domain = \trim(1 < \count($emailParts) ? \array_pop($emailParts) : '');
		$localPart = \implode('@', $emailParts);

		return \rtrim($appDataRoot, '\\/')
			. '/_data_/_default_/storage/'
			. \MailSo\Base\Utils::SecureFileName($domain ?: 'unknown.tld')
			. '/'
			. \MailSo\Base\Utils::SecureFileName($localPart ?: '.unknown')
			. '/'
			. ($subFolder ? \MailSo\Base\Utils::SecureFileName($subFolder) . '/' : '');
	}

	private function getOldMainAccountCryptKey(string $email, #[\SensitiveParameter] string $password, string $oldAppSalt): ?string {
		$paths = $this->getAppDataImportPaths();
		$cryptKeyFile = $this->getStorageFilePath($paths['source'], $email, '.cryptkey');

		if (\is_file($cryptKeyFile)) {
			$encryptedCryptKey = (string) \file_get_contents($cryptKeyFile);

			foreach ([$password, $email] as $key) {
				$cryptKeyHex = $this->decryptSnappyMailJsonWithSalt($encryptedCryptKey, $key, $oldAppSalt);
				if (\is_string($cryptKeyHex) && \ctype_xdigit($cryptKeyHex)) {
					$cryptKey = \hex2bin($cryptKeyHex);
					if (\is_string($cryptKey)) {
						return $cryptKey;
					}
				}
			}
		}

		$derived = \hex2bin(\sha1($password . $oldAppSalt));
		return \is_string($derived) ? $derived : null;
	}

	private function decryptSnappyMailJsonWithSalt(
		#[\SensitiveParameter] string $encryptedJson,
		#[\SensitiveParameter] string $key,
		string $appSalt
	) {
		try {
			$parts = \json_decode($encryptedJson, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			return null;
		}

		if (!\is_array($parts) || 3 !== \count($parts)) {
			return null;
		}

		$parts = \array_map('base64_decode', $parts);
		if (!isset($parts[0], $parts[1], $parts[2]) || '' === $parts[0]) {
			return null;
		}

		$json = $this->decryptSnappyMailValueWithSalt((string) $parts[0], (string) $parts[2], (string) $parts[1], $key, $appSalt);
		if (!\is_string($json)) {
			return null;
		}

		try {
			return \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function writeNextSnapMailMainCryptKey(string $email, #[\SensitiveParameter] string $password): void {
		SnappyMailHelper::loadApp();

		$paths = $this->getAppDataImportPaths();
		$targetFile = $this->getStorageFilePath($paths['target'], $email, '.cryptkey');
		$targetDirectory = \dirname($targetFile);
		if (!\is_dir($targetDirectory) && !\mkdir($targetDirectory, 0700, true) && !\is_dir($targetDirectory)) {
			throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $targetDirectory);
		}

		$encryptionKey = \RainLoop\Api::Config()->Get('security', 'insecure_cryptkey', false)
			? $email
			: $password;

		\RainLoop\Utils::saveFile(
			$targetFile,
			\SnappyMail\Crypt::EncryptToJSON(\sha1($password . APP_SALT), $encryptionKey)
		);
	}

	private function getNextSnapMailMainAccountCryptKey(string $email, #[\SensitiveParameter] string $password): ?string {
		SnappyMailHelper::loadApp();

		$paths = $this->getAppDataImportPaths();
		$cryptKeyFile = $this->getStorageFilePath($paths['target'], $email, '.cryptkey');
		if (!\is_file($cryptKeyFile)) {
			return null;
		}

		$encryptedCryptKey = (string) \file_get_contents($cryptKeyFile);
		$key = \RainLoop\Api::Config()->Get('security', 'insecure_cryptkey', false)
			? $email
			: $password;
		$cryptKeyHex = \SnappyMail\Crypt::DecryptFromJSON($encryptedCryptKey, $key);
		if (\is_string($cryptKeyHex) && \ctype_xdigit($cryptKeyHex)) {
			$cryptKey = \hex2bin($cryptKeyHex);
			if (\is_string($cryptKey)) {
				return $cryptKey;
			}
		}

		return null;
	}

	private function prepareNextSnapMailMainCryptKey(string $email, #[\SensitiveParameter] string $password): array {
		$existingCryptKey = $this->getNextSnapMailMainAccountCryptKey($email, $password);
		if (\is_string($existingCryptKey)) {
			return [
				'key' => $existingCryptKey,
				'reused' => true,
				'refreshed' => false
			];
		}

		$this->writeNextSnapMailMainCryptKey($email, $password);
		$newCryptKey = \hex2bin(\sha1($password . APP_SALT));
		if (!\is_string($newCryptKey)) {
			throw new \RuntimeException($this->l->t('Could not create NextSnapMail crypt key.'));
		}

		return [
			'key' => $newCryptKey,
			'reused' => false,
			'refreshed' => true
		];
	}

	private function accountAlreadyExistsInAdditionalAccounts(array $accounts, string $accountEmail, array $account): bool {
		$accountEmail = \strtolower(\trim($accountEmail));
		$embeddedEmail = \strtolower(\trim((string) ($account['email'] ?? '')));

		foreach ($accounts as $existingEmail => $existingAccount) {
			if ($accountEmail && $accountEmail === \strtolower(\trim((string) $existingEmail))) {
				return true;
			}
			if ($embeddedEmail && \is_array($existingAccount)
			 && $embeddedEmail === \strtolower(\trim((string) ($existingAccount['email'] ?? '')))
			) {
				return true;
			}
		}

		return false;
	}

	private function writeAdditionalAccountsFile(string $targetFile, array $accounts = []): void {
		$targetDirectory = \dirname($targetFile);
		if (!\is_dir($targetDirectory) && !\mkdir($targetDirectory, 0700, true) && !\is_dir($targetDirectory)) {
			throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $targetDirectory);
		}
		\RainLoop\Utils::saveFile($targetFile, \json_encode($accounts));
	}

	private function getValidExistingAdditionalAccounts(array $accounts, #[\SensitiveParameter] string $newCryptKey): array {
		$validAccounts = [];

		foreach ($accounts as $accountEmail => $account) {
			if (!\is_array($account) || empty($account['pass']) || empty($account['hmac'])) {
				continue;
			}

			if (\hash_equals((string) $account['hmac'], \hash_hmac('sha1', (string) $account['pass'], $newCryptKey))) {
				$validAccounts[$accountEmail] = $account;
			}
		}

		return $validAccounts;
	}

	private function migrateAdditionalAccounts(
		string $email,
		#[\SensitiveParameter] string $password,
		#[\SensitiveParameter] string $newCryptKey,
		bool $reuseExistingCryptKey
	): array {
		SnappyMailHelper::loadApp();

		$paths = $this->getAppDataImportPaths();
		$targetFile = $this->getStorageFilePath($paths['target'], $email, 'additionalaccounts');
		$existingAccounts = [];
		$preservedExisting = 0;
		$discardedExisting = false;

		if (\is_file($targetFile)) {
			$decodedExistingAccounts = \json_decode((string) \file_get_contents($targetFile), true);
			if ($reuseExistingCryptKey && \is_array($decodedExistingAccounts)) {
				$existingAccounts = $this->getValidExistingAdditionalAccounts($decodedExistingAccounts, $newCryptKey);
				$preservedExisting = \count($existingAccounts);
				$discardedExisting = $preservedExisting !== \count($decodedExistingAccounts);
			} else {
				$discardedExisting = true;
			}
		}

		$oldAppSalt = $this->getOldSnappyMailAppSalt();
		if (!$oldAppSalt) {
			if ($discardedExisting) {
				$this->writeAdditionalAccountsFile($targetFile, $existingAccounts);
			}

			$lines = [$this->l->t('Additional accounts skipped: old SnappyMail salt was not found.')];
			if ($preservedExisting) {
				$lines[] = $this->l->t('Preserved existing NextSnapMail additional accounts') . ': ' . $preservedExisting;
			} else if ($discardedExisting) {
				$lines[] = $this->l->t('Existing NextSnapMail additional accounts reset because their crypt key could not be reused.');
			}
			return $lines;
		}

		$sourceFile = $this->getStorageFilePath($paths['source'], $email, 'additionalaccounts');
		if (!\is_file($sourceFile)) {
			if ($discardedExisting) {
				$this->writeAdditionalAccountsFile($targetFile, $existingAccounts);
			}

			$lines = [$this->l->t('Additional accounts skipped: no old additional accounts file was found.')];
			if ($preservedExisting) {
				$lines[] = $this->l->t('Preserved existing NextSnapMail additional accounts') . ': ' . $preservedExisting;
			} else if ($discardedExisting) {
				$lines[] = $this->l->t('Existing NextSnapMail additional accounts reset because their crypt key could not be reused.');
			}
			return $lines;
		}
		if (\filesize($sourceFile) > self::MAX_ADDITIONAL_ACCOUNTS_BYTES) {
			if ($discardedExisting) {
				$this->writeAdditionalAccountsFile($targetFile, $existingAccounts);
			}
			return [$this->l->t('Additional accounts skipped: old additional accounts file is too large.')];
		}

		$oldCryptKey = $this->getOldMainAccountCryptKey($email, $password, $oldAppSalt);
		if (!$oldCryptKey) {
			if ($discardedExisting) {
				$this->writeAdditionalAccountsFile($targetFile, $existingAccounts);
			}
			return [$this->l->t('Additional accounts skipped: old main account crypt key could not be read.')];
		}

		$additionalAccounts = \json_decode((string) \file_get_contents($sourceFile), true);
		if (!\is_array($additionalAccounts)) {
			if ($discardedExisting) {
				$this->writeAdditionalAccountsFile($targetFile, $existingAccounts);
			}
			return [$this->l->t('Additional accounts skipped: old additional accounts file is invalid.')];
		}

		$converted = 0;
		$skipped = 0;
		$keptBecauseExisting = 0;
		$mergedAccounts = $existingAccounts;

		foreach ($additionalAccounts as $accountEmail => $account) {
			if (!\is_array($account) || empty($account['pass'])) {
				++$skipped;
				continue;
			}

			if ($this->accountAlreadyExistsInAdditionalAccounts($mergedAccounts, (string) $accountEmail, $account)) {
				++$keptBecauseExisting;
				continue;
			}

			$expectedHmac = \hash_hmac('sha1', (string) $account['pass'], $oldCryptKey);
			if (!empty($account['hmac']) && !\hash_equals((string) $account['hmac'], $expectedHmac)) {
				++$skipped;
				continue;
			}

			$plainPassword = $this->decryptSnappyMailUrlSafeWithSalt((string) $account['pass'], $oldCryptKey, $oldAppSalt);
			if (!\is_string($plainPassword)) {
				++$skipped;
				continue;
			}

			$account['pass'] = \SnappyMail\Crypt::EncryptUrlSafe($plainPassword, $newCryptKey);
			$account['hmac'] = \hash_hmac('sha1', $account['pass'], $newCryptKey);

			if (!empty($account['smtp']['pass'])) {
				$plainSmtpPassword = $this->decryptSnappyMailUrlSafeWithSalt((string) $account['smtp']['pass'], $oldCryptKey, $oldAppSalt);
				if (\is_string($plainSmtpPassword)) {
					$account['smtp']['pass'] = \SnappyMail\Crypt::EncryptUrlSafe($plainSmtpPassword, $newCryptKey);
				}
			}

			$mergedAccounts[$accountEmail] = $account;
			++$converted;
		}

		$this->writeAdditionalAccountsFile($targetFile, $mergedAccounts);

		return [
			$this->l->t('Additional accounts migration completed.'),
			$this->l->t('Preserved existing NextSnapMail additional accounts') . ': ' . $preservedExisting,
			$this->l->t('Converted additional accounts') . ': ' . $converted,
			$this->l->t('Skipped because an existing NextSnapMail additional account was kept') . ': ' . $keptBecauseExisting,
			$this->l->t('Skipped additional accounts') . ': ' . $skipped
		];
	}

	private function decryptOldPgpBackupKeyFile(
		string $sourceFile,
		#[\SensitiveParameter] string $oldCryptKey,
		string $oldAppSalt
	): ?string {
		try {
			$key = \json_decode((string) \file_get_contents($sourceFile), true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			return null;
		}

		if (!\is_array($key) || 4 !== \count($key) || empty($key[0]) || empty($key[1]) || empty($key[2]) || empty($key[3])) {
			return null;
		}

		if (!\hash_equals((string) $key[3], \hash_hmac('sha1', (string) $key[2], $oldCryptKey))) {
			return null;
		}

		$nonceOrIv = \base64_decode((string) $key[1], true);
		$data = \base64_decode((string) $key[2], true);
		if (!\is_string($nonceOrIv) || !\is_string($data)) {
			return null;
		}

		$json = $this->decryptSnappyMailValueWithSalt((string) $key[0], $data, $nonceOrIv, $oldCryptKey, $oldAppSalt);
		if (!\is_string($json)) {
			return null;
		}

		try {
			$plainKey = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
			return \is_string($plainKey) ? $plainKey : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function writeNextSnapMailPgpBackupKeyFile(
		string $targetFile,
		#[\SensitiveParameter] string $plainKey,
		#[\SensitiveParameter] string $newCryptKey
	): void {
		$encryptedKey = \SnappyMail\Crypt::Encrypt($plainKey, $newCryptKey);
		$encryptedKey[1] = \base64_encode($encryptedKey[1]);
		$encryptedKey[2] = \base64_encode($encryptedKey[2]);
		$encryptedKey[3] = \hash_hmac('sha1', $encryptedKey[2], $newCryptKey);

		$targetDirectory = \dirname($targetFile);
		if (!\is_dir($targetDirectory) && !\mkdir($targetDirectory, 0700, true) && !\is_dir($targetDirectory)) {
			throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $targetDirectory);
		}

		\RainLoop\Utils::saveFile($targetFile, \json_encode($encryptedKey));
	}

	private function isNextSnapMailPgpBackupKeyFileValid(string $targetFile, #[\SensitiveParameter] string $newCryptKey): bool {
		if (!\is_file($targetFile)) {
			return false;
		}

		try {
			$key = \json_decode((string) \file_get_contents($targetFile), true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			return false;
		}

		return \is_array($key)
			&& 4 === \count($key)
			&& !empty($key[2])
			&& !empty($key[3])
			&& \hash_equals((string) $key[3], \hash_hmac('sha1', (string) $key[2], $newCryptKey));
	}

	private function migratePgpBackups(
		string $email,
		#[\SensitiveParameter] string $password,
		#[\SensitiveParameter] string $newCryptKey,
		bool $reuseExistingCryptKey
	): array {
		SnappyMailHelper::loadApp();

		$oldAppSalt = $this->getOldSnappyMailAppSalt();
		if (!$oldAppSalt) {
			return [$this->l->t('PGP backups skipped: old SnappyMail salt was not found.')];
		}

		$oldCryptKey = $this->getOldMainAccountCryptKey($email, $password, $oldAppSalt);
		if (!$oldCryptKey) {
			return [$this->l->t('PGP backups skipped: old main account crypt key could not be read.')];
		}

		$paths = $this->getAppDataImportPaths();
		$sourceDirectory = $this->getStorageDirectoryPath($paths['source'], $email, '.pgp');
		if (!\is_dir($sourceDirectory)) {
			return [$this->l->t('PGP backups skipped: no old PGP backup folder was found.')];
		}

		$targetDirectory = $this->getStorageDirectoryPath($paths['target'], $email, '.pgp');
		$converted = 0;
		$preservedExisting = 0;
		$skipped = 0;

		foreach (\glob(\rtrim($sourceDirectory, '\\/') . '/*.key') ?: [] as $sourceFile) {
			if (!\is_file($sourceFile)) {
				continue;
			}

			if (\filesize($sourceFile) > self::MAX_PGP_BACKUP_KEY_BYTES) {
				++$skipped;
				continue;
			}

			$targetFile = \rtrim($targetDirectory, '\\/') . '/' . \MailSo\Base\Utils::SecureFileName(\basename($sourceFile));
			if ($reuseExistingCryptKey && $this->isNextSnapMailPgpBackupKeyFileValid($targetFile, $newCryptKey)) {
				++$preservedExisting;
				continue;
			}

			$plainKey = $this->decryptOldPgpBackupKeyFile($sourceFile, $oldCryptKey, $oldAppSalt);
			if (!\is_string($plainKey)) {
				++$skipped;
				continue;
			}

			$this->writeNextSnapMailPgpBackupKeyFile($targetFile, $plainKey, $newCryptKey);
			++$converted;
		}

		return [
			$this->l->t('PGP backup migration completed.'),
			$this->l->t('Converted PGP private key backups') . ': ' . $converted,
			$this->l->t('Preserved existing NextSnapMail PGP private key backups') . ': ' . $preservedExisting,
			$this->l->t('Skipped PGP private key backups') . ': ' . $skipped
		];
	}

	private function decryptSnappyMailValueWithSalt(
		string $method,
		#[\SensitiveParameter] string $data,
		#[\SensitiveParameter] string $nonceOrIv,
		#[\SensitiveParameter] string $key,
		string $appSalt
	): ?string {
		$passphrase = \sha1($key . $appSalt, true);

		try {
			if ('sodium' === $method) {
				if (!\is_callable('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
					return null;
				}

				$result = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
					$data,
					$appSalt,
					$nonceOrIv,
					\str_pad('', \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, $passphrase)
				);

				return \is_string($result) ? $result : null;
			}

			if ('openssl' === $method) {
				if (!\is_callable('openssl_decrypt')) {
					return null;
				}

				$cipher = \RainLoop\Api::Config()->Get('security', 'encrypt_cipher', 'aes-256-cbc-hmac-sha1');
				$result = \openssl_decrypt($data, $cipher, $passphrase, OPENSSL_RAW_DATA, $nonceOrIv);

				return \is_string($result) ? $result : null;
			}

			if ('xxtea' === $method) {
				$xxteaKey = $nonceOrIv . $passphrase;
				$result = \is_callable('xxtea_decrypt')
					? \xxtea_decrypt($data, $xxteaKey)
					: \MailSo\Base\Xxtea::decrypt($data, $xxteaKey);

				return \is_string($result) ? $result : null;
			}
		} catch (\Throwable $e) {
			return null;
		}

		return null;
	}

	private function deleteNextSnapMailAccountForReimport(): array {
		$oldAccounts = $this->getOldSnappyMailAccounts();
		$uids = $_POST['nextsnapmail-delete-account-uid'] ?? [];
		if (!\is_array($uids)) {
			$uids = [$uids];
		}

		$uids = \array_values(\array_unique(\array_filter(\array_map('strval', $uids), static function ($uid) {
			return '' !== $uid;
		})));
		if (!$uids) {
			return [$this->l->t('No account was selected for deletion.')];
		}

		$lines = [];
		foreach ($uids as $uid) {
			if (!isset($oldAccounts[$uid])) {
				$lines[] = $this->l->t('Skipped unknown account') . ': ' . $uid;
				continue;
			}
			$this->config->deleteUserValue($uid, 'nextsnapmail', 'nextsnapmail-email');
			$this->config->deleteUserValue($uid, 'nextsnapmail', 'passphrase');
			$this->config->deleteUserValue($uid, 'nextsnapmail', 'nextsnapmail-password');
			$lines[] = $this->l->t('Deleted NextSnapMail account data for reimport') . ': ' . $uid;
		}

		return $lines;
	}

	private function deleteOldSnappyMailData(): array {
		$confirmation = (string) ($_POST['nextsnapmail-delete-old-snappymail-confirmation'] ?? '');
		if (!\hash_equals(self::DELETE_OLD_SNAPPYMAIL_CONFIRMATION, $confirmation)) {
			return [$this->l->t('Old SnappyMail data was not deleted because the confirmation code was missing or invalid.')];
		}

		$lines = [];
		$deletedPreferences = 0;

		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$qb = $db->getQueryBuilder();
		$query = $qb->delete('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('snappymail')));
		$deletedPreferences = $query->executeStatement();

			$lines[] = $this->l->t('Deleted old SnappyMail database entries') . ': ' . $deletedPreferences;

		$paths = $this->getAppDataImportPaths();
		if (\is_dir($paths['source'])) {
			$this->deleteDirectory($paths['source'], 'appdata_snappymail');
			$lines[] = $this->l->t('Deleted old SnappyMail app data folder') . ': ' . $paths['source'];
		} else {
			$lines[] = $this->l->t('Old SnappyMail app data folder was not found.');
		}

		return $lines;
	}

	private function resetNextSnapMailData(): array {
		$confirmation = (string) ($_POST['nextsnapmail-reset-confirmation'] ?? '');
		if (!\hash_equals(self::RESET_NEXTSNAPMAIL_CONFIRMATION, $confirmation)) {
			return [$this->l->t('NextSnapMail data was not reset because the confirmation code was missing or invalid.')];
		}

		$lines = [];
		$this->clearNextSnapMailRuntimeSession();

		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$qb = $db->getQueryBuilder();
		$query = $qb->delete('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('nextsnapmail')));
		$deletedPreferences = $query->executeStatement();

		$lines[] = $this->l->t('Deleted NextSnapMail database entries') . ': ' . $deletedPreferences;
		$deletedAppConfig = $this->deleteNextSnapMailAppConfig();
		if ($deletedAppConfig) {
			$lines[] = $this->l->t('Deleted NextSnapMail app configuration entries') . ': ' . $deletedAppConfig;
		}

		$paths = $this->getAppDataImportPaths();
		$existingSalt = \is_file($paths['target'] . '/SALT.php')
			? (string) \file_get_contents($paths['target'] . '/SALT.php')
			: '';
		if (\is_dir($paths['target'])) {
			$this->deleteDirectory($paths['target'], 'appdata_nextsnapmail');
			$lines[] = $this->l->t('Deleted NextSnapMail app data folder') . ': ' . $paths['target'];
		} else {
			$lines[] = $this->l->t('NextSnapMail app data folder was not found.');
		}

		foreach ($this->initializeNextSnapMailDataAfterReset($existingSalt) as $line) {
			$lines[] = $line;
		}

		$lines[] = $this->l->t('NextSnapMail reset completed. Reload the page before using the app again.');

		return $lines;
	}

	private function deleteNextSnapMailAppConfig(): int {
		$keys = [
			'gmail-oauth-client-id',
			'gmail-oauth-client-secret',
			'gmail-oauth-domains',
			'gmail-oauth-auto-configure',
			'nextsnapmail-autologin',
			'nextsnapmail-autologin-with-email',
			'nextsnapmail-no-embed',
			'nextsnapmail-debug',
			'nextsnapmail-force-language'
		];
		$deleted = 0;
		foreach ($keys as $key) {
			if ('' !== $this->config->getAppValue('nextsnapmail', $key, '')) {
				++$deleted;
			}
			$this->config->deleteAppValue('nextsnapmail', $key);
		}

		return $deleted;
	}

	private function clearNextSnapMailRuntimeSession(): void {
		try {
			$session = \OC::$server->get(\OCP\ISession::class);
			foreach (['nextsnapmail-passphrase', 'nextsnapmail-nc-uid'] as $key) {
				if (\method_exists($session, 'remove')) {
					$session->remove($key);
				}
				$session->set($key, '');
			}
		} catch (\Throwable $e) {
			// Reset must continue even if the current request has no writable session.
		}

		try {
			SnappyMailHelper::loadApp();
			\RainLoop\Api::Actions()->Logout(true);
			if (\class_exists('\\SnappyMail\\Cookies')) {
				\SnappyMail\Cookies::clear(\RainLoop\Utils::SESSION_TOKEN);
				\SnappyMail\Cookies::clear(\RainLoop\Utils::CONNECTION_TOKEN);
				\SnappyMail\Cookies::clear(\RainLoop\Actions::AUTH_SPEC_TOKEN_KEY);
				\SnappyMail\Cookies::clear(\RainLoop\Actions::AUTH_ADDITIONAL_TOKEN_KEY);
				\SnappyMail\Cookies::clear(\RainLoop\Actions::AUTH_SIGN_ME_TOKEN_KEY);
				\SnappyMail\Cookies::clear(\RainLoop\Actions::AUTH_MAILTO_TOKEN_KEY);
				\SnappyMail\Cookies::clear('nsmadmin');
				\SnappyMail\Cookies::clear('nsmctoken');
			}
		} catch (\Throwable $e) {
			// The data folder may already be incomplete while resetting; that is OK.
		}
	}

	private function initializeNextSnapMailDataAfterReset(string $existingSalt): array {
		$paths = $this->getAppDataImportPaths();
		$target = $this->normalizePath($paths['target']);
		$privateData = $target . '/_data_/_default_/';
		$lines = [];

		if (!\is_dir($target) && !\mkdir($target, 0700, true) && !\is_dir($target)) {
			throw new \RuntimeException($this->l->t('Could not create NextSnapMail app data folder.') . ': ' . $target);
		}

		if ('' !== $existingSalt) {
			\file_put_contents($target . '/SALT.php', $existingSalt);
		}

		foreach (['configs', 'domains', 'plugins', 'storage'] as $folder) {
			$path = $privateData . $folder;
			if (!\is_dir($path) && !\mkdir($path, 0700, true) && !\is_dir($path)) {
				throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $path);
			}
		}

		SnappyMailHelper::loadApp();

		if (!\is_file($target . '/INSTALLED') && \defined('APP_VERSION')) {
			\RainLoop\Utils::saveFile($target . '/INSTALLED', APP_VERSION);
		}
		if (!\is_file($target . '/index.html')) {
			\RainLoop\Utils::saveFile($target . '/index.html', 'Forbidden');
		}
		if (!\is_file($target . '/index.php')) {
			\RainLoop\Utils::saveFile($target . '/index.php', 'Forbidden');
		}
		if (!\is_file($target . '/.htaccess') && \defined('APP_VERSION_ROOT_PATH') && \is_file(APP_VERSION_ROOT_PATH . 'app/.htaccess')) {
			\copy(APP_VERSION_ROOT_PATH . 'app/.htaccess', $target . '/.htaccess');
		}

		$this->installNextcloudPluginAfterReset($privateData . 'plugins/');
		$lines[] = $this->l->t('Reinstalled the required Nextcloud extension.');

		$oConfig = \RainLoop\Api::Config();
		$oConfig->Set('webmail', 'app_path', $this->appManager->getAppWebPath('nextsnapmail') . '/app/');
		$oConfig->Set('webmail', 'allow_languages_on_settings', false);
		$oConfig->Set('webmail', 'loading_description', 'NextSnapMail - based on SnappyMail');
		$oConfig->Set('login', 'allow_languages_on_login', false);
		$oConfig->Set('plugins', 'enable', true);
		$oConfig->Set('plugins', 'enabled_list', 'nextcloud');
		$oConfig->Set('webmail', 'theme', 'NextcloudV25+');
		$oConfig->Set('login', 'default_domain', 'nextcloud');

		$sPassword = \substr(\base64_encode(\random_bytes(16)), 0, 12);
		$oConfig->SetPassword(new \SnappyMail\SensitiveString($sPassword));
		\RainLoop\Utils::saveFile($privateData . 'admin_password.txt', $sPassword . "\n");

		if (!$oConfig->Save()) {
			throw new \RuntimeException($this->l->t('Could not save NextSnapMail configuration after reset.'));
		}

		$oProvider = \RainLoop\Api::Actions()->DomainProvider();
		$oDomain = new \RainLoop\Model\Domain('nextcloud');
		$iSecurityType = \MailSo\Net\Enumerations\ConnectionSecurityType::NONE;
		$oDomain->ImapSettings()->host = 'localhost';
		$oDomain->ImapSettings()->type = $iSecurityType;
		$oDomain->ImapSettings()->shortLogin = true;
		$oDomain->SieveSettings()->enabled = true;
		$oDomain->SieveSettings()->host = 'localhost';
		$oDomain->SieveSettings()->type = $iSecurityType;
		$oDomain->SmtpSettings()->host = 'localhost';
		$oDomain->SmtpSettings()->type = $iSecurityType;
		$oDomain->SmtpSettings()->shortLogin = true;
		$oProvider->Save($oDomain);

		$lines[] = $this->l->t('Created fresh NextSnapMail app data folder.') . ': ' . $target;

		return $lines;
	}

	private function installNextcloudPluginAfterReset(string $pluginsPath): void {
		$appDir = \dirname(\dirname(__DIR__)) . '/app';
		$nextcloudPlugin = $appDir . '/bundled-plugins/nextcloud';
		if (!\is_dir($nextcloudPlugin)) {
			return;
		}

		$this->copyDirectoryForReset($nextcloudPlugin, $pluginsPath . 'nextcloud');
	}

	private function copyDirectoryForReset(string $source, string $destination): void {
		if (!\is_dir($destination) && !\mkdir($destination, 0755, true) && !\is_dir($destination)) {
			throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $destination);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$prefixLength = \strlen($source) + 1;

		foreach ($iterator as $item) {
			$target = $destination . '/' . \substr($item->getPathname(), $prefixLength);
			if ($item->isDir()) {
				if (!\is_dir($target) && !\mkdir($target, 0755, true) && !\is_dir($target)) {
					throw new \RuntimeException($this->l->t('Could not create directory') . ': ' . $target);
				}
			} else if (!\copy($item->getPathname(), $target)) {
				throw new \RuntimeException($this->l->t('Could not copy file') . ': ' . $target);
			}
		}
	}

	private function deleteDirectory(string $directory, string $allowedFolderName): void {
		$directory = $this->realDirectoryPath($directory);
		$dataDirectory = $this->realDirectoryPath(\trim($this->config->getSystemValue('datadirectory', '')));
		if (null === $directory || null === $dataDirectory) {
			throw new \RuntimeException($this->l->t('Refusing to delete unexpected folder'));
		}

		if (!\in_array($allowedFolderName, ['appdata_snappymail', 'appdata_nextsnapmail'], true)) {
			throw new \RuntimeException($this->l->t('Refusing to delete unexpected folder') . ': ' . $allowedFolderName);
		}

		$allowedDirectory = $this->normalizePath($dataDirectory . '/' . $allowedFolderName);
		$allowedDirectoryReal = $this->realDirectoryPath($allowedDirectory);

		if (null === $allowedDirectoryReal || $directory !== $allowedDirectoryReal) {
			throw new \RuntimeException($this->l->t('Refusing to delete unexpected folder') . ': ' . $directory);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $item) {
			if ($item->isDir() && !$item->isLink()) {
				if (!\rmdir($item->getPathname())) {
					throw new \RuntimeException($this->l->t('Could not delete directory') . ': ' . $item->getPathname());
				}
			} else if (!\unlink($item->getPathname())) {
				throw new \RuntimeException($this->l->t('Could not delete file') . ': ' . $item->getPathname());
			}
		}

		if (!\rmdir($directory)) {
			throw new \RuntimeException($this->l->t('Could not delete directory') . ': ' . $directory);
		}
	}

	private function deleteDirectoryTree(string $directory, string $allowedBaseDirectory): void {
		$directory = $this->realDirectoryPath($directory);
		$baseDirectory = $this->realDirectoryPath($allowedBaseDirectory);
		if (null === $directory || null === $baseDirectory) {
			throw new \RuntimeException($this->l->t('Refusing to delete unexpected folder'));
		}

		$this->ensurePathIsInsideDirectory($directory, $baseDirectory);
		if ($directory === $baseDirectory) {
			throw new \RuntimeException($this->l->t('Refusing to delete unexpected folder') . ': ' . $directory);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $item) {
			$path = $item->getPathname();
			$this->ensurePathIsInsideDirectory($path, $baseDirectory);
			if ($item->isDir() && !$item->isLink()) {
				if (!\rmdir($path)) {
					throw new \RuntimeException($this->l->t('Could not delete directory') . ': ' . $path);
				}
			} else if (!\unlink($path)) {
				throw new \RuntimeException($this->l->t('Could not delete file') . ': ' . $path);
			}
		}

		if (!\rmdir($directory)) {
			throw new \RuntimeException($this->l->t('Could not delete directory') . ': ' . $directory);
		}
	}

	private function importSelectedOldSnappyMailAccounts(): array {
		$selected = $_POST['nextsnapmail-selected-accounts'] ?? [];
		if (!\is_array($selected)) {
			$selected = [$selected];
		}

		$selected = \array_values(\array_unique(\array_filter(\array_map('strval', $selected), static function ($uid) {
			return '' !== $uid;
		})));
		$importAppData = !empty($_POST['nextsnapmail-import-appdata']);
		if (!$selected && !$importAppData) {
			return [$this->l->t('No accounts were selected for import.')];
		}

		$accounts = $this->getOldSnappyMailAccounts();
		$lines = [];

		foreach ($selected as $uid) {
			if (!isset($accounts[$uid])) {
				$lines[] = $this->l->t('Skipped unknown account') . ': ' . $uid;
				continue;
			}

			$account = $accounts[$uid];
			$changes = [];
			$skipped = [];

			if ('' !== $account['email']) {
				$emailExisted = '' !== $this->config->getUserValue($uid, 'nextsnapmail', 'nextsnapmail-email', '');
				$this->config->setUserValue($uid, 'nextsnapmail', 'nextsnapmail-email', $account['email']);
				$changes[] = $emailExisted
					? $this->l->t('email address overwritten')
					: $this->l->t('email address');
			} else {
				$skipped[] = $this->l->t('no email address found');
			}

			$passphraseExisted = '' !== $this->config->getUserValue($uid, 'nextsnapmail', 'passphrase', '');
			$passphrase = $this->config->getUserValue($uid, 'snappymail', 'passphrase', '');
			if ('' === $passphrase) {
				$passphrase = $this->config->getUserValue($uid, 'snappymail', 'snappymail-password', '');
			}

			if ('' !== $passphrase) {
				$password = $this->decryptOldSnappyMailPassphrase($passphrase, $account['email']);
				if (null !== $password) {
					$this->config->setUserValue($uid, 'nextsnapmail', 'passphrase',
						SnappyMailHelper::encodePassword($password, \md5($account['email'])));
					$cryptKeyState = $this->prepareNextSnapMailMainCryptKey($account['email'], $password);
					$changes[] = $passphraseExisted
						? $this->l->t('password/passphrase overwritten')
						: $this->l->t('password/passphrase');
					$changes[] = $cryptKeyState['reused']
						? $this->l->t('existing crypt key reused')
						: $this->l->t('crypt key refreshed');
					foreach ($this->migrateAdditionalAccounts(
						$account['email'],
						$password,
						$cryptKeyState['key'],
						(bool) $cryptKeyState['reused']
					) as $line) {
						$skipped[] = $line;
					}
					foreach ($this->migratePgpBackups(
						$account['email'],
						$password,
						$cryptKeyState['key'],
						(bool) $cryptKeyState['reused']
					) as $line) {
						$skipped[] = $line;
					}
				} else {
					$skipped[] = $this->l->t('password/passphrase could not be converted');
				}
			} else {
				$skipped[] = $this->l->t('no password/passphrase found');
			}

			if ($changes) {
				$lines[] = $this->l->t('Imported') . ' ' . $uid . ': ' . \implode(', ', $changes);
			} else {
				$lines[] = $this->l->t('No values imported for') . ' ' . $uid;
			}

			if ($skipped) {
				$lines[] = '  ' . $this->l->t('Skipped') . ': ' . \implode(', ', $skipped);
			}
		}

		$lines[] = '';
		$lines[] = $this->l->t('Selected existing NextSnapMail values were overwritten.');

		if ($importAppData) {
			$lines[] = '';
			foreach ($this->importOldSnappyMailAppData() as $line) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setPersonal(): JSONResponse {
		try {
			$sEmail = '';
			if (isset($_POST['appname'], $_POST['nextsnapmail-password'], $_POST['nextsnapmail-email']) && 'nextsnapmail' === $_POST['appname']) {
				$sUser = \OC::$server->get(\OCP\IUserSession::class)->getUser()->getUID();

				$sEmail = $_POST['nextsnapmail-email'];
				$this->config->setUserValue($sUser, 'nextsnapmail', 'nextsnapmail-email', $sEmail);

				$sPass = $_POST['nextsnapmail-password'];
				if ('******' !== $sPass) {
					$this->config->setUserValue($sUser, 'nextsnapmail', 'passphrase',
						$sPass ? SnappyMailHelper::encodePassword($sPass, \md5($sEmail)) : '');
				}
			} else {
				return new JSONResponse([
					'status' => 'error',
					'Message' => $this->l->t('Invalid argument(s)'),
					'Email' => $sEmail
				]);
			}

			// Logout as the credentials have changed
			SnappyMailHelper::loadApp();
			\RainLoop\Api::Actions()->DoLogout();

			return new JSONResponse([
				'status' => 'success',
				'Message' => $this->l->t('Saved successfully'),
				'Email' => $sEmail
			]);
		} catch (\Throwable $e) {
			// Logout as the credentials might have changed, as exception could be in one attribute
			// TODO: Handle both exceptions separately?
			SnappyMailHelper::loadApp();
			\RainLoop\Api::Actions()->DoLogout();

			return new JSONResponse([
				'status' => 'error',
				'Message' => $e->getMessage()
			]);
		}
	}
}
