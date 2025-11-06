<?php if (!defined('IN_PHPBB')) exit; ?>Subject: Post approved - "<?php echo (isset($this->_rootref['POST_SUBJECT'])) ? $this->_rootref['POST_SUBJECT'] : ''; ?>"

Hello <?php echo (isset($this->_rootref['USERNAME'])) ? $this->_rootref['USERNAME'] : ''; ?>,

You are receiving this notification because your post "<?php echo (isset($this->_rootref['POST_SUBJECT'])) ? $this->_rootref['POST_SUBJECT'] : ''; ?>" at "<?php echo (isset($this->_rootref['SITENAME'])) ? $this->_rootref['SITENAME'] : ''; ?>" was approved by a moderator or administrator.

If you want to view the post, click the following link:
<?php echo (isset($this->_rootref['U_VIEW_POST'])) ? $this->_rootref['U_VIEW_POST'] : ''; ?>


If you want to view the topic, click the following link:
<?php echo (isset($this->_rootref['U_VIEW_TOPIC'])) ? $this->_rootref['U_VIEW_TOPIC'] : ''; ?>



<?php echo (isset($this->_rootref['EMAIL_SIG'])) ? $this->_rootref['EMAIL_SIG'] : ''; ?>