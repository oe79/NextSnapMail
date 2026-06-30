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
			$bAdmin = \RainLoop\Api::Config()->Get('security', 'admin_panel_key', 'admin') == $_SERVER['QUERY_STRING'];
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

		$response = new TemplateResponse('nextsnapmail', 'index_embed', $params);

		$response->setContentSecurityPolicy($csp->getPolicy());

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function appGet()
	{
		return SnappyMailHelper::startApp(true);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function appPost()
	{
		return SnappyMailHelper::startApp(true);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexPost()
	{
		return SnappyMailHelper::startApp(true);
	}
}
