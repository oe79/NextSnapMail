<div class="section nextsnapmail-admin-settings">
	<form class="nextsnapmail" action="admin.php" method="post">
		<input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken'] ?>" id="requesttoken">
		<fieldset class="personalblock">
			<h2><?php echo($l->t('NextSnapMail Webmail')); ?></h2>
			<p class="settings-hint"><?php echo($l->t('This administration area uses your current Nextcloud administrator session. No separate NextSnapMail login is required.')); ?></p>

			<nav class="nextsnapmail-admin-navigation" aria-label="<?php echo \htmlspecialchars($l->t('NextSnapMail administration'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?>">
				<button type="button" class="nextsnapmail-admin-navigation-item active" data-nextsnapmail-page="integration"><?php echo($l->t('Nextcloud integration')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="general"><?php echo($l->t('General')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="domains"><?php echo($l->t('Domains & Google OAuth')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="login"><?php echo($l->t('Login')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="branding"><?php echo($l->t('Branding')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="contacts"><?php echo($l->t('Contacts')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="security"><?php echo($l->t('Security')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="extensions"><?php echo($l->t('Extensions')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="config"><?php echo($l->t('Advanced configuration')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="data"><?php echo($l->t('Import & reset')); ?></button>
				<button type="button" class="nextsnapmail-admin-navigation-item" data-nextsnapmail-page="about"><?php echo($l->t('About')); ?></button>
			</nav>

			<div class="nextsnapmail-integrated-admin" data-nextsnapmail-admin-workspace>
				<iframe id="nextsnapmail-admin-frame" title="<?php echo \htmlspecialchars($l->t('NextSnapMail administration'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?>" src="<?php echo \htmlspecialchars($_['nextsnapmail-integrated-admin-url'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?>#/settings/domains" loading="eager"></iframe>
			</div>

			<section class="nextsnapmail-admin-context" data-nextsnapmail-context="domains" hidden>
				<div class="nextsnapmail-settings-card">
					<h3><?php echo($l->t('Gmail / Google OAuth2')); ?></h3>
					<p class="settings-hint"><?php echo($l->t('Configure this to let users connect Gmail or Google Workspace accounts without a password.')); ?></p>
					<p><label for="nextsnapmail-gmail-oauth-client-id"><?php echo($l->t('Client ID')); ?></label><input id="nextsnapmail-gmail-oauth-client-id" name="nextsnapmail-gmail-oauth-client-id" type="text" value="<?php echo \htmlspecialchars($_['gmail-oauth-client-id']); ?>"></p>
					<p><label for="nextsnapmail-gmail-oauth-client-secret"><?php echo($l->t('Client Secret')); ?></label><input id="nextsnapmail-gmail-oauth-client-secret" name="nextsnapmail-gmail-oauth-client-secret" type="password" placeholder="<?php echo $_['gmail-oauth-client-secret-set'] ? \htmlspecialchars($l->t('Leave empty to keep existing secret')) : ''; ?>"></p>
					<p><label for="nextsnapmail-gmail-oauth-domains"><?php echo($l->t('Email domains')); ?></label><textarea id="nextsnapmail-gmail-oauth-domains" name="nextsnapmail-gmail-oauth-domains" rows="3"><?php echo \htmlspecialchars($_['gmail-oauth-domains']); ?></textarea></p>
					<p><input id="nextsnapmail-gmail-oauth-auto-configure" name="nextsnapmail-gmail-oauth-auto-configure" type="checkbox" class="checkbox" <?php if ($_['gmail-oauth-auto-configure']) echo 'checked="checked"'; ?>><label for="nextsnapmail-gmail-oauth-auto-configure"><?php echo($l->t('Automatically configure Gmail domains')); ?></label></p>
					<p class="settings-hint nextsnapmail-callback-url"><?php echo($l->t('Authorized redirect URI for Google Cloud Console:')); ?> <code><?php echo \htmlspecialchars($_['gmail-oauth-callback-url'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?></code></p>
				</div>
			</section>

			<section class="nextsnapmail-admin-context" data-nextsnapmail-context="extensions" hidden>
				<div class="nextsnapmail-settings-card">
					<h3><?php echo($l->t('Install plugin package')); ?></h3>
					<p class="settings-hint"><?php echo($l->t('Upload only trusted SnappyMail/NextSnapMail plugin packages. Plugins are executable PHP code.')); ?></p>
					<input id="nextsnapmail-plugin-package" name="nextsnapmail-plugin-package" type="file" accept=".tgz,.tar.gz,.zip">
					<p><input id="nextsnapmail-plugin-overwrite" name="nextsnapmail-plugin-overwrite" type="checkbox" class="checkbox"><label for="nextsnapmail-plugin-overwrite"><?php echo($l->t('Overwrite existing plugin after creating a backup')); ?></label></p>
					<button id="nextsnapmail-upload-plugin-button" type="button"><?php echo($l->t('Upload and install plugin')); ?></button>
					<div class="nextsnapmail-plugin-upload-result-desc" style="white-space: pre-wrap"></div>
				</div>
			</section>

			<section class="nextsnapmail-admin-page active" data-nextsnapmail-panel="integration">
				<h3><?php echo($l->t('Nextcloud integration')); ?></h3>
				<div class="nextsnapmail-settings-card">
					<p class="nextsnapmail-radio-row"><input type="radio" id="nextsnapmail-noautologin" name="nextsnapmail-autologin" value="0" <?php if (!$_['nextsnapmail-autologin']&&!$_['nextsnapmail-autologin-with-email']) echo 'checked="checked"'; ?>><label for="nextsnapmail-noautologin"><?php echo($l->t('Users will login manually, or define credentials in their personal settings for automatic logins.')); ?></label></p>
					<p class="nextsnapmail-radio-row"><input type="radio" id="nextsnapmail-autologin" name="nextsnapmail-autologin" value="1" <?php if ($_['nextsnapmail-autologin']) echo 'checked="checked"'; ?>><label for="nextsnapmail-autologin"><?php echo($l->t('Attempt to automatically login users with their Nextcloud username and password, or user-defined credentials, if set.')); ?></label></p>
					<p class="nextsnapmail-radio-row"><input type="radio" id="nextsnapmail-autologin-with-email" name="nextsnapmail-autologin" value="2" <?php if ($_['nextsnapmail-autologin-with-email']) echo 'checked="checked"'; ?>><label for="nextsnapmail-autologin-with-email"><?php echo($l->t('Attempt to automatically login users with their Nextcloud email and password, or user-defined credentials, if set.')); ?></label></p>
					<p><input id="nextsnapmail-autologin-oidc" name="nextsnapmail-autologin-oidc" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-autologin-oidc']) echo 'checked="checked"'; ?>><label for="nextsnapmail-autologin-oidc"><?php echo($l->t('Attempt to automatically login with OIDC when active')); ?></label></p>
					<p><input id="nextsnapmail-debug" name="nextsnapmail-debug" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-debug']) echo 'checked="checked"'; ?>><label for="nextsnapmail-debug"><?php echo($l->t('Debug')); ?></label></p>
					<?php if ($_['can-import-rainloop']) { ?><p><input id="import-rainloop" name="import-rainloop" type="checkbox" class="checkbox"><label for="import-rainloop"><?php echo($l->t('Import RainLoop data')); ?></label></p><?php } ?>
					<p><input id="nextsnapmail-nc-lang" name="nextsnapmail-nc-lang" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-nc-lang']) echo 'checked="checked"'; ?>><label for="nextsnapmail-nc-lang"><?php echo($l->t('Force Nextcloud personal language')); ?></label></p>
					<p><label for="nextsnapmail-app_path"><?php echo($l->t('app_path')); ?></label><input id="nextsnapmail-app_path" name="nextsnapmail-app_path" type="text" value="<?php echo \htmlspecialchars($_['nextsnapmail-app_path']); ?>"></p>
					<h4><?php echo($l->t('Integrated functions')); ?></h4>
					<p><input id="nextcloud-plugin-suggestions" name="nextcloud-plugin-suggestions" type="checkbox" class="checkbox" <?php if ($_['nextcloud-plugin-suggestions']) echo 'checked="checked"'; ?>><label for="nextcloud-plugin-suggestions"><?php echo($l->t('Use Nextcloud contacts for recipient suggestions')); ?></label></p>
					<p><input id="nextcloud-plugin-ignore-system-addressbook" name="nextcloud-plugin-ignore-system-addressbook" type="checkbox" class="checkbox" <?php if ($_['nextcloud-plugin-ignore-system-addressbook']) echo 'checked="checked"'; ?>><label for="nextcloud-plugin-ignore-system-addressbook"><?php echo($l->t('Ignore the system address book when using Nextcloud contacts')); ?></label></p>
					<p><input id="nextcloud-plugin-calendar" name="nextcloud-plugin-calendar" type="checkbox" class="checkbox" <?php if ($_['nextcloud-plugin-calendar']) echo 'checked="checked"'; ?>><label for="nextcloud-plugin-calendar"><?php echo($l->t('Allow saving calendar invitations to a Nextcloud calendar')); ?></label></p>
				</div>
			</section>

			<section class="nextsnapmail-admin-page" data-nextsnapmail-panel="data" hidden>
				<h3><?php echo($l->t('Import & reset')); ?></h3>
				<p class="settings-hint"><?php echo($l->t('Import existing SnappyMail data or reset this NextSnapMail installation.')); ?></p>
				<div class="nextsnapmail-settings-card">
					<h3><?php echo($l->t('Import old SnappyMail data')); ?></h3>
					<button id="nextsnapmail-import-snappymail-button" type="button"><?php echo($l->t('Import old SnappyMail data')); ?></button>
					<div class="nextsnapmail-import-result-desc"></div>
				</div>
				<div class="nextsnapmail-settings-card nextsnapmail-danger-zone">
					<h3><?php echo($l->t('Reset NextSnapMail')); ?></h3>
					<p class="settings-hint"><?php echo($l->t('Remove all NextSnapMail account settings and recreate the application data folder.')); ?></p>
					<button id="nextsnapmail-reset-button" type="button"><?php echo($l->t('Reset NextSnapMail')); ?></button>
					<div class="nextsnapmail-reset-result-desc" style="white-space: pre-wrap"></div>
				</div>
			</section>

			<div class="nextsnapmail-save-area" data-nextsnapmail-save-area>
				<button id="nextsnapmail-save-button" name="nextsnapmail-save-button"><?php echo($l->t('Save')); ?></button>
				<div class="nextsnapmail-result-desc" style="white-space: pre"></div>
			</div>
		</fieldset>
	</form>
</div>
