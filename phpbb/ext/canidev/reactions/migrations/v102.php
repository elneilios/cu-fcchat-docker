<?php
/**
 * @package cBB Reactions
 * @version 1.0.2 20/10/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

 namespace canidev\reactions\migrations;

use canidev\reactions\libraries\constants;

class v102 extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritDoc}
	 */
	public function effectively_installed()
	{
		return $this->config->offsetExists('reactions_score_on_profile');
	}

	/**
	 * {@inheritDoc}
	 */
	static public function depends_on()
	{
		return [
			'\canidev\reactions\migrations\v101',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_data()
	{
		return [
			['config.add',		['reactions_score_on_profile', 0]],
			['config.add',		['reactions_button_position', constants::BTN_POSITION_ABOVE]],
			['config.remove',	['reactions_version']],
		];
	}
}
