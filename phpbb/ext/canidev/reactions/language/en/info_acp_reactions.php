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
	'ACP_REACTIONS'			=> 'Reactions',
	'ACP_REACTIONS_CONFIG'	=> 'Options',
	'ACP_REACTIONS_MANAGE'	=> 'Configure Reactions',
	
	'EXT_INSTALL_ERROR'		=> 'This extension is not compatible with any installed extension.<br />Review the documentation for more information.',
	'CORE_INSTALL_ERROR'	=> 'You have not uploaded all the files from the installation package or you are trying to install an old package.<br />
		Please make sure to upload all the files (including the <em>core</em> folder) and use a package downloaded from the official website.',
]);
