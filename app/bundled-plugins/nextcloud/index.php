<?php

class NextcloudPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME = 'Nextcloud',
		VERSION = '2.38.10',
		RELEASE  = '2026-10-07',
		CATEGORY = 'Integrations',
		DESCRIPTION = 'Integrate with Nextcloud v20+',
		REQUIRED = '2.38.0';

	const
		GMAIL_OAUTH_TOKEN_PREFIX = 'login-gmail-oauth:',
		GMAIL_OAUTH_LEGACY_TOKEN_PREFIX = 'gmail2026:',
		GMAIL_OAUTH_START_PART = 'NextSnapMailGmailOauthStart',
		GMAIL_OAUTH_CALLBACK_PART = 'NextSnapMailGmailOauth',
		GMAIL_OAUTH_STATE_KEY = 'nextsnapmail_gmail_oauth_state',
		GMAIL_OAUTH_LOGIN_URI = 'https://accounts.google.com/o/oauth2/v2/auth',
		GMAIL_OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token',
		GMAIL_OAUTH_USERINFO_URI = 'https://openidconnect.googleapis.com/v1/userinfo';

	public function Init() : void
	{
		if (static::IsIntegrated()) {
			\SnappyMail\Log::debug('Nextcloud', 'integrated');
			$this->UseLangs(true);

			$this->addHook('main.fabrica', 'MainFabrica');
			$this->addHook('filter.app-data', 'FilterAppData');
			$this->addHook('filter.language', 'FilterLanguage');

			$this->addCss('style.css');

			$this->addJs('js/webdav.js');
			$this->addJs('js/gmail-oauth.js');
			$this->addJs('js/vacation.js');

			$this->addJs('js/message.js');
			$this->addHook('json.attachments', 'DoAttachmentsActions');
			$this->addJsonHook('NextcloudSaveMsg', 'NextcloudSaveMsg');

			$this->addJs('js/composer.js');
			$this->addJsonHook('NextcloudAttachFile', 'NextcloudAttachFile');

			$this->addJs('js/messagelist.js');

			$this->addTemplate('templates/PopupsNextcloudFiles.html');
			$this->addTemplate('templates/PopupsNextcloudCalendars.html');
			$this->addTemplate('templates/NextSnapMailVacationUserSettings.html');

//			$this->addHook('login.credentials.step-2', 'loginCredentials2');
//			$this->addHook('login.credentials', 'loginCredentials');
			$this->addHook('imap.before-login', 'beforeLogin');
			$this->addHook('smtp.before-login', 'beforeLogin');
			$this->addHook('sieve.before-login', 'beforeLogin');
			$this->addPartHook(static::GMAIL_OAUTH_START_PART, 'ServiceStartGmailOauth');
			$this->addPartHook(static::GMAIL_OAUTH_CALLBACK_PART, 'ServiceCallbackGmailOauth');
			$this->addHook('filter.http-paths', 'httpPaths');
		} else {
			\SnappyMail\Log::debug('Nextcloud', 'NOT integrated');
			// \OC::$server->getConfig()->getAppValue('nextsnapmail', 'nextsnapmail-no-embed');
			$this->addHook('main.content-security-policy', 'ContentSecurityPolicy');
		}
	}

	public function httpPaths(array &$aPaths) : void
	{
		$path = isset($aPaths[0]) ? \rtrim((string) $aPaths[0], '=') : '';
		if (!$path && !empty($_SERVER['QUERY_STRING'])) {
			$path = (string) \preg_replace('/[=&].*$/', '', $_SERVER['QUERY_STRING']);
		}

		if (\in_array($path, [static::GMAIL_OAUTH_START_PART, static::GMAIL_OAUTH_CALLBACK_PART], true)) {
			$aPaths[0] = $path;
			$this->allowGoogleOAuthRedirect();
		}
	}

	private function allowGoogleOAuthRedirect() : void
	{
		$oConfig = \RainLoop\Api::Config();
		$rule = 'mode=navigate,dest=document,site=cross-site';
		$current = \trim($oConfig->Get('security', 'secfetch_allow', ''));
		if (!\preg_match('/(?:^|;)\s*' . \preg_quote($rule, '/') . '\s*(?:;|$)/', $current)) {
			$oConfig->Set('security', 'secfetch_allow', \trim($current . ';' . $rule, ';'));
		}
	}

	public function ContentSecurityPolicy(\SnappyMail\HTTP\CSP $CSP)
	{
		if (\method_exists($CSP, 'add')) {
			$CSP->add('frame-ancestors', "'self'");
		}
	}

	public function Supported() : string
	{
		return static::IsIntegrated() ? '' : 'Nextcloud not found to use this plugin';
	}

	public static function IsIntegrated()
	{
		return \class_exists('OC') && isset(\OC::$server);
	}

	public static function IsLoggedIn()
	{
		return static::IsIntegrated() && \OC::$server->get(\OCP\IUserSession::class)->isLoggedIn();
	}

	public function loginCredentials(string &$sEmail, string &$sLogin, ?string &$sPassword = null) : void
	{
		/**
		 * This has an issue.
		 * When user changes email address, all settings are gone as the new
		 * _data_/_default_/storage/{domain}/{local-part} is used
		 */
//		$ocUser = \OC::$server->getUserSession()->getUser();
//		$sEmail = $ocUser->getEMailAddress() ?: $ocUser->getPrimaryEMailAddress() ?: $sEmail;
	}

	public function loginCredentials2(string &$sEmail, ?string &$sPassword = null) : void
	{
		$ocUser = \OC::$server->get(\OCP\IUserSession::class)->getUser();
		$sEmail = $ocUser->getEMailAddress() ?: $ocUser->getPrimaryEMailAddress() ?: $sEmail;
	}

	public function beforeLogin(\RainLoop\Model\Account $oAccount, \MailSo\Net\NetClient $oClient, \MailSo\Net\ConnectSettings $oSettings) : void
	{
		if ($this->applyGmailOauthLogin($oAccount, $oSettings)) {
			return;
		}

		// Only login with OIDC access token if
		// it is enabled in config, the user is currently logged in with OIDC,
		// the current snappymail account is the OIDC account and no account defined explicitly
		if ($oAccount instanceof \RainLoop\Model\MainAccount
		 && \OCA\NextSnapMail\Util\SnappyMailHelper::isOIDCLogin()
//		 && $oClient->supportsAuthType('OAUTHBEARER') // v2.28
		 && \str_starts_with($oSettings->passphrase, 'oidc_login|')
		) {
//			$oSettings->passphrase = \OC::$server->getSession()->get('nextsnapmail-passphrase');
			$oSettings->passphrase = \OC::$server->get(\OCP\ISession::class)->get('oidc_access_token');
			\array_unshift($oSettings->SASLMechanisms, 'OAUTHBEARER');
		}
	}

	public function ServiceStartGmailOauth() : string
	{
		$oActions = \RainLoop\Api::Actions();
		$oActions->Http()->ServerNoCache();

		try {
			$client = $this->gmailOauthClient();
			if (!$client) {
				throw new \RuntimeException('Gmail OAuth2 client_id/client_secret is not configured.');
			}

			$state = \bin2hex(\random_bytes(16));
			$mode = (string) ($_GET['mode'] ?? 'main');
			$mode = 'additional' === $mode ? 'additional' : 'main';
			\OC::$server->get(\OCP\ISession::class)->set(static::GMAIL_OAUTH_STATE_KEY, [
				'state' => $state,
				'mode' => $mode
			]);

			$oActions->Location($client->getAuthenticationUrl(
				static::GMAIL_OAUTH_LOGIN_URI,
				$this->gmailOauthCallbackUrl(),
				[
					'scope' => \implode(' ', [
						'openid',
						'email',
						'profile',
						'https://mail.google.com/'
					]),
					'state' => $state,
					'access_type' => 'offline',
					'prompt' => 'consent'
				]
			));
		} catch (\Throwable $oException) {
			$this->redirectToPersonalSettingsWithGmailStatus('', $oException->getMessage());
		}
		exit;
	}

	public function ServiceCallbackGmailOauth() : string
	{
		$oActions = \RainLoop\Api::Actions();
		$oActions->Http()->ServerNoCache();

		try {
			if (isset($_GET['error'])) {
				$message = (string) $_GET['error'];
				if (!empty($_GET['error_description'])) {
					$message .= ': ' . (string) $_GET['error_description'];
				}
				throw new \RuntimeException($message);
			}

			$state = (string) ($_GET['state'] ?? '');
			$session = \OC::$server->get(\OCP\ISession::class);
			$stateData = $session->get(static::GMAIL_OAUTH_STATE_KEY);
			$session->remove(static::GMAIL_OAUTH_STATE_KEY);
			$expectedState = \is_array($stateData) ? (string) ($stateData['state'] ?? '') : (string) $stateData;
			$mode = \is_array($stateData) ? (string) ($stateData['mode'] ?? 'main') : 'main';
			if (!$state || !$expectedState || !\hash_equals($expectedState, $state)) {
				throw new \RuntimeException('Invalid Gmail OAuth2 state.');
			}

			if (empty($_GET['code'])) {
				throw new \RuntimeException('Missing Gmail OAuth2 authorization code.');
			}

			$client = $this->gmailOauthClient();
			if (!$client) {
				throw new \RuntimeException('Gmail OAuth2 client_id/client_secret is not configured.');
			}

			$expiresAt = \time();
			$token = $this->successfulGmailOauthResponse(
				$client->getAccessToken(
					static::GMAIL_OAUTH_TOKEN_URI,
					'authorization_code',
					[
						'code' => (string) $_GET['code'],
						'redirect_uri' => $this->gmailOauthCallbackUrl()
					]
				),
				'Gmail OAuth2 token exchange failed.'
			);

			if (empty($token['access_token'])) {
				throw new \RuntimeException('Gmail OAuth2 access_token missing.');
			}
			if (empty($token['refresh_token'])) {
				throw new \RuntimeException('Gmail OAuth2 refresh_token missing. Please revoke access in Google and try again.');
			}

			$expiresAt += (int) ($token['expires_in'] ?? 3600);
			$client->setAccessToken($token['access_token']);
			$client->setAccessTokenType(\OAuth2\Client::ACCESS_TOKEN_BEARER);

			$userInfo = $this->successfulGmailOauthResponse(
				$client->fetch(static::GMAIL_OAUTH_USERINFO_URI),
				'Gmail OAuth2 userinfo request failed.'
			);

			$email = (string) ($userInfo['email'] ?? '');
			if (!$email) {
				throw new \RuntimeException('Gmail OAuth2 userinfo email missing.');
			}

			$auth = [
				'access_token' => $token['access_token'],
				'refresh_token' => $token['refresh_token'],
				'expires_in' => (int) ($token['expires_in'] ?? 3600),
				'expires' => $expiresAt
			];

			$this->ensureGmailDomain($email);
			if ('additional' === $mode) {
				$this->createOrUpdateGmailAdditionalAccount($email, $auth);
				$oActions->Location($this->appUrl());
				exit;
			}

			$this->storeGmailMainAccount($email, $auth);
			$this->redirectToPersonalSettingsWithGmailStatus('Gmail / Google account connected: ' . $email);
		} catch (\Throwable $oException) {
			$this->redirectToPersonalSettingsWithGmailStatus('', $oException->getMessage());
		}
		exit;
	}

	private function applyGmailOauthLogin(\RainLoop\Model\Account $oAccount, \MailSo\Net\ConnectSettings $oSettings) : bool
	{
		try {
			$auth = $this->gmailOauthAuthFromAccount($oAccount);
			if (empty($auth['expires']) || empty($auth['access_token']) || empty($auth['refresh_token'])) {
				return false;
			}

			if (\time() >= ((int) $auth['expires'] - 60)) {
				$auth = $this->refreshGmailOauthToken($auth);
				if (empty($auth['access_token'])) {
					return false;
				}
				$this->persistGmailOauthAuth($oAccount, $auth);
			}

			$oSettings->passphrase = $auth['access_token'];
			\array_unshift($oSettings->SASLMechanisms, 'OAUTHBEARER', 'XOAUTH2');
			return true;
		} catch (\Throwable $oException) {
			\SnappyMail\Log::warning('Nextcloud', 'Gmail OAuth2 login preparation failed: ' . $oException->getMessage());
			return false;
		}
	}

	private function gmailOauthAuthFromAccount(\RainLoop\Model\Account $oAccount) : array
	{
		$password = (string) $oAccount->ImapPass();
		if ($this->isGmailOauthStoredToken($password)) {
			return $this->decodeGmailOauthStoredToken($password);
		}

		$oActions = \RainLoop\Api::Actions();
		$sessionAuth = $oActions->StorageProvider()->Get(
			$oAccount,
			\RainLoop\Providers\Storage\Enumerations\StorageType::SESSION,
			\RainLoop\Utils::GetSessionToken()
		);
		if ($sessionAuth) {
			$auth = \SnappyMail\Crypt::DecryptFromJSON($sessionAuth, $oAccount->CryptKey());
			return \is_array($auth) ? $auth : [];
		}

		return [];
	}

	private function refreshGmailOauthToken(#[\SensitiveParameter] array $auth) : array
	{
		$client = $this->gmailOauthClient();
		if (!$client) {
			return $auth;
		}

		$expiresAt = \time();
		$response = $client->getAccessToken(
			static::GMAIL_OAUTH_TOKEN_URI,
			'refresh_token',
			['refresh_token' => $auth['refresh_token']]
		);
		$refresh = $this->successfulGmailOauthResponse($response, 'Gmail OAuth2 token refresh failed.');

		if (!empty($refresh['access_token'])) {
			$auth['access_token'] = $refresh['access_token'];
			$auth['expires_in'] = (int) ($refresh['expires_in'] ?? 3600);
			$auth['expires'] = $expiresAt + $auth['expires_in'];
		}

		return $auth;
	}

	private function persistGmailOauthAuth(\RainLoop\Model\Account $oAccount, #[\SensitiveParameter] array $auth) : void
	{
		$storedAuth = $this->encodeGmailOauthStoredToken($auth);
		if ($oAccount instanceof \RainLoop\Model\AdditionalAccount && $this->isGmailOauthStoredToken((string) $oAccount->ImapPass())) {
			$oActions = \RainLoop\Api::Actions();
			$oMainAccount = $oActions->getMainAccountFromToken();
			if ($oMainAccount instanceof \RainLoop\Model\MainAccount) {
				$accounts = $oActions->GetAccounts($oMainAccount);
				if (isset($accounts[$oAccount->Email()])) {
					$sensitiveAuth = new \SnappyMail\SensitiveString($storedAuth);
					$oAccount->setImapPass($sensitiveAuth);
					$oAccount->setSmtpPass($sensitiveAuth);
					$accounts[$oAccount->Email()] = \array_replace(
						$accounts[$oAccount->Email()],
						$oAccount->asTokenArray($oMainAccount)
					);
					$oActions->SetAccounts($oMainAccount, $accounts);
				}
			}
			return;
		}

		if ($oAccount instanceof \RainLoop\Model\MainAccount && static::IsIntegrated()) {
			$user = \OC::$server->get(\OCP\IUserSession::class)->getUser();
			if ($user) {
				$config = \OC::$server->get(\OCP\IConfig::class);
				$config->setUserValue(
					$user->getUID(),
					'nextsnapmail',
					'passphrase',
					\OCA\NextSnapMail\Util\SnappyMailHelper::encodePassword($storedAuth, \md5($oAccount->Email()))
				);
			}
		}
	}

	private function storeGmailMainAccount(string $email, #[\SensitiveParameter] array $auth) : void
	{
		$user = \OC::$server->get(\OCP\IUserSession::class)->getUser();
		if (!$user) {
			throw new \RuntimeException('No active Nextcloud user found.');
		}

		$config = \OC::$server->get(\OCP\IConfig::class);
		$storedAuth = $this->encodeGmailOauthStoredToken($auth);
		$config->setUserValue($user->getUID(), 'nextsnapmail', 'nextsnapmail-email', $email);
		$config->setUserValue(
			$user->getUID(),
			'nextsnapmail',
			'passphrase',
			\OCA\NextSnapMail\Util\SnappyMailHelper::encodePassword($storedAuth, \md5($email))
		);
		$config->deleteUserValue($user->getUID(), 'nextsnapmail', 'nextsnapmail-password');
	}

	private function createOrUpdateGmailAdditionalAccount(string $email, #[\SensitiveParameter] array $auth) : void
	{
		$oActions = \RainLoop\Api::Actions();
		if (!$oActions->GetCapa(\RainLoop\Enumerations\Capa::ADDITIONAL_ACCOUNTS)) {
			throw new \RuntimeException('Additional accounts are disabled.');
		}

		$oMainAccount = $oActions->getMainAccountFromToken();
		if (!$oMainAccount instanceof \RainLoop\Model\MainAccount) {
			throw new \RuntimeException('No active main account found.');
		}

		$oDomain = $oActions->DomainProvider()->getByEmailAddress($email);
		if (!$oDomain) {
			throw new \RuntimeException('No Gmail domain configuration found for ' . $email);
		}

		$storedAuth = new \SnappyMail\SensitiveString($this->encodeGmailOauthStoredToken($auth));
		$oAdditionalAccount = new \RainLoop\Model\AdditionalAccount();
		$oAdditionalAccount->setCredentials(
			$oDomain,
			$email,
			$oDomain->ImapSettings()->fixUsername($email),
			$storedAuth,
			$oDomain->SmtpSettings()->fixUsername($email),
			$storedAuth
		);

		$accounts = $oActions->GetAccounts($oMainAccount);
		$accounts[$oAdditionalAccount->Email()] = $oAdditionalAccount->asTokenArray($oMainAccount);
		$accounts[$oAdditionalAccount->Email()]['name'] = 'Gmail';
		$accounts[$oAdditionalAccount->Email()]['nextsnapmail-gmail-oauth'] = true;
		$oActions->SetAccounts($oMainAccount, $accounts);
	}

	private function ensureGmailDomain(string $email) : void
	{
		$config = \OC::$server->get(\OCP\IConfig::class);
		if (!$config->getAppValue('nextsnapmail', 'gmail-oauth-auto-configure', '1')) {
			return;
		}

		$domain = \strtolower((string) \substr(\strrchr($email, '@') ?: '', 1));
		if (!$domain || !$this->isConfiguredGmailDomain($email)) {
			return;
		}

		$oProvider = \RainLoop\Api::Actions()->DomainProvider();
		$oDomain = $oProvider->Load($domain, false) ?: new \RainLoop\Model\Domain($domain);
		$oDomain->ImapSettings()->host = 'imap.gmail.com';
		$oDomain->ImapSettings()->port = 993;
		$oDomain->ImapSettings()->type = \MailSo\Net\Enumerations\ConnectionSecurityType::SSL;
		$oDomain->ImapSettings()->shortLogin = false;
		$oDomain->ImapSettings()->lowerLogin = true;
		$oDomain->ImapSettings()->SASLMechanisms = ['XOAUTH2', 'OAUTHBEARER'];

		$oDomain->SieveSettings()->enabled = false;
		$oDomain->SieveSettings()->host = '';
		$oDomain->SieveSettings()->port = 4190;
		$oDomain->SieveSettings()->type = \MailSo\Net\Enumerations\ConnectionSecurityType::NONE;

		$oDomain->SmtpSettings()->host = 'smtp.gmail.com';
		$oDomain->SmtpSettings()->port = 587;
		$oDomain->SmtpSettings()->type = \MailSo\Net\Enumerations\ConnectionSecurityType::STARTTLS;
		$oDomain->SmtpSettings()->shortLogin = false;
		$oDomain->SmtpSettings()->lowerLogin = true;
		$oDomain->SmtpSettings()->useAuth = true;
		$oDomain->SmtpSettings()->SASLMechanisms = ['XOAUTH2', 'OAUTHBEARER'];

		$oProvider->Save($oDomain);
	}

	private function isConfiguredGmailDomain(string $email) : bool
	{
		$domain = \strtolower((string) \substr(\strrchr($email, '@') ?: '', 1));
		if (!$domain) {
			return false;
		}

		$raw = \strtolower(\OC::$server->get(\OCP\IConfig::class)->getAppValue('nextsnapmail', 'gmail-oauth-domains', "gmail.com\ngooglemail.com"));
		$domains = \preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$domains = \array_values(\array_unique(\array_map(
			static fn (string $domain) : string => \ltrim(\trim($domain), '@'),
			$domains
		)));

		return \in_array($domain, $domains, true);
	}

	private function gmailOauthClient() : ?\OAuth2\Client
	{
		$config = \OC::$server->get(\OCP\IConfig::class);
		$clientId = \trim($config->getAppValue('nextsnapmail', 'gmail-oauth-client-id', ''));
		$clientSecret = $this->gmailOauthClientSecret();
		if (!$clientId || !$clientSecret) {
			return null;
		}

		$client = new \OAuth2\Client($clientId, $clientSecret, \OAuth2\Client::AUTH_TYPE_FORM);
		$client->setAccessTokenType(\OAuth2\Client::ACCESS_TOKEN_BEARER);

		$sProxy = \RainLoop\Api::Config()->Get('labs', 'curl_proxy', '');
		if (\strlen($sProxy)) {
			$client->setCurlOption(CURLOPT_PROXY, $sProxy);
			$sProxyAuth = \RainLoop\Api::Config()->Get('labs', 'curl_proxy_auth', '');
			if (\strlen($sProxyAuth)) {
				$client->setCurlOption(CURLOPT_PROXYUSERPWD, $sProxyAuth);
			}
		}

		return $client;
	}

	private function gmailOauthCallbackUrl() : string
	{
		return \OC::$server->get(\OCP\IURLGenerator::class)->linkToRouteAbsolute('nextsnapmail.page.index')
			. '?' . static::GMAIL_OAUTH_CALLBACK_PART;
	}

	private function appUrl() : string
	{
		return \OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('nextsnapmail.page.index');
	}

	private function redirectToPersonalSettingsWithGmailStatus(string $message = '', string $error = '') : void
	{
		$session = \OC::$server->get(\OCP\ISession::class);
		if ('' !== $message) {
			$session->set('nextsnapmail-gmail-oauth-message', $message);
		}
		if ('' !== $error) {
			$session->set('nextsnapmail-gmail-oauth-error', 'Gmail OAuth2 login failed: ' . $error);
		}

		\RainLoop\Api::Actions()->Location(
			\OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('settings.PersonalSettings.index', ['section' => 'nextsnapmail'])
		);
	}

	private function gmailOauthClientSecret() : string
	{
		$encrypted = \OC::$server->get(\OCP\IConfig::class)->getAppValue('nextsnapmail', 'gmail-oauth-client-secret', '');
		if ('' === $encrypted) {
			return '';
		}

		$secret = \OCA\NextSnapMail\Util\SnappyMailHelper::decodePassword($encrypted, 'nextsnapmail-gmail-oauth-client-secret');
		return $secret ? (string) $secret : '';
	}

	private function successfulGmailOauthResponse(#[\SensitiveParameter] array $response, string $defaultMessage) : array
	{
		if (200 === (int) ($response['code'] ?? 0) && \is_array($response['result'] ?? null)) {
			return $response['result'];
		}

		$result = \is_array($response['result'] ?? null) ? $response['result'] : [];
		$message = $defaultMessage;
		if (!empty($result['error'])) {
			$message .= ' ' . $result['error'];
			if (!empty($result['error_description'])) {
				$message .= ': ' . $result['error_description'];
			}
		} else if (!empty($response['code'])) {
			$message .= ' HTTP ' . $response['code'];
		}

		throw new \RuntimeException($message);
	}

	private function encodeGmailOauthStoredToken(#[\SensitiveParameter] array $auth) : string
	{
		return static::GMAIL_OAUTH_TOKEN_PREFIX . \rtrim(\strtr(\base64_encode(\json_encode($auth)), '+/', '-_'), '=');
	}

	private function decodeGmailOauthStoredToken(#[\SensitiveParameter] string $value) : array
	{
		$prefix = $this->gmailOauthStoredTokenPrefix($value);
		if ('' === $prefix) {
			return [];
		}
		$encoded = \substr($value, \strlen($prefix));
		$json = \base64_decode(\strtr($encoded, '-_', '+/'), true);
		$data = $json ? \json_decode($json, true) : null;

		return \is_array($data) ? $data : [];
	}

	private function isGmailOauthStoredToken(#[\SensitiveParameter] string $value) : bool
	{
		return '' !== $this->gmailOauthStoredTokenPrefix($value);
	}

	private function gmailOauthStoredTokenPrefix(#[\SensitiveParameter] string $value) : string
	{
		foreach ([static::GMAIL_OAUTH_TOKEN_PREFIX, static::GMAIL_OAUTH_LEGACY_TOKEN_PREFIX] as $prefix) {
			if (\str_starts_with($value, $prefix)) {
				return $prefix;
			}
		}

		return '';
	}

	/*
	\OC::$server->getCalendarManager();
	\OC::$server->getLDAPProvider();
	*/

	public function NextcloudAttachFile() : array
	{
		$aResult = [
			'success' => false,
			'tempName' => ''
		];
		$rSource = null;
		try {
			$sFile = static::NormalizePath($this->jsonParam('file', ''));
			$oNode = static::UserFolder()->get($sFile);
			if (!$oNode instanceof \OCP\Files\File || !$oNode->isReadable()) {
				throw new \RuntimeException('The selected Nextcloud file is not readable');
			}

			$rSource = $oNode->fopen('rb');
			if (!\is_resource($rSource)) {
				throw new \RuntimeException('The selected Nextcloud file could not be opened');
			}

			$oActions = \RainLoop\Api::Actions();
			$oAccount = $oActions->getAccountFromToken();
			if (!$oAccount) {
				throw new \RuntimeException('No active mail account');
			}

			$sSavedName = 'nextcloud-file-' . \sha1($sFile . \microtime(true));
			$oFilesProvider = $oActions->FilesProvider();
			if (!$oFilesProvider->PutFile($oAccount, $sSavedName, $rSource)) {
				throw new \RuntimeException('The selected Nextcloud file could not be copied');
			}

			$iSourceSize = (int) $oNode->getSize();
			$iSavedSize = $oFilesProvider->FileSize($oAccount, $sSavedName);
			if (false === $iSavedSize || $iSourceSize !== (int) $iSavedSize) {
				$oFilesProvider->Clear($oAccount, $sSavedName);
				throw new \RuntimeException('The copied file size does not match the Nextcloud file');
			}

			$aResult += static::FileMetadata($oNode);
			$aResult['tempName'] = $sSavedName;
			$aResult['success'] = true;
		} catch (\Throwable $oException) {
			$aResult['error'] = $oException->getMessage();
		} finally {
			if (\is_resource($rSource)) {
				\fclose($rSource);
			}
		}
		return $this->jsonResponse(__FUNCTION__, $aResult);
	}

	public function NextcloudSaveMsg() : array
	{
		$sSaveFolder = \ltrim($this->jsonParam('folder', ''), '/');
//		$aValues = \RainLoop\Api::Actions()->decodeRawKey($this->jsonParam('msgHash', ''));
		$msgHash = $this->jsonParam('msgHash', '');
		$aValues = \json_decode(\MailSo\Base\Utils::UrlSafeBase64Decode($msgHash), true);
		$aResult = [
			'folder' => '',
			'filename' => '',
			'success' => false
		];
		if (!empty($aValues['folder']) && !empty($aValues['uid'])) {
			try {
				$sSaveFolder = static::NormalizePath($sSaveFolder, true);
				$oTargetFolder = static::FolderAtPath($sSaveFolder);
				$oActions = \RainLoop\Api::Actions();
				$oMailClient = $oActions->MailClient();
				if (!$oMailClient->IsLoggined()) {
					$oAccount = $oActions->getAccountFromToken();
					$oAccount->ImapConnectAndLogin($oActions->Plugins(), $oMailClient->ImapClient(), $oActions->Config());
				}

				$aResult['folder'] = $sSaveFolder;
				$sFileName = \MailSo\Base\Utils::SecureFileName(
					\mb_substr($this->jsonParam('filename', '') ?: \date('YmdHis'), 0, 100)
				) . '.' . \md5($msgHash) . '.eml';
				$sFileName = $oTargetFolder->getNonExistingName($sFileName);
				$aResult['filename'] = $sFileName;

				$oMailClient->MessageMimeStream(
					function ($rResource) use ($oTargetFolder, $sFileName, &$aResult) {
						if (\is_resource($rResource)) {
							$oFile = $oTargetFolder->newFile($sFileName, $rResource);
							$aResult += static::FileMetadata($oFile);
							$aResult['success'] = true;
						}
					},
					(string) $aValues['folder'],
					(int) $aValues['uid'],
					isset($aValues['mimeIndex']) ? (string) $aValues['mimeIndex'] : ''
				);
			} catch (\Throwable $oException) {
				$aResult['error'] = $oException->getMessage();
			}
		}

		return $this->jsonResponse(__FUNCTION__, $aResult);
	}

	public function DoAttachmentsActions(\SnappyMail\AttachmentsAction $data)
	{
		if (static::isLoggedIn() && 'nextcloud' === $data->action) {
			try {
				$sSaveFolder = \ltrim($this->jsonParam('NcFolder', ''), '/');
				$sSaveFolder = $sSaveFolder ?: 'Attachments';
				$sSaveFolder = static::NormalizePath($sSaveFolder, true);
				$oTargetFolder = static::FolderAtPath($sSaveFolder);
				$aSavedFiles = [];
				$aUsedFileNames = [];
				foreach ($data->items as $aItem) {
					try {
						$sSavedFileName = \MailSo\Base\Utils::SecureFileName(
							empty($aItem['fileName']) ? 'file.dat' : $aItem['fileName']
						);
						$sSavedFileName = $sSavedFileName ?: 'file.dat';
						$sSavedFileName = static::NextcloudNonExistingName($oTargetFolder, $sSavedFileName, $aUsedFileNames);
						$aUsedFileNames[\mb_strtolower($sSavedFileName)] = true;
						$oFile = null;
						if (!empty($aItem['data'])) {
							$oFile = $oTargetFolder->newFile($sSavedFileName, $aItem['data']);
						} else if (!empty($aItem['fileHash'])) {
							$fFile = $data->filesProvider->GetFile($data->account, $aItem['fileHash'], 'rb');
							if (\is_resource($fFile)) {
								try {
									$oFile = $oTargetFolder->newFile($sSavedFileName, $fFile);
								} finally {
									\fclose($fFile);
								}
							}
						}
						if (!$oFile instanceof \OCP\Files\File) {
							throw new \RuntimeException('An attachment could not be read');
						}
						$aSavedFiles[] = static::FileMetadata($oFile);
					} catch (\Throwable $oException) {
						\SnappyMail\Log::error('Nextcloud', 'Could not save attachment "' . ($aItem['fileName'] ?? 'file.dat') . '": ' . $oException->getMessage());
					}
				}
				$data->result = [
					'success' => \count($aSavedFiles) === \count($data->items),
					'files' => $aSavedFiles,
					'requested' => \count($data->items),
					'saved' => \count($aSavedFiles)
				];
			} catch (\Throwable $oException) {
				\SnappyMail\Log::error('Nextcloud', $oException->getMessage());
				$data->result = false;
			}
		}
	}

	public function FilterAppData($bAdmin, &$aResult) : void
	{
		if (!$bAdmin && \is_array($aResult)) {
			$ocUser = \OC::$server->get(\OCP\IUserSession::class)->getUser();
			$sUID = $ocUser->getUID();
			$oUrlGen = \OC::$server->get(\OCP\IURLGenerator::class);
			$sWebDAV = $oUrlGen->getAbsoluteURL($oUrlGen->linkTo('', 'remote.php') . '/dav');
//			$sWebDAV = \OCP\Util::linkToRemote('dav');
			$aResult['Nextcloud'] = [
				'UID' => $sUID,
				'WebDAV' => $sWebDAV,
				'CalDAV' => $this->Config()->Get('plugin', 'calendar', false)
//				'WebDAV_files' => $sWebDAV . '/files/' . $sUID
			];
			$config = \OC::$server->get(\OCP\IConfig::class);
			$gmailClientSecret = $config->getAppValue('nextsnapmail', 'gmail-oauth-client-secret', '');
			if ($config->getAppValue('nextsnapmail', 'gmail-oauth-client-id', '') && $gmailClientSecret) {
				$aResult['NextSnapMailGmailOAuth'] = [
					'StartUrl' => \OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('nextsnapmail.page.index')
						. '?' . static::GMAIL_OAUTH_START_PART,
					'AddAccountLabel' => 'Add Gmail / Google account'
				];
			}
			if (empty($aResult['Auth'])) {
				$sEmail = '';
				// Only store the user's password in the current session if they have
				// enabled auto-login using Nextcloud username or email address.
				if ($config->getAppValue('nextsnapmail', 'nextsnapmail-autologin', false)) {
					$sEmail = $sUID;
				} else if ($config->getAppValue('nextsnapmail', 'nextsnapmail-autologin-with-email', false)) {
					$sEmail = $config->getUserValue($sUID, 'settings', 'email', '');
				} else {
					\SnappyMail\Log::debug('Nextcloud', 'nextsnapmail-autologin is off');
				}
				// If the user has set credentials for SnappyMail in their personal
				// settings, override everything before and use those instead.
				$sCustomEmail = $config->getUserValue($sUID, 'nextsnapmail', 'nextsnapmail-email', '');
				if ($sCustomEmail) {
					$sEmail = $sCustomEmail;
				}
				if (!$sEmail) {
					$sEmail = $ocUser->getEMailAddress();
//						?: $ocUser->getPrimaryEMailAddress();
				}
/*
				if ($config->getAppValue('nextsnapmail', 'nextsnapmail-autologin-oidc', false)) {
					if (\OC::$server->getSession()->get('is_oidc')) {
						$sEmail = "{$sUID}@nextcloud";
						$aResult['DevPassword'] = \OC::$server->getSession()->get('oidc_access_token');
					} else {
						\SnappyMail\Log::debug('Nextcloud', 'Not an OIDC login');
					}
				} else {
					\SnappyMail\Log::debug('Nextcloud', 'OIDC is off');
				}
*/
				$aResult['DevEmail'] = $sEmail ?: '';
			} else if (!empty($aResult['ContactsSync'])) {
				$bSave = false;
				if (empty($aResult['ContactsSync']['Url'])) {
					$aResult['ContactsSync']['Url'] = "{$sWebDAV}/addressbooks/users/{$sUID}/contacts/";
					$bSave = true;
				}
				if (empty($aResult['ContactsSync']['User'])) {
					$aResult['ContactsSync']['User'] = $sUID;
					$bSave = true;
				}
				$pass = \OC::$server->get(\OCP\ISession::class)->get('nextsnapmail-passphrase');
				if ($pass/* && empty($aResult['ContactsSync']['Password'])*/) {
					$pass = \SnappyMail\Crypt::DecryptUrlSafe($pass, $sUID);
					if ($pass) {
						$aResult['ContactsSync']['Password'] = $pass;
						$bSave = true;
					}
				}
				if ($bSave) {
					$oActions = \RainLoop\Api::Actions();
					$oAccount = $oActions->getAccountFromToken();
					if (!($oAccount instanceof \RainLoop\Model\AdditionalAccount)) {
						$oActions->setContactsSyncData(
							$oAccount,
							array(
								'Mode' => $aResult['ContactsSync']['Mode'],
								'User' => $aResult['ContactsSync']['User'],
								'Password' => $aResult['ContactsSync']['Password'],
								'Url' => $aResult['ContactsSync']['Url']
							)
						);
					}
				}
			}
		}
	}

	public function FilterLanguage(&$sLanguage, $bAdmin) : void
	{
		if (!\RainLoop\Api::Config()->Get('webmail', 'allow_languages_on_settings', true)) {
			$aResultLang = \SnappyMail\L10n::getLanguages($bAdmin);
			$userId = \OC::$server->get(\OCP\IUserSession::class)->getUser()->getUID();
			$userLang = \OC::$server->get(\OCP\IConfig::class)->getUserValue($userId, 'core', 'lang', 'en');
			$userLang = \strtr($userLang, '_', '-');
			$sLanguage = $this->determineLocale($userLang, $aResultLang);
			// Check if $sLanguage is null
			if (!$sLanguage) {
				$sLanguage = 'en'; // Assign 'en' if $sLanguage is null
			}
		}
	}

	/**
	 * Determine locale from user language.
	 *
	 * @param string $langCode The name of the input.
	 * @param array  $languagesArray The value of the array.
	 *
	 * @return string return locale
	 */
	private function determineLocale(string $langCode, array $languagesArray) : ?string
	{
		// Direct check for the language code
		if (\in_array($langCode, $languagesArray)) {
			return $langCode;
		}

		// Check without country code
		if (\str_contains($langCode, '-')) {
			$langCode = \explode('-', $langCode)[0];
			if (\in_array($langCode, $languagesArray)) {
				return $langCode;
			}
		}

		// Check with uppercase country code
		$langCodeWithUpperCase = $langCode . '-' . \strtoupper($langCode);
		if (\in_array($langCodeWithUpperCase, $languagesArray)) {
			return $langCodeWithUpperCase;
		}

		// If no match is found
		return null;
	}

	/**
	 * @param mixed $mResult
	 */
	public function MainFabrica(string $sName, &$mResult)
	{
		if (static::isLoggedIn()) {
			if ('suggestions' === $sName && $this->Config()->Get('plugin', 'suggestions', true)) {
				if (!\is_array($mResult)) {
					$mResult = array();
				}
				include_once __DIR__ . '/NextcloudContactsSuggestions.php';
				$mResult[] = new NextcloudContactsSuggestions(
					$this->Config()->Get('plugin', 'ignoreSystemAddressbook', true)
				);
			}
/*
			if ($this->Config()->Get('plugin', 'storage', false) && ('storage' === $sName || 'storage-local' === $sName)) {
				require_once __DIR__ . '/storage.php';
				$oDriver = new \NextcloudStorage(APP_PRIVATE_DATA.'storage', $sName === 'storage-local');
			}
*/
		}
	}

	protected function configMapping() : array
	{
		return array(
			\RainLoop\Plugins\Property::NewInstance('suggestions')->SetLabel('Suggestions')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDefaultValue(true),
			\RainLoop\Plugins\Property::NewInstance('ignoreSystemAddressbook')->SetLabel('Ignore system addressbook')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDefaultValue(true),
/*
			\RainLoop\Plugins\Property::NewInstance('storage')->SetLabel('Use Nextcloud user ID in config storage path')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDefaultValue(false)
*/
			\RainLoop\Plugins\Property::NewInstance('calendar')->SetLabel('Enable "Put ICS in calendar"')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDefaultValue(false)
		);
	}

	private static function UserFolder() : \OCP\Files\Folder
	{
		$oUser = \OC::$server->get(\OCP\IUserSession::class)->getUser();
		if (!$oUser) {
			throw new \RuntimeException('No active Nextcloud user');
		}
		return \OC::$server->get(\OCP\Files\IRootFolder::class)->getUserFolder($oUser->getUID());
	}

	private static function FolderAtPath(string $sPath) : \OCP\Files\Folder
	{
		$oFolder = static::UserFolder();
		if ('' === $sPath) {
			return $oFolder;
		}
		foreach (\explode('/', $sPath) as $sPart) {
			$oNode = $oFolder->nodeExists($sPart) ? $oFolder->get($sPart) : $oFolder->newFolder($sPart);
			if (!$oNode instanceof \OCP\Files\Folder) {
				throw new \RuntimeException('A file blocks the selected Nextcloud folder path');
			}
			$oFolder = $oNode;
		}
		return $oFolder;
	}

	private static function NormalizePath(string $sPath, bool $bAllowRoot = false) : string
	{
		$sPath = \trim(\str_replace('\\', '/', $sPath), '/');
		if ('' === $sPath) {
			if ($bAllowRoot) {
				return '';
			}
			throw new \InvalidArgumentException('No Nextcloud file was selected');
		}
		foreach (\explode('/', $sPath) as $sPart) {
			if ('' === $sPart || '.' === $sPart || '..' === $sPart || \str_contains($sPart, "\0")) {
				throw new \InvalidArgumentException('Invalid Nextcloud path');
			}
		}
		return $sPath;
	}

	private static function FileMetadata(\OCP\Files\File $oFile) : array
	{
		return [
			'fileName' => $oFile->getName(),
			'path' => $oFile->getPath(),
			'size' => (int) $oFile->getSize(),
			'mimeType' => $oFile->getMimeType(),
			'mtime' => (int) $oFile->getMTime(),
			'etag' => $oFile->getEtag()
		];
	}

	private static function NextcloudNonExistingName(\OCP\Files\Folder $oFolder, string $sFileName, array $aUsedFileNames) : string
	{
		$sFileName = $oFolder->getNonExistingName($sFileName);
		if (empty($aUsedFileNames[\mb_strtolower($sFileName)])) {
			return $sFileName;
		}

		$sExtension = '';
		$sBaseName = $sFileName;
		$iDotPosition = \strrpos($sFileName, '.');
		if (false !== $iDotPosition && 0 < $iDotPosition) {
			$sBaseName = \substr($sFileName, 0, $iDotPosition);
			$sExtension = \substr($sFileName, $iDotPosition);
		}

		$iCounter = 2;
		do {
			$sCandidate = "{$sBaseName} ({$iCounter}){$sExtension}";
			$sCandidate = $oFolder->getNonExistingName($sCandidate);
			++$iCounter;
		} while (!empty($aUsedFileNames[\mb_strtolower($sCandidate)]));

		return $sCandidate;
	}
}
