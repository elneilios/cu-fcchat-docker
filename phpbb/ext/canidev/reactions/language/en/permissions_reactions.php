<?php
/**
 * [English [En]]
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

// DO NOT CHANGE
if(!defined('IN_PHPBB'))
{
	exit;
}

if(empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACL_M_REACTIONS'			=> 'Can moderate reactions',
	'ACL_U_REACTIONS'			=> 'Can react to messages',
	'ACL_U_REACTIONS_VIEW'		=> 'Can view reactions',
]);
