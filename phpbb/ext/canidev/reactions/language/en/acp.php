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
	'ACP_REACTIONS_CONFIG_EXPLAIN'	=> 'From here you can configure various options regarding reactions.',
	'ACP_REACTIONS_MANAGE_EXPLAIN'	=> 'From here you can manage the reactions that will be available on the forum.',
	'ABOVE'							=> 'Above',
	'BELOW'							=> 'Below',
	'ENABLE_DISABLE'				=> 'Enable/Disable',
	'ERROR_REACTION_NO_EXISTS'		=> 'The selected reaction does not exist',
	'ERROR_SCORE_INVALID'			=> 'Defined score is invalid',
	'NO_REACTIONS' 					=> 'No reaction added.',
	'REACTION_ADD'					=> 'Add reaction',
	'REACTION_COLOR'				=> 'Color',
	'REACTION_COLOR_EXPLAIN'		=> 'Color associated with this reaction.',
	'REACTION_ENABLED'				=> 'Enabled',
	'REACTION_ENABLED_EXPLAIN'		=> 'Defines whether the reaction will be available for use.',
	'REACTION_IMAGE'				=> 'Image',
	'REACTION_IMAGE_EXPLAIN'		=> 'Image/Emoticon related to this reaction.',
	'REACTION_SCORE'				=> 'Score',
	'REACTION_SCORE_EXPLAIN'		=> 'Define the impact (in the form of points) that this reaction will have on the message.',
	'REACTION_TITLE'				=> 'Title',
	'REACTION_TITLE_EXPLAIN'		=> 'Name or title of the reaction',
	
	'REACTIONS_ALLOW_CHANGE'			=> 'Allow reaction change',
	'REACTIONS_ALLOW_CHANGE_EXPLAIN'	=> 'Defines whether users will be able to modify their reaction on messages.',
	'REACTIONS_ALLOW_MYSELF'			=> 'Allow self reactions',
	'REACTIONS_ALLOW_MYSELF_EXPLAIN'	=> 'If enabled, users will be able to react to their own messages.',
	'REACTIONS_ANONYMOUS'				=> 'Anonymous reactions',
	'REACTIONS_ANONYMOUS_EXPLAIN'		=> 'Enable this option if you want user names not to appear in reaction listings.<br />
		<em>This option does not affect users with permission to moderate reactions.</em>',
	'REACTIONS_BUTTON_POSITION'			=> 'Button position',
	'REACTIONS_BUTTON_POSITION_EXPLAIN'	=> 'Defines where the button to react will appear in the message.',
	'REACTIONS_FORCE_ATTACH'			=> 'Force reaction to view attachments',
	'REACTIONS_FORCE_ATTACH_EXPLAIN'	=> 'If enabled, users will have to react to a message in order to see its attachments.',
	'REACTIONS_FORCE_REPLY'				=> 'Force reaction to reply',
	'REACTIONS_FORCE_REPLY_EXPLAIN'		=> 'If enabled, users will have to react to the first post in a topic in order to post replies to it.',
	'REACTIONS_FORUMS'					=> 'Forums where users can react',
	'REACTIONS_FORUMS_EXPLAIN'			=> 'Defines the forums in which reactions will be displayed.<br />
		If you don\'t select any, reactions will be displayed on all forums.<br />
		You can select as many as you like using the <em>Ctrl</em> key on your keyboard.',
	'REACTIONS_LIST_ORDER'				=> 'List order',
	'REACTIONS_LIST_ORDER_EXPLAIN'		=> 'Defines the criteria that will be used to sort the users in the reaction list.',
	'REACTIONS_ORDER_TIME'				=> 'Reaction date',
	'REACTIONS_ORDER_USERNAME'			=> 'Username',
	'REACTIONS_SCORE_ON_PROFILE'		=> 'Show score on profile',
	'REACTIONS_SCORE_ON_PROFILE_EXPLAIN'	=> 'If enabled, the reaction score obtained by each user will be displayed on their profile.',
	'REACTIONS_ZONE_ALL'				=> 'All posts',
	'REACTIONS_ZONE_ONLY_FIRST_POST'	=> 'Only first post',
	'REACTIONS_ZONE_ONLY_REPLIES'		=> 'Only replies',
	'REACTIONS_ZONES'					=> 'Posts on which you can react',
	'REACTIONS_ZONES_EXPLAIN'			=> 'Defines in which posts the option to react will appear.<br /><br />
		<em>The option chosen here can change/limit the behavior of some other extension option.</em>',

	'SCORE_CUSTOM'			=> 'Custom',
	'SCORE_CUSTOM_VALUE'	=> 'Custom (%s)',
	'SELECT_COLOUR'			=> 'Select color',
	'SET_AS_DEFAULT'		=> 'Set as default',

	'scores' 	=> [
		1 		=> 'Positive (+1)',
		0 		=> 'Neutral',
		-1 		=> 'Negative (-1)',
	],
]);
