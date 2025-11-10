<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions;

class ext extends \phpbb\extension\base
{
	const REQUIRED_PHPBB	= '3.2.0';
	const REQUIRED_CORE		= '1.1.10';
	
	/**
	 * {@inheritDoc}
	 */
	public function is_enableable()
	{
		$config 		= $this->container->get('config');
		$ext_manager 	= $this->container->get('ext.manager');
		$language 		= $this->container->get('language');
		
		$language->add_lang('info_acp_reactions', 'canidev/reactions');
		
		// Check phpBB version
		if(phpbb_version_compare($config['version'], self::REQUIRED_PHPBB, '<'))
		{
			return false;
		}

		// Check Core version
		if(!class_exists('\canidev\core\constants') || phpbb_version_compare(\canidev\core\constants::VERSION, self::REQUIRED_CORE, '<'))
		{
			trigger_error($language->lang('CORE_INSTALL_ERROR'), E_USER_WARNING);
		}

		// Check if similar ext is installed
		if($ext_manager->is_enabled('steve/postreactions') || $ext_manager->is_enabled('sitesplat/bbreaction'))
		{
			trigger_error($language->lang('EXT_INSTALL_ERROR'), E_USER_WARNING);
		}
		
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enable_step($old_state)
	{
		if($old_state === false)
		{
			/** @var \phpbb\config\db */
			$config = $this->container->get('config');

			// Fresh install
			if(!$config->offsetExists('reactions_allow_change'))
			{
				define('REACTIONS_FRESH_INSTALL', true);
			}

			// Enable notifications
			/** @var \phpbb\notification\manager */
			$phpbb_notifications = $this->container->get('notification_manager');
			$phpbb_notifications->enable_notifications('canidev.reactions.notification.post');

			// Create upload folder
			\canidev\core\tools::mkdir($this->container->getParameter('core.root_path') . 'files/reactions/', true);
			
			return true;
		}
		
		// Run parent enable step method
		return parent::enable_step($old_state);
	}

	/**
	 * {@inheritDoc}
	 */
	public function disable_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				// Disable notifications
				$phpbb_notifications = $this->container->get('notification_manager');
				$phpbb_notifications->disable_notifications('canidev.reactions.notification.post');
				
				return 'notifications';

			default:
				// Run parent disable step method
				return parent::disable_step($old_state);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function purge_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				// Purge notifications
				$phpbb_notifications = $this->container->get('notification_manager');
				$phpbb_notifications->purge_notifications('canidev.reactions.notification.post');

				return 'notifications';

			default:
				// Run parent purge step method
				return parent::purge_step($old_state);
		}
	}
}
