<div class="section">
	<form class="nextsnapmail" action="personal.php" method="post">
		<input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken'] ?>" id="requesttoken">
		<fieldset class="personalblock">
			<h2><?php echo $l->t('NextSnapMail Webmail'); ?></h2>
			<p>
				<?php echo $l->t('Enter an email and password to auto-login to NextSnapMail.'); ?>
			</p>
			<p>
				<input type="text" id="nextsnapmail-email" name="nextsnapmail-email"
					value="<?php echo $_['nextsnapmail-email']; ?>" placeholder="<?php echo($l->t('Email')); ?>" />

				<input type="password" id="nextsnapmail-password" name="nextsnapmail-password"
					value="<?php echo $_['nextsnapmail-password']; ?>" placeholder="<?php echo($l->t('Password')); ?>" />

				<button id="nextsnapmail-save-button" name="nextsnapmail-save-button"><?php echo($l->t('Save')); ?></button>
				&nbsp;&nbsp;<span class="nextsnapmail-result-desc"></span>
			</p>
		</fieldset>
	</form>
</div>
