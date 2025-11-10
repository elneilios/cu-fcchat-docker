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
	'NO_ATTACH_REACTION'	=> 'You must react to this message in order to view its attachments',
	'NO_POST_REACTION'		=> 'You need to react to the first message to be able to reply',

	'REACTIONS_NOTIFICATION_POST'		=> [
		1	=> '%s has reacted to a message you posted',
		2	=> '%s have reacted to a message you posted',
	],

	'REACTIONS_NOTIFICATION_TYPE_POST'	=> 'Someone reacted to a message you posted',

	'REACTION_SCORE_LABEL_ANONYMOUS'		=> [
		1	=> '1 user has reacted',
		2	=> '%d users have reacted',
	],
	
	'REACTION_SCORE_LABEL_SIMPLE'		=> '%1$s',
	
	'REACTION_SCORE_LABEL_COUNT'		=> [
		1	=> '%1$s and another user',
		2	=> '%1$s and another %2$d users',
	],
	
	'REACTIONS'			=> 'Reactions',
	'REACTIONS_ALL'		=> 'All',
	'REACTIONS_SCORE'	=> 'Score',
	'TOTAL_REACTIONS'	=> 'Reactions',

	'TOTAL_REACTIONS_LABEL'	=> [
		1	=> 'Has reacted to %d message',
		2	=> 'Has reacted to %d messages',
	],

	'REACTIONS_USER_SCORE'	=> 'Reactions score',

	'REACTION_ANGRY'	=> 'Angry',
	'REACTION_ENJOY'	=> 'Haha',
	'REACTION_LIKE'		=> 'Like',
	'REACTION_LOVE'		=> 'Love',
	'REACTION_MATTER'	=> 'Care',
	'REACTION_SAD'		=> 'Sad',
	'REACTION_SURPRISE'	=> 'Wow',
]);
