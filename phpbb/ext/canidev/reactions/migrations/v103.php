<?php
/**
 * @package cBB Reactions
 * @version 1.0.3 26/02/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 * 
 * Migration deactivated because problem with table index on big forums
 */

namespace canidev\reactions\migrations;

class v103 extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritDoc}
	 */
	public function effectively_installed()
	{
		return true;
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
}
