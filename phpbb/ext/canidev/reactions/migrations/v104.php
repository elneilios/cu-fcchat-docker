<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 * 
 */

namespace canidev\reactions\migrations;

class v104 extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritDoc}
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'users', 'user_reaction_score');
	}

	/**
	 * {@inheritDoc}
	 */
	static public function depends_on()
	{
		return [
			'\canidev\reactions\migrations\v102',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_schema()
	{
		return [
			'add_columns'	=> [
				$this->table_prefix . 'users'	=> [
					'user_reaction_score'		=> ['INT:4', null],
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
			'drop_columns'	=> [
				$this->table_prefix . 'users'	=> [
					'user_reaction_score',
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
			['config.add', ['reactions_forums', '']],
			['config.add', ['reactions_default', 0]],
			['config.add', ['reactions_anonymous', 0]],
			
			['custom', [[$this, 'adjust_first_score']]],
		];
	}

	/**
	 * Set first user score. 0 on fresh install, null on update. (Fix phpbb_posts index problem)
	 */
	public function adjust_first_score()
	{
		if(defined('REACTIONS_FRESH_INSTALL'))
		{
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_reaction_score = 0
				WHERE user_reaction_score IS NULL';
			$this->db->sql_query($sql);
		}
	}
}
