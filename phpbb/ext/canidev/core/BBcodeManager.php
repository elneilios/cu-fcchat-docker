<?php
/**
 * @package Ext Common Core
 * @version 1.1.3 20/10/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

class BBcodeManager
{
	private $cache 			= null;
	private $max_bbcode_id	= 0;
	
	protected $bbcode_tool;
	protected $db;
	protected $phpbb_root_path;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface		$db				DB Object
	 * @param string								$root_path		phpBB root path
	 * @param string								$php_ext		phpEx
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		$root_path,
		$php_ext)
	{
		// Load the acp_bbcode class
		if(!class_exists('acp_bbcodes', false))
		{
			include($root_path . 'includes/acp/acp_bbcodes.' . $php_ext);
		}

		$this->bbcode_tool		= new \acp_bbcodes;
		$this->db				= $db;
		$this->phpbb_root_path 	= $root_path;
	}
	
	/**
	 * Get installed bbcodes
	 * @return array
	 */
	public function getInstalledBBcodes()
	{
		if($this->cache === null)
		{
			$this->cache = [];

			$sql = 'SELECT bbcode_id, bbcode_tag, bbcode_match, bbcode_tpl
				FROM ' . BBCODES_TABLE;
			$result = $this->db->sql_query($sql);
			while($row = $this->db->sql_fetchrow($result))
			{
				$row['bbcode_id']	= (int)$row['bbcode_id'];
				$bbcode_tag 		= strtolower($row['bbcode_tag']);
				
				$this->cache[$bbcode_tag] = $row;
				$this->max_bbcode_id = max($this->max_bbcode_id, $row['bbcode_id']);
			}
			$this->db->sql_freeresult($result);

			$this->max_bbcode_id = max($this->max_bbcode_id, NUM_CORE_BBCODES);
		}
		
		return $this->cache;
	}

	/**
	 * Insert BBcodes into DB
	 * 
	 * @param array 	$bbcode_ary 		Array with bbcode's data
	 * @param bool 		$overwrite 			Defines if existing bbcodes will be overwritten
	 * @param bool 		$backup 			Define if original bbcodes will be saved on backup file
	 */
	public function insert($bbcode_ary, $overwrite = false, $backup = false)
	{
		$insert_ary	= [];
		
		$this->getInstalledBBcodes();

		foreach($bbcode_ary as $bbcode_name => $bbcode_array)
		{
			// Build the BBCodes
			$data = $this->bbcode_tool->build_regexp($bbcode_array['bbcode_match'], $bbcode_array['bbcode_tpl']);

			$bbcode_array = array_merge($bbcode_array, [
				'bbcode_tag'			=> $data['bbcode_tag'],
				'bbcode_match'			=> $bbcode_array['bbcode_match'],
				'bbcode_tpl'			=> $bbcode_array['bbcode_tpl'],
				'first_pass_match'		=> $data['first_pass_match'],
				'first_pass_replace'	=> $data['first_pass_replace'],
				'second_pass_match'		=> $data['second_pass_match'],
				'second_pass_replace'	=> $data['second_pass_replace'],
				'display_on_posting'	=> isset($bbcode_array['display_on_posting']) ? $bbcode_array['display_on_posting'] : 1,
				'bbcode_helpline' 		=> isset($bbcode_array['bbcode_helpline']) ? $bbcode_array['bbcode_helpline'] : '',
			]);
			
			$bbcode_tag	= strtolower($bbcode_array['bbcode_tag']);
			$bbcode		= isset($this->cache[$bbcode_tag]) ? $this->cache[$bbcode_tag] : false;
			
			if($bbcode !== false && !$overwrite)
			{
				continue;
			}

			if($bbcode !== false)
			{
				if($backup && $bbcode['bbcode_tpl'] != $bbcode_array['bbcode_tpl'])
				{
					$content = $bbcode['bbcode_match'] . "\n----\n" . $bbcode['bbcode_tpl'] . "\n<!--//-->\n";
					@file_put_contents($this->phpbb_root_path . 'store/bbcode_backup.txt', $content, FILE_APPEND);
				}

				// Update existing BBCode
				$this->updateByTag($bbcode['bbcode_tag'], $bbcode_array);
			}
			else
			{
				if($this->max_bbcode_id > BBCODE_LIMIT - 1)
				{
					continue;
				}

				// Create new BBCode
				$this->max_bbcode_id++;
				$bbcode_array['bbcode_id'] = $this->max_bbcode_id;
				
				$this->cache[$bbcode_tag] = $bbcode_array;
				$insert_ary[] = $bbcode_array;
			}
		}
		
		foreach($insert_ary as $ary)
		{
			$sql = 'INSERT INTO ' . BBCODES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $ary);
			$this->db->sql_query($sql);
		}
	}
	
	/**
	 * Update BBcode by it's tag name
	 * 
	 * @param string|array 	$bbcode_tag 		BBcode tag name
	 * @param array 		$bbcode_data 		Array with new bbcode data
	 */
	public function updateByTag($bbcode_tag, $bbcode_data)
	{
		$bbcode_tag = (is_array($bbcode_tag) ? $bbcode_tag : [$bbcode_tag]);
		
		if(isset($bbcode_data['bbcode_match']) && !isset($bbcode_data['first_pass_match']))
		{
			// Build the BBCode data
			$data = $this->bbcode_tool->build_regexp($bbcode_data['bbcode_match'], $bbcode_data['bbcode_tpl']);

			$bbcode_data = array_merge($bbcode_data, [
				'bbcode_tag'			=> $data['bbcode_tag'],
				'bbcode_match'			=> $bbcode_data['bbcode_match'],
				'bbcode_tpl'			=> $bbcode_data['bbcode_tpl'],
				'first_pass_match'		=> $data['first_pass_match'],
				'first_pass_replace'	=> $data['first_pass_replace'],
				'second_pass_match'		=> $data['second_pass_match'],
				'second_pass_replace'	=> $data['second_pass_replace'],
			]);
		}
		
		$sql = 'UPDATE ' . BBCODES_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $bbcode_data) . '
			WHERE ' . $this->db->sql_in_set('bbcode_tag', $bbcode_tag);
		$this->db->sql_query($sql);
	}
}
