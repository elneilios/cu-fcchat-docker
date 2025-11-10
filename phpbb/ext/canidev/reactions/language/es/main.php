<?php
/**
 * [Spanish [Es]]
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
	'NO_ATTACH_REACTION'	=> 'Debe reaccionar a este mensaje para poder ver sus archivos adjuntos',
	'NO_POST_REACTION'		=> 'Necesita reaccionar al primer mensaje para poder responder',
	
	'REACTIONS_NOTIFICATION_POST'		=> [
		1	=> '%s ha reaccionado a un mensaje que ha publicado',
		2	=> '%s han reaccionado a un mensaje que ha publicado',
	],

	'REACTIONS_NOTIFICATION_TYPE_POST'	=> 'Alguien reaccionó a un mensaje que ha publicado',

	'REACTION_SCORE_LABEL_ANONYMOUS'		=> [
		1	=> '1 usuario ha reaccionado',
		2	=> '%d usuarios han reaccionado',
	],
	
	'REACTION_SCORE_LABEL_SIMPLE'		=> '%1$s',
	
	'REACTION_SCORE_LABEL_COUNT'		=> [
		1	=> '%1$s y otro usuario',
		2	=> '%1$s y otros %2$d usuarios',
	],

	'REACTIONS'				=> 'Reacciones',
	'REACTIONS_ALL'			=> 'Todas',
	'REACTIONS_SCORE'		=> 'Calificación',
	'TOTAL_REACTIONS'		=> 'Reacciones',
	
	'TOTAL_REACTIONS_LABEL'	=> [
		1	=> 'Ha reaccionado a %d mensaje',
		2	=> 'Ha reaccionado a %d mensajes',
	],

	'REACTIONS_USER_SCORE'	=> 'Puntuación reacciones',

	'REACTION_ANGRY'	=> 'Me enfada',
	'REACTION_ENJOY'	=> 'Me divierte',
	'REACTION_LIKE'		=> 'Me gusta',
	'REACTION_LOVE'		=> 'Me encanta',
	'REACTION_MATTER'	=> 'Me importa',
	'REACTION_SAD'		=> 'Me entristece',
	'REACTION_SURPRISE'	=> 'Me asombra',
]);
