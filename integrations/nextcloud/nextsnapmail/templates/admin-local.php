<div class="section">
	<form class="nextsnapmail" action="admin.php" method="post">
		<input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken'] ?>" id="requesttoken">
		<fieldset class="personalblock">
			<h2><?php echo($l->t('NextSnapMail Webmail')); ?></h2>
			<br />
			<?php if ($_['nextsnapmail-admin-panel-link']) { ?>
			<p>
				<a href="<?php echo $_['nextsnapmail-admin-panel-link'] ?>" style="text-decoration: underline">
					<?php echo($l->t('Go to NextSnapMail Webmail admin panel')); ?>
				</a>
			<?php if ($_['nextsnapmail-admin-password']) { ?>
				<br/>
				Username: admin<br/>
				Temporary password: <?php echo $_['nextsnapmail-admin-password']; ?>
			<?php } ?>
			</p>
			<br />
			<?php } ?>
			<p>
				<div style="display: flex;">
					<input type="radio" id="nextsnapmail-noautologin" name="nextsnapmail-autologin" value="0" <?php if (!$_['nextsnapmail-autologin']&&!$_['nextsnapmail-autologin-with-email']) echo 'checked="checked"'; ?> />
					<label style="margin: auto 5px;" for="nextsnapmail-noautologin">
						<?php echo($l->t('Users will login manually, or define credentials in their personal settings for automatic logins.')); ?>
					</label>
				</div>
				<div style="display: flex;">
					<input type="radio" id="nextsnapmail-autologin" name="nextsnapmail-autologin" value="1" <?php if ($_['nextsnapmail-autologin']) echo 'checked="checked"'; ?> />
					<label style="margin: auto 5px;" for="nextsnapmail-autologin">
						<?php echo($l->t('Attempt to automatically login users with their Nextcloud username and password, or user-defined credentials, if set.')); ?>
					</label>
				</div>
				<div style="display: flex;">
					<input type="radio" id="nextsnapmail-autologin-with-email" name="nextsnapmail-autologin" value="2" <?php if ($_['nextsnapmail-autologin-with-email']) echo 'checked="checked"'; ?> />
					<label style="margin: auto 5px;" for="nextsnapmail-autologin-with-email">
						<?php echo($l->t('Attempt to automatically login users with their Nextcloud email and password, or user-defined credentials, if set.')); ?>
					</label>
				</div>
			</p>
			<br />

			<p>
				<input id="nextsnapmail-autologin-oidc" name="nextsnapmail-autologin-oidc" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-autologin-oidc']) echo 'checked="checked"'; ?>>
				<label for="nextsnapmail-autologin-oidc">
					<?php echo($l->t('Attempt to automatically login with OIDC when active')); ?>
				</label>
			</p>
			<br />

			<p>
				<input id="nextsnapmail-no-embed" name="nextsnapmail-no-embed" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-no-embed']) echo 'checked="checked"'; ?>>
				<label for="nextsnapmail-no-embed">
					<?php echo($l->t('Don\'t fully integrate in Nextcloud, use in iframe')); ?>
				</label>
			</p>
			<br />
			<p>
				<input id="nextsnapmail-debug" name="nextsnapmail-debug" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-debug']) echo 'checked="checked"'; ?>>
				<label for="nextsnapmail-debug">
					<?php echo($l->t('Debug')); ?>
				</label>
			</p>
			<br />
			<?php if ($_['can-import-rainloop']) { ?>
			<p>
				<input id="import-rainloop" name="import-rainloop" type="checkbox" class="checkbox">
				<label for="import-rainloop">
					<?php echo($l->t('Import RainLoop data')); ?>
				</label>
			</p>
			<br />
			<?php } ?>

			<p>
				<input id="nextsnapmail-nc-lang" name="nextsnapmail-nc-lang" type="checkbox" class="checkbox" <?php if ($_['nextsnapmail-nc-lang']) echo 'checked="checked"'; ?>>
				<label for="nextsnapmail-nc-lang">
					<?php echo($l->t('Force Nextcloud personal language')); ?>
				</label>
			</p>
			<br />
			<p>
				<label for="nextsnapmail-app_path">
					<?php echo($l->t('app_path')); ?>
				</label>
				<input id="nextsnapmail-app_path" name="nextsnapmail-app_path" type="text" <?php echo 'value="'.\htmlspecialchars($_['nextsnapmail-app_path']).'"'; ?> style="width:20em">
			</p>
			<br />

			<p>
				<button id="nextsnapmail-save-button" name="nextsnapmail-save-button"><?php echo($l->t('Save')); ?></button>
				<div class="nextsnapmail-result-desc" style="white-space: pre"></div>
			</p>
		</fieldset>
	</form>
</div>
