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
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-autologin',
					isset($_POST['nextsnapmail-autologin']) ? '1' === $_POST['nextsnapmail-autologin'] : false);
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-autologin-with-email',
					isset($_POST['nextsnapmail-autologin']) ? '2' === $_POST['nextsnapmail-autologin'] : false);
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-no-embed', isset($_POST['nextsnapmail-no-embed']));
				$this->config->setAppValue('nextsnapmail', 'nextsnapmail-autologin-oidc', isset($_POST['nextsnapmail-autologin-oidc']));
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

