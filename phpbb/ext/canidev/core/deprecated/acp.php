<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 *
 * @deprecated Use controller\acp instead. To be removed on v1.2.0
 */

namespace canidev\core;

class acp extends \canidev\core\controller\acp
{
	const SELECT_PARSE_LANG		= 1;
	const SELECT_MULTIPLE		= 2;

	protected $lang; // Backward compatibility

	/**
	* Constructor
	*
	* @param \phpbb\cache\driver\driver_interface|null			$cache 				Cache instance
	* @param \phpbb\config\config 								$config				Config Object
	* @param \phpbb\language\language							$language			Language Object
	* @param \canidev\core\template|\phpbb\template				$template 			Template Object
	*
	* @access public
	*/
	public function __construct(
		$cache,
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		$template
	)
	{
		$this->cache 		= $cache;
		$this->config 		= $config;
		$this->language 	= $language;
		$this->template 	= $template;
	}

	public function display_vars($display_vars, $tpl_key = 'options', $cfg_array = null, $individual_name = false)
	{
		$this->backward_language_compatibility();

		return parent::display_vars($display_vars, $tpl_key, $cfg_array, $individual_name);
	}

	public function get_title($prefix = '')
	{
		$this->backward_language_compatibility();

		return parent::get_title($prefix);
	}

	/**
	 * Build select input
	*/
	public function make_select($options, $key, $select_ids = '', $flags = self::SELECT_PARSE_LANG, $classname = '')
	{
		global $phpbb_container;
		return $phpbb_container->get('canidev.core.tools')->make_select($options, $key, $select_ids, $flags, $classname);
	}
	
	/**
	 * Create a select with all groups
	*/
	public function groups_select($config_key, $select_groups, $include_all = true)
	{
		global $phpbb_container;
		return $phpbb_container->get('canidev.core.tools')->groups_select($config_key, $select_groups, $include_all);
	}
	
	/**
	 * Make select with the forum names
	*/
	public function forums_select($config_key = 'forums', $select_ids = false, $ignore_cats = false, $classname = '')
	{
		global $phpbb_container;
		return $phpbb_container->get('canidev.core.tools')->forums_select($config_key, $select_ids, $ignore_cats, $classname);
	}

	/* $this->lang is deprecated, kept for compatibility */
	private function backward_language_compatibility()
	{
		if(!isset($this->language) && isset($this->lang))
		{
			$this->language = $this->lang;
		}
	}
}
