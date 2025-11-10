<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\acp;

class main_info
{
	function module()
	{
		return [
			'filename'	=> '\canidev\reactions\acp\main_module',
			'title'		=> 'ACP_REACTIONS',
			'version'	=> '1.0.4',
			'modes'		=> [
				'config'	=> ['title' => 'ACP_REACTIONS_CONFIG',		'auth' => 'ext_canidev/reactions && acl_a_board', 	'cat' => ['ACP_REACTIONS']],
				'manage'	=> ['title' => 'ACP_REACTIONS_MANAGE',		'auth' => 'ext_canidev/reactions && acl_a_board', 	'cat' => ['ACP_REACTIONS']],
			],
		];
	}
}
