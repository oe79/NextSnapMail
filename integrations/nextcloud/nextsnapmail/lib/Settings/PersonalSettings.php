<?php
namespace OCA\NextSnapMail\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class PersonalSettings implements ISettings
{
	private $config;

	public function __construct(IConfig $config)
	{
		$this->config = $config;
	}

	public function getForm()
	{
		$uid = \OC::$server->get(\OCP\IUserSession::class)->getUser()->getUID();
		$sEmail = $this->config->getUserValue($uid, 'nextsnapmail', 'nextsnapmail-email');
		if ($sPass = $this->config->getUserValue($uid, 'nextsnapmail', 'nextsnapmail-password')) {
			$this->config->deleteUserValue($uid, 'nextsnapmail', 'nextsnapmail-password');
			$this->config->setUserValue($uid, 'nextsnapmail', 'passphrase', $sPass);
		}
		$parameters = [
			'nextsnapmail-email' => $sEmail,
			'nextsnapmail-password' => $this->config->getUserValue($uid, 'nextsnapmail', 'passphrase') ? '******' : ''
		];
		\OCP\Util::addScript('nextsnapmail', 'nextsnapmail');
		return new TemplateResponse('nextsnapmail', 'personal_settings', $parameters, '');
	}

	public function getSection()
	{
		return 'nextsnapmail';
	}

	public function getPriority()
	{
		return 50;
	}
}
