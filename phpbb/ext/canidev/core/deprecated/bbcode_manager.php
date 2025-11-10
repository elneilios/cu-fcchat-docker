<?php
/**
 * @package Ext Common Core
 * @version 1.1.3 20/10/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

/**
 * @deprecated 		To be removed in v1.2.0
 */
class bbcode_manager extends BBcodeManager
{
	/**
	 * Get installed bbcodes
	 * @return array
	 */
	public function get_installed_bbcodes()
	{
		return $this->getInstalledBBcodes();
	}

	/**
	 * Update BBcode by it's tag name
	 * 
	 * @param string|array 	$bbcode_tag 		BBcode tag name
	 * @param array 		$bbcode_data 		Array with new bbcode data
	 */
	public function update_by_tag($bbcode_tag, $bbcode_data)
	{
		return $this->updateByTag($bbcode_tag, $bbcode_data);
	}
}
