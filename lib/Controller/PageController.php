<?php

namespace OCA\NextSnapMail\Controller;

use OCA\NextSnapMail\Util\SnappyMailHelper;
use OCA\NextSnapMail\ContentSecurityPolicy;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;

class PageController extends Controller
{
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index()
	{
		$config = \OC::$server->get(\OCP\IConfig::class);
		$navigationManager = \OC::$server->get(\OCP\INavigationManager::class);

		$bAdmin = false;
		if (!empty($_SERVER['QUERY_STRING'])) {
			SnappyMailHelper::loadApp();
			$bAdmin = $this->getAdminPanelKey() === $_SERVER['QUERY_STRING'];
			if ($this->isAdminServiceRequest()) {
				$this->ensureNextcloudAdminSession();
			}
			if (!$bAdmin) {
				return SnappyMailHelper::startApp(true);
			}
		}
		if (!$bAdmin && $config->getAppValue('nextsnapmail', 'nextsnapmail-no-embed')) {
			$navigationManager->setActiveEntry('nextsnapmail');
			\OCP\Util::addScript('nextsnapmail', 'nextsnapmail');
			\OCP\Util::addStyle('nextsnapmail', 'style');
			SnappyMailHelper::startApp();
			$response = new TemplateResponse('nextsnapmail', 'index', [
				'nextsnapmail-iframe-url' => SnappyMailHelper::normalizeUrl(SnappyMailHelper::getAppUrl())
					. (empty($_GET['target']) ? '' : "#{$_GET['target']}")
			]);
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedFrameDomain("'self'");
//			$csp->addAllowedFrameAncestorDomain("'self'");
			$response->setContentSecurityPolicy($csp->getPolicy());
			return $response;
		}

		return $this->renderEmbeddedApp($bAdmin);
	}

	/**
	 * Integrated administration entry point.
	 *
	 * Unlike the legacy ?admin URL this route is protected by Nextcloud's
	 * default admin middleware. No @NoAdminRequired annotation must be added.
	 *
	 * @NoCSRFRequired
	 */
	public function admin()
	{
		$this->ensureNextcloudAdminSession();

		if (!empty($_SERVER['QUERY_STRING'])) {
			return $this->handleIntegratedAdminRequest();
		}

		return $this->renderEmbeddedApp(true, true);
	}

	/**
	 * SnappyMail uses its own X-SM-Token for the legacy admin JSON API. The
	 * Nextcloud CSRF middleware cannot validate that token, therefore this
	 * endpoint opts out of the Nextcloud request token check. It remains admin
	 * protected and SnappyMail validates X-SM-Token for every modifying call.
	 *
	 * @NoCSRFRequired
	 */
	public function adminPost()
	{
		$this->ensureNextcloudAdminSession();
		return $this->handleIntegratedAdminRequest();
	}

	private function ensureNextcloudAdminSession(): void
	{
		$user = \OC::$server->get(\OCP\IUserSession::class)->getUser();
		if (!$user || !\OC_User::isAdminUser($user->getUID())) {
			throw new \RuntimeException('NextSnapMail administration requires a Nextcloud administrator session.');
		}

		SnappyMailHelper::loadApp();
		$config = \RainLoop\Api::Config();
		if (!$config->Get('security', 'allow_admin_panel', true)) {
			throw new \RuntimeException('NextSnapMail administration is disabled.');
		}

		$actions = \RainLoop\Api::Actions();
		if (!$actions->IsAdminLoggined(false)) {
			$random = \MailSo\Base\Utils::Sha1Rand();
			if (!$actions->Cacher(null, true)->Set(\RainLoop\KeyPathHelper::SessionAdminKey($random), \time())) {
				throw new \RuntimeException('Could not create the NextSnapMail administrator session.');
			}

			$token = \RainLoop\Utils::EncodeKeyValuesQ(['token', $random]);
			if (!$token) {
				throw new \RuntimeException('Could not encode the NextSnapMail administrator session.');
			}
			\SnappyMail\Cookies::set('nsmadmin', $token);
		}
	}

	private function handleIntegratedAdminRequest()
	{
		$adminKey = $this->getAdminPanelKey();
		$query = \trim((string) ($_SERVER['QUERY_STRING'] ?? ''), " \t\n\r\0\x0B/");
		// The SnappyMail admin client already includes its configured admin
		// path in JSON and asset requests. Prefix only requests that do not
		// contain it yet; otherwise "admin/admin/Json" is produced and domain
		// as well as extension requests are routed to a non-existing service.
		if ($query !== $adminKey && 0 !== \strpos($query, $adminKey . '/')) {
			$query = $adminKey . ($query ? '/' . $query : '');
		}
		$_SERVER['QUERY_STRING'] = $query;
		$_GET[$adminKey] = '1';

		return SnappyMailHelper::startApp(true);
	}

	private function getAdminPanelKey(): string
	{
		$key = (string) \RainLoop\Api::Config()->Get('admin_panel', 'key', '');
		return '' !== $key ? $key : 'admin';
	}

	private function isAdminServiceRequest(): bool
	{
		$query = \trim((string) ($_SERVER['QUERY_STRING'] ?? ''), " \t\n\r\0\x0B/");
		$ampersand = \strpos($query, '&');
		if (false !== $ampersand) {
			$query = \substr($query, 0, $ampersand);
		}
		$adminKey = $this->getAdminPanelKey();
		return $query === $adminKey || 0 === \strpos($query, $adminKey . '/');
	}

	private function protectLegacyAdminRequest(): void
	{
		SnappyMailHelper::loadApp();
		if ($this->isAdminServiceRequest()) {
			$this->ensureNextcloudAdminSession();
		}
	}

	private function renderEmbeddedApp(bool $bAdmin, bool $blank = false)
	{
		$navigationManager = \OC::$server->get(\OCP\INavigationManager::class);
		$navigationManager->setActiveEntry('nextsnapmail');

		\OCP\Util::addStyle('nextsnapmail', 'embed');

		SnappyMailHelper::startApp();
		$oConfig = \RainLoop\Api::Config();
		$oActions = $bAdmin ? new \RainLoop\ActionsAdmin() : \RainLoop\Api::Actions();
		$oHttp = \MailSo\Base\Http::SingletonInstance();
		$oServiceActions = new \RainLoop\ServiceActions($oHttp, $oActions);
		$sAppJsMin = $oConfig->Get('debug', 'javascript', false) ? '' : '.min';
		$sAppCssMin = $oConfig->Get('debug', 'css', false) ? '' : '.min';
		$sLanguage = $oActions->GetLanguage(false);

		$csp = new ContentSecurityPolicy();
		$sNonce = $csp->getSnappyMailNonce();
		$sLoadingDescription = $oConfig->Get('webmail', 'loading_description', 'NextSnapMail - based on SnappyMail');
		if ('SnappyMail' === $sLoadingDescription) {
			$sLoadingDescription = 'NextSnapMail - based on SnappyMail';
		}

		$params = [
			'Admin' => $bAdmin ? 1 : 0,
			'LoadingDescriptionEsc' => \htmlspecialchars($sLoadingDescription, ENT_QUOTES|ENT_IGNORE, 'UTF-8'),
			'BaseTemplates' => \RainLoop\Utils::ClearHtmlOutput($oServiceActions->compileTemplates($bAdmin)),
			'BaseAppBootScript' => \file_get_contents(APP_VERSION_ROOT_PATH.'static/js'.($sAppJsMin ? '/min' : '').'/boot'.$sAppJsMin.'.js'),
			'BaseAppBootScriptNonce' => $sNonce,
			'BaseLanguage' => $oActions->compileLanguage($sLanguage, $bAdmin),
			'BaseAppBootCss' => \file_get_contents(APP_VERSION_ROOT_PATH.'static/css/boot'.$sAppCssMin.'.css'),
			'BaseAppThemeCss' => \preg_replace(
				'/\\s*([:;{},]+)\\s*/s',
				'$1',
				$oActions->compileCss($oActions->GetTheme($bAdmin), $bAdmin)
			)
		];

//		\OCP\Util::addScript('nextsnapmail', '../app/snappymail/v/'.APP_VERSION.'/static/js'.($sAppJsMin ? '/min' : '').'/boot'.$sAppJsMin);

		// Nextcloud html encodes, so addHeader('style') is not possible
//		\OCP\Util::addHeader('style', ['id'=>'app-boot-css'], \file_get_contents(APP_VERSION_ROOT_PATH.'static/css/boot'.$sAppCssMin.'.css'));
		\OCP\Util::addHeader('link', ['type'=>'text/css','rel'=>'stylesheet','href'=>\RainLoop\Utils::WebStaticPath('css/'.($bAdmin?'admin':'app').$sAppCssMin.'.css')], '');
//		\OCP\Util::addHeader('style', ['id'=>'app-theme-style','data-href'=>$params['BaseAppThemeCssLink']], $params['BaseAppThemeCss']);

		$response = new TemplateResponse('nextsnapmail', 'index_embed', $params, $blank ? 'blank' : 'user');

		$response->setContentSecurityPolicy($csp->getPolicy());

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function appGet()
	{
		$this->protectLegacyAdminRequest();
		return SnappyMailHelper::startApp(true);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function appPost()
	{
		$this->protectLegacyAdminRequest();
		return SnappyMailHelper::startApp(true);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexPost()
	{
		$this->protectLegacyAdminRequest();
		return SnappyMailHelper::startApp(true);
	}
}
