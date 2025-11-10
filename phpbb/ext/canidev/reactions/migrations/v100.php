<?php
/**
 * @package cBB Reactions
 * @version v1.0.0 28/03/2022
 *
 * @copyright (c) 2022 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\migrations;

class v100 extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritDoc}
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'reactions');
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'reactions'	=> [
					'COLUMNS' => [
						'reaction_id'		=> ['UINT:4', NULL, 'auto_increment'],
						'reaction_title'	=> ['VCHAR', ''],
						'reaction_color'	=> ['VCHAR:10', ''],
						'reaction_image'	=> ['VCHAR', ''],
						'reaction_order'	=> ['UINT:4', 0],
						'reaction_score'	=> ['INT:4', 0],
						'reaction_enabled'	=> ['UINT:1', 0],
					],
					'PRIMARY_KEY'	=> 'reaction_id',
				],
				
				$this->table_prefix . 'reactions_data'	=> [
					'COLUMNS' => [
						'reaction_id'		=> ['UINT:4', 0],
						'post_id'			=> ['UINT:8', 0],
						'user_id'			=> ['UINT:8', 0],
						'username'			=> ['VCHAR', ''],
						'reaction_time'		=> ['UINT:11', 0],
					],
					'KEYS'			=> [
						'user_id'		=> ['INDEX', ['post_id', 'user_id']],
					],
				],
			],
			
			'add_columns'	=> [
				$this->table_prefix . 'posts'	=> [
					'post_reaction_score'		=> ['INT:4', 0],
				],
				
				$this->table_prefix . 'topics'	=> [
					'topic_reaction_score'		=> ['INT:4', 0],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function revert_schema()
	{
		return [
			'drop_tables'	=> [
				$this->table_prefix . 'reactions',
				$this->table_prefix . 'reactions_data',
			],

			'drop_columns'	=> [
				$this->table_prefix . 'posts'	=> [
					'post_reaction_score',
				],
				
				$this->table_prefix . 'topics'	=> [
					'topic_reaction_score',
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_data()
	{
		return [
			['config.add', ['reactions_version', '1.0.0']],
			['config.add', ['reactions_allow_change', 1]],
			['config.add', ['reactions_allow_myself', 0]],
			['config.add', ['reactions_force_attach', 0]],
			['config.add', ['reactions_force_reply', 0]],
			['config.add', ['reactions_list_order', \canidev\reactions\libraries\constants::ORDER_TIME]],
	
			// Add the ACP modules
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_REACTIONS']],
			['module.add', [
				'acp',
				'ACP_REACTIONS',
				[
					'module_basename'	=> '\canidev\reactions\acp\main_module',
					'modes'				=> ['config', 'manage'],
				],
			]],
			
			// Define permissions to add and set
			['permission.add', ['u_reactions',		true]],
			['permission.add', ['u_reactions_view',	true]],
			['permission.add', ['m_reactions',		true]],

			['permission.permission_set', ['REGISTERED', 			'u_reactions',		'group']],
			['permission.permission_set', ['REGISTERED', 			'u_reactions_view',	'group']],
			['permission.permission_set', ['GLOBAL_MODERATORS', 	'm_reactions', 		'group']],

			['custom', [[$this, 'add_basic_reactions']]],
		];
	}

	/**
	 * Add default reactions to DB
	 */
	public function add_basic_reactions()
	{
		$sql_ary = [
			[
				'reaction_title'	=> 'REACTION_LIKE',
				'reaction_color'	=> '#0472f9',
				'reaction_image'	=> './reaction/like.svg',
				'reaction_order'	=> 1,
				'reaction_score'	=> 1,
				'reaction_enabled'	=> 1,
			],
			[
				'reaction_title'	=> 'REACTION_LOVE',
				'reaction_color'	=> '#fb003d',
				'reaction_image'	=> './reaction/love.svg',
				'reaction_order'	=> 2,
				'reaction_score'	=> 1,
				'reaction_enabled'	=> 1,
			],
			[
				'reaction_title'	=> 'REACTION_MATTER',
				'reaction_color'	=> '#fb003d',
				'reaction_image'	=> './reaction/matter.svg',
				'reaction_order'	=> 3,
				'reaction_score'	=> 1,
				'reaction_enabled'	=> 1,
			],
			[
				'reaction_title'	=> 'REACTION_ENJOY',
				'reaction_color'	=> '#fcad04',
				'reaction_image'	=> './reaction/enjoy.svg',
				'reaction_order'	=> 4,
				'reaction_score'	=> 0,
				'reaction_enabled'	=> 1,
			],
			[
				'reaction_title'	=> 'REACTION_SURPRISE',
				'reaction_color'	=> '#fcad04',
				'reaction_image'	=> './reaction/surprise.svg',
				'reaction_order'	=> 5,
				'reaction_score'	=> 0,
				'reaction_enabled'	=> 1,
			],
			[
				'reaction_title'	=> 'REACTION_SAD',
				'reaction_color'	=> '#006b9c',
				'reaction_image'	=> './reaction/sad.svg',
				'reaction_order'	=> 6,
				'reaction_score'	=> 0,
				'reaction_enabled'	=> 1,
			],
			[
				'reaction_title'	=> 'REACTION_ANGRY',
				'reaction_color'	=> '#fc0e00',
				'reaction_image'	=> './reaction/angry.svg',
				'reaction_order'	=> 7,
				'reaction_score'	=> -1,
				'reaction_enabled'	=> 1,
			],
		];

		$this->db->sql_multi_insert($this->table_prefix . 'reactions', $sql_ary);
	}
}
