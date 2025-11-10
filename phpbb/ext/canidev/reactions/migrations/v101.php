<?php
/**
 * @package cBB Reactions
 * @version 1.0.1 15/01/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

 namespace canidev\reactions\migrations;

class v101 extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritDoc}
	 */
	public function effectively_installed()
	{
		return $this->config->offsetExists('reactions_zones');
	}

	/**
	 * {@inheritDoc}
	 */
	static public function depends_on()
	{
		return [
			'\canidev\reactions\migrations\v100',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_data()
	{
		return [
			['config.add',		['reactions_zones', \canidev\reactions\libraries\constants::ZONE_ALL]],
			['config.update',	['reactions_version', '1.0.1']],
		];
	}
}
