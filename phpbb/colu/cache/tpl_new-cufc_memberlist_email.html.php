<?php if (!defined('IN_PHPBB')) exit; $this->_tpl_include('overall_header.html'); ?>


<h2 class="titlespace"><?php echo ((isset($this->_rootref['L_SEND_EMAIL_USER'])) ? $this->_rootref['L_SEND_EMAIL_USER'] : ((isset($user->lang['SEND_EMAIL_USER'])) ? $user->lang['SEND_EMAIL_USER'] : '{ SEND_EMAIL_USER }')); ?> <?php echo (isset($this->_rootref['USERNAME'])) ? $this->_rootref['USERNAME'] : ''; ?></h2>

<form method="post" action="<?php echo (isset($this->_rootref['S_POST_ACTION'])) ? $this->_rootref['S_POST_ACTION'] : ''; ?>" id="post">

<div class="panel">
	<div class="inner"><span class="corners-top"><span></span></span>

	<div class="content">
		<?php if ($this->_rootref['ERROR_MESSAGE']) {  ?><p class="error"><?php echo (isset($this->_rootref['ERROR_MESSAGE'])) ? $this->_rootref['ERROR_MESSAGE'] : ''; ?></p><?php } ?>

		<fieldset class="fields2">
		<?php if ($this->_rootref['S_SEND_USER']) {  ?>

			<dl>
				<dt><label><?php echo ((isset($this->_rootref['L_RECIPIENT'])) ? $this->_rootref['L_RECIPIENT'] : ((isset($user->lang['RECIPIENT'])) ? $user->lang['RECIPIENT'] : '{ RECIPIENT }')); ?>:</label></dt>
				<dd><strong><?php echo (isset($this->_rootref['USERNAME'])) ? $this->_rootref['USERNAME'] : ''; ?></strong></dd>
			</dl>
			<dl>
				<dt><label for="subject"><?php echo ((isset($this->_rootref['L_SUBJECT'])) ? $this->_rootref['L_SUBJECT'] : ((isset($user->lang['SUBJECT'])) ? $user->lang['SUBJECT'] : '{ SUBJECT }')); ?>:</label></dt>
				<dd><input class="inputbox autowidth" type="text" name="subject" id="subject" size="50" tabindex="1" value="<?php echo (isset($this->_rootref['SUBJECT'])) ? $this->_rootref['SUBJECT'] : ''; ?>" /></dd>
			</dl>
		<?php } else { ?>

			<dl>
				<dt><label for="email"><?php echo ((isset($this->_rootref['L_EMAIL_ADDRESS'])) ? $this->_rootref['L_EMAIL_ADDRESS'] : ((isset($user->lang['EMAIL_ADDRESS'])) ? $user->lang['EMAIL_ADDRESS'] : '{ EMAIL_ADDRESS }')); ?>:</label></dt>
				<dd><input class="inputbox autowidth" type="text" name="email" id="email" size="50" maxlength="100" tabindex="2" value="<?php echo (isset($this->_rootref['EMAIL'])) ? $this->_rootref['EMAIL'] : ''; ?>" /></dd>
			</dl>
			<dl>
				<dt><label for="name"><?php echo ((isset($this->_rootref['L_REAL_NAME'])) ? $this->_rootref['L_REAL_NAME'] : ((isset($user->lang['REAL_NAME'])) ? $user->lang['REAL_NAME'] : '{ REAL_NAME }')); ?>:</label></dt>
				<dd><input class="inputbox autowidth" type="text" name="name" id="name" size="50" tabindex="3" value="<?php echo (isset($this->_rootref['NAME'])) ? $this->_rootref['NAME'] : ''; ?>" /></dd>
			</dl>
			<dl>
				<dt><label for="lang"><?php echo ((isset($this->_rootref['L_DEST_LANG'])) ? $this->_rootref['L_DEST_LANG'] : ((isset($user->lang['DEST_LANG'])) ? $user->lang['DEST_LANG'] : '{ DEST_LANG }')); ?>:</label><br />
					<span><?php echo ((isset($this->_rootref['L_DEST_LANG_EXPLAIN'])) ? $this->_rootref['L_DEST_LANG_EXPLAIN'] : ((isset($user->lang['DEST_LANG_EXPLAIN'])) ? $user->lang['DEST_LANG_EXPLAIN'] : '{ DEST_LANG_EXPLAIN }')); ?></span></dt>
				<dd><select name="lang"><?php echo (isset($this->_rootref['S_LANG_OPTIONS'])) ? $this->_rootref['S_LANG_OPTIONS'] : ''; ?></select></dd>
			</dl>
		<?php } ?>

		<dl>
			<dt><label for="message"><?php echo ((isset($this->_rootref['L_MESSAGE_BODY'])) ? $this->_rootref['L_MESSAGE_BODY'] : ((isset($user->lang['MESSAGE_BODY'])) ? $user->lang['MESSAGE_BODY'] : '{ MESSAGE_BODY }')); ?>:</label><br />
			<span><?php echo ((isset($this->_rootref['L_EMAIL_BODY_EXPLAIN'])) ? $this->_rootref['L_EMAIL_BODY_EXPLAIN'] : ((isset($user->lang['EMAIL_BODY_EXPLAIN'])) ? $user->lang['EMAIL_BODY_EXPLAIN'] : '{ EMAIL_BODY_EXPLAIN }')); ?></span></dt>
			<dd><textarea class="inputbox" name="message" id="message" rows="15" cols="76" tabindex="4"><?php echo (isset($this->_rootref['MESSAGE'])) ? $this->_rootref['MESSAGE'] : ''; ?></textarea></dd>
		</dl>
		<dl>
			<dt>&nbsp;</dt>
			<dd><label for="cc_email"><input type="checkbox" name="cc_email" id="cc_email" value="1" checked="checked" tabindex="5" /> <?php echo ((isset($this->_rootref['L_CC_EMAIL'])) ? $this->_rootref['L_CC_EMAIL'] : ((isset($user->lang['CC_EMAIL'])) ? $user->lang['CC_EMAIL'] : '{ CC_EMAIL }')); ?></label></dd>
		</dl>
		</fieldset>
	</div>

	<span class="corners-bottom"><span></span></span></div>
</div>

<div class="panel">
	<div class="inner"><span class="corners-top"><span></span></span>
	<div class="content">
		<fieldset class="submit-buttons">
			<input type="submit" tabindex="6" name="submit" class="button1" value="<?php echo ((isset($this->_rootref['L_SEND_EMAIL'])) ? $this->_rootref['L_SEND_EMAIL'] : ((isset($user->lang['SEND_EMAIL'])) ? $user->lang['SEND_EMAIL'] : '{ SEND_EMAIL }')); ?>" />
		</fieldset>
	</div>
	<span class="corners-bottom"><span></span></span></div>
<?php echo (isset($this->_rootref['S_FORM_TOKEN'])) ? $this->_rootref['S_FORM_TOKEN'] : ''; ?>

</div>

</form>

<?php $this->_tpl_include('overall_footer.html'); ?>