<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\controller;

use \canidev\reactions\libraries\constants;

class admin_config extends \canidev\core\controller\acp
{
	protected $dispatcher;

	/**
	 * Constructor
	 *
	 * @param \phpbb\event\dispatcher_interface			$dispatcher		Event dispatcher
	 *
	 * @access public
	 */
	public function __construct(\phpbb\event\dispatcher_interface $dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Display the controller
	 */
	public function display()
	{
		$this->pageHeader('canidev/reactions');

		$this->language->add_lang('acp', 'canidev/reactions');

		$this->tpl_name		= 'acp_reactions_config';
		$this->page_title	= 'ACP_REACTIONS_CONFIG';

		$submit 		= $this->request->is_set_post('submit');
		$form_key 		= 'acp_reactions';
		$error			= [];

		add_form_key($form_key);

		$order_options = [
			constants::ORDER_TIME		=> 'REACTIONS_ORDER_TIME',
			constants::ORDER_USERNAME	=> 'REACTIONS_ORDER_USERNAME',
		];

		$position_options = [
			constants::BTN_POSITION_ABOVE 		=> 'ABOVE',
			constants::BTN_POSITION_BELOW 		=> 'BELOW',
		];

		$zones_options = [
			constants::ZONE_ALL 					=> 'REACTIONS_ZONE_ALL',
			constants::ZONE_ONLY_FIRST_POST 		=> 'REACTIONS_ZONE_ONLY_FIRST_POST',
			constants::ZONE_ONLY_REPLIES			=> 'REACTIONS_ZONE_ONLY_REPLIES',
		];

		$display_vars = [
			'legend1'			=> '',
			'reactions_allow_myself'		=> ['lang' => 'REACTIONS_ALLOW_MYSELF',			'validate' => 'bool',		'type' => 'radio:yes_no'],
			'reactions_allow_change'		=> ['lang' => 'REACTIONS_ALLOW_CHANGE',			'validate' => 'bool',		'type' => 'radio:yes_no'],
			'reactions_anonymous'			=> ['lang' => 'REACTIONS_ANONYMOUS',			'validate' => 'bool',		'type' => 'radio:yes_no'],
			'reactions_force_reply'			=> ['lang' => 'REACTIONS_FORCE_REPLY',			'validate' => 'bool',		'type' => 'radio:yes_no'],
			'reactions_force_attach'		=> ['lang' => 'REACTIONS_FORCE_ATTACH',			'validate' => 'bool',		'type' => 'radio:yes_no'],
			'reactions_score_on_profile'	=> ['lang' => 'REACTIONS_SCORE_ON_PROFILE',		'validate' => 'bool',		'type' => 'radio:yes_no'],
			'reactions_zones'				=> ['lang' => 'REACTIONS_ZONES',				'type' => 'select',			'function' => ['tools', 'make_select'],		'params' => [$zones_options, false, '{CONFIG_VALUE}']],
			'reactions_forums'				=> ['lang' => 'REACTIONS_FORUMS',				'type' => 'custom',			'function' => ['tools', 'forums_select'],	'params' => ['forums', '{CONFIG_VALUE}', 1]],
			'reactions_button_position'		=> ['lang' => 'REACTIONS_BUTTON_POSITION',		'type' => 'select',			'function' => ['tools', 'make_select'],		'params' => [$position_options, false, '{CONFIG_VALUE}']],
			'reactions_list_order'			=> ['lang' => 'REACTIONS_LIST_ORDER',			'type' => 'select',			'function' => ['tools', 'make_select'],		'params' => [$order_options, false, '{CONFIG_VALUE}']],
		];

		$cfg_array = $this->config;

		if($submit)
		{
			$cfg_array = $this->request->variable('config', ['' => ''], true);
			$cfg_array['reactions_forums']	= implode(',', $this->request->variable('forums', [0]));

			if($cfg_array['reactions_zones'] == constants::ZONE_ONLY_REPLIES)
			{
				$cfg_array['reactions_force_reply'] = false;
			}

			if(!check_form_key($form_key))
			{
				$error[] = $this->language->lang('FORM_INVALID');
			}
		}

		/**
		 * @event reactions.acp_config_before
		 * @var	array	display_vars	Array of config values to display and process
		 * @var	bool	submit			Do we display the form or process the submission
		 * @var	array	cfg_array 		Array with data
		 * @var	array 	error 			Array with data errors 
		 * @since 1.0.0
		 */
		$vars = ['display_vars', 'submit', 'cfg_array', 'error'];
		extract($this->dispatcher->trigger_event('reactions.acp_config_before', compact($vars)));

		// We validate the complete config if whished
		validate_config_vars($display_vars, $cfg_array, $error);

		// Do not write values if there is an error
		if(count($error))
		{
			$submit = false;
		}
		
		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach($display_vars as $config_name => $null)
		{
			if(!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

			if($submit)
			{
				$this->config->set($config_name, $config_value);
			}
		}

		if($submit)
		{
			/**
			 * @event reactions.acp_config_after
			 * @var	array	display_vars	Array of config values to display and process
			 * @var	array	cfg_array 		Array with data
			 * @since 1.0.0
			 */
			$vars 	= ['display_vars', 'cfg_array'];
			extract($this->dispatcher->trigger_event('reactions.acp_config_after', compact($vars)));

			//$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_CHAT_CONFIG');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->form_action));
		}

		$this->template->assign_vars([
			'S_ERROR'		=> count($error) > 0,
			'ERROR_MSG'		=> implode('<br />', $error),
		]);
		
		$this->display_vars($display_vars);
	}
}
