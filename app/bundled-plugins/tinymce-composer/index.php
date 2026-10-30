<?php

class TinymceComposerPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME = 'TinyMCE Composer',
		AUTHOR = 'LibertyTalk FM, Inc.',
		URL = 'https://github.com/oe79/NextSnapMail',
		VERSION = '1.0.1',
		RELEASE = '2026-10-30',
		REQUIRED = '2.34.0',
		LICENSE = 'AGPL v3',
		DESCRIPTION = 'TinyMCE as the message composer: word-processor editing, clean markup, plain and source modes';

	public function Init() : void
	{
		$path = APP_VERSION_ROOT_PATH . 'static/tinymce-composer';

		// Apache refuses symlinks to plugin folders, so the static files are copied once
		if (!\is_dir($path)) {
			$old_mask = \umask(0022);
			\mkdir($path, 0755, true);
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(__DIR__ . '/static', \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ($iterator as $item) {
				if ($item->isDir()) {
					\mkdir($path . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
				} else {
					\copy($item, $path . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
				}
			}
			\umask($old_mask);
		}

		if (\is_file("{$path}/tinymce/tinymce.min.js")) {
			$this->addCss('style.css');
			$this->addJs('tinymce-composer.js');
			$this->addHook('filter.app-data', 'FilterAppData');
		}
	}

	/**
	 * Make TinyMCE the default editor for users who have never chosen one.
	 * A saved choice, including Squire, is never overridden.
	 */
	public function FilterAppData(bool $bAdmin, array &$aAppData) : void
	{
		if ($bAdmin) {
			return;
		}
		try {
			$oActions = $this->Manager()->Actions();
			$oAccount = $oActions->getAccountFromToken();
			if ($oAccount) {
				$oSettings = $oActions->SettingsProvider()->Load($oAccount);
				if ($oSettings && null === $oSettings->GetConf('editorWysiwyg', null)) {
					$aAppData['editorWysiwyg'] = 'TinyMCE';
				}
			}
		} catch (\Throwable $e) {
			// Not logged in yet, or settings unavailable: leave the app's default alone
		}
	}
}
