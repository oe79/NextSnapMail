<?php

namespace OCA\NextSnapMail\AppInfo;

use OCA\NextSnapMail\Util\SnappyMailHelper;
use OCA\NextSnapMail\Controller\FetchController;
use OCA\NextSnapMail\Controller\PageController;
use OCA\NextSnapMail\Dashboard\UnreadMailWidget;
use OCA\NextSnapMail\Search\Provider;
use OCA\NextSnapMail\Listeners\AccessTokenUpdatedListener;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ISession;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\BeforeUserLoggedOutEvent;
use OCA\OIDCLogin\Events\AccessTokenUpdatedEvent;

class Application extends App implements IBootstrap
{
	public const APP_ID = 'nextsnapmail';

	public function __construct(array $urlParams = [])
	{
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void
	{
		/**
		 * Controllers
		 */
		$context->registerService(
			'PageController', function($c) {
				return new PageController(
					$c->query('AppName'),
					$c->query('Request')
				);
			}
		);

		$context->registerService(
			'FetchController', function($c) {
				return new FetchController(
					$c->query('AppName'),
					$c->query('Request'),
					$c->query(IAppManager::class),
					$c->query(IConfig::class),
					$c->query(IL10N::class)
				);
			}
		);

		/**
		 * Utils
		 */
		$context->registerService(
			'SnappyMailHelper', function($c) {
				return new SnappyMailHelper();
			}
		);

		$context->registerSearchProvider(Provider::class);
		$context->registerEventListener(AccessTokenUpdatedEvent::class, AccessTokenUpdatedListener::class);

		// TODO: Not working yet, needs a Vue UI
//		$context->registerDashboardWidget(UnreadMailWidget::class);
	}

	public function boot(IBootContext $context): void
	{
		$server = $context->getServerContainer();
		$config = $server->get(IConfig::class);
		$session = $server->get(ISession::class);

		if (!\is_dir(\rtrim(\trim($config->getSystemValue('datadirectory', '')), '\\/') . '/appdata_nextsnapmail')) {
			return;
		}

		$dispatcher = $server->get(IEventDispatcher::class);
		$dispatcher->addListener(PostLoginEvent::class, function (PostLoginEvent $Event) use ($session) {
/*
			$config = \OC::$server->getConfig();
			// Only store the user's password in the current session if they have
			// enabled auto-login using Nextcloud username or email address.
			if ($config->getAppValue('nextsnapmail', 'nextsnapmail-autologin', false)
			 || $config->getAppValue('nextsnapmail', 'nextsnapmail-autologin-with-email', false)) {
*/
				$sUID = $Event->getUser()->getUID();
				$session->set('nextsnapmail-nc-uid', $sUID);
				$session->set('nextsnapmail-passphrase', SnappyMailHelper::encodePassword($Event->getPassword(), $sUID));
/*
			}
*/
		});

		$dispatcher->addListener(BeforeUserLoggedOutEvent::class, function (BeforeUserLoggedOutEvent $Event) {
			// https://github.com/nextcloud/server/issues/36083#issuecomment-1387370634
//			\OC::$server->getSession()['nextsnapmail-passphrase'] = '';
			SnappyMailHelper::loadApp();
//			\RainLoop\Api::Actions()->Logout(true);
			\RainLoop\Api::Actions()->DoLogout();
		});

		// https://github.com/nextcloud/impersonate/issues/179
		// https://github.com/nextcloud/impersonate/pull/180
		$class = 'OCA\Impersonate\Events\BeginImpersonateEvent';
		if (\class_exists($class)) {
			$dispatcher->addListener($class, function ($Event) use ($session) {
				$session->set('nextsnapmail-passphrase', '');
				SnappyMailHelper::loadApp();
				\RainLoop\Api::Actions()->Logout(true);
			});
			$dispatcher->addListener('OCA\Impersonate\Events\EndImpersonateEvent', function ($Event) use ($session) {
				$session->set('nextsnapmail-passphrase', '');
				SnappyMailHelper::loadApp();
				\RainLoop\Api::Actions()->Logout(true);
			});
		}
	}
}
