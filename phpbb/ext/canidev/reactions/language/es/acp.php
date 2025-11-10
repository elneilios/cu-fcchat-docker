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
	'ACP_REACTIONS_CONFIG_EXPLAIN'	=> 'Desde aquí puede configurar distintas opciones referentes a las reacciones.',
	'ACP_REACTIONS_MANAGE_EXPLAIN'	=> 'Desde aquí puede administrar las reacciones que estarán disponibles en el foro.',
	'ABOVE'							=> 'Arriba',
	'BELOW'							=> 'Abajo',
	'ENABLE_DISABLE'				=> 'Activar/Desactivar',
	'ERROR_REACTION_NO_EXISTS'		=> 'La reacción seleccionada no existe',
	'ERROR_SCORE_INVALID'			=> 'La puntuación definida no es válida',
	'NO_REACTIONS' 					=> 'No se ha añadido ninguna reacción.',
	'REACTION_ADD'					=> 'Añadir reacción',
	'REACTION_COLOR'				=> 'Color',
	'REACTION_COLOR_EXPLAIN'		=> 'Color asociado con esta reacción.',
	'REACTION_ENABLED'				=> 'Activado',
	'REACTION_ENABLED_EXPLAIN'		=> 'Define si la reacción estará disponible para su uso.',
	'REACTION_IMAGE'				=> 'Imagen',
	'REACTION_IMAGE_EXPLAIN'		=> 'Imagen/Emoticono relacionado con esta reacción.',
	'REACTION_SCORE'				=> 'Puntuación',
	'REACTION_SCORE_EXPLAIN'		=> 'Define el impacto (en forma de puntos) que tendrá esta reacción sobre el mensaje.',
	'REACTION_TITLE'				=> 'Título',
	'REACTION_TITLE_EXPLAIN'		=> 'Nombre o título de la reacción',
	
	'REACTIONS_ALLOW_CHANGE'			=> 'Permitir cambiar reacción',
	'REACTIONS_ALLOW_CHANGE_EXPLAIN'	=> 'Define si los usuarios podrán modificar su reacción en los mensajes.',
	'REACTIONS_ALLOW_MYSELF'			=> 'Permitir reacciones de uno mismo',
	'REACTIONS_ALLOW_MYSELF_EXPLAIN'	=> 'Si se habilita, los usuarios podrán reaccionar a sus propios mensajes.',
	'REACTIONS_ANONYMOUS'				=> 'Reacciones anónimas',
	'REACTIONS_ANONYMOUS_EXPLAIN'		=> 'Habilite esta opción si desea que los nombres de los usuarios no aparezcan en los listados de reacciones.<br />
		<em>Esta opción no afecta a los usuarios con permiso para moderar las reacciones.</em>',
	'REACTIONS_BUTTON_POSITION'			=> 'Posición del botón',
	'REACTIONS_BUTTON_POSITION_EXPLAIN'	=> 'Define en que lugar del mensaje aparecerá el botón para reaccionar.',
	'REACTIONS_FORCE_ATTACH'			=> 'Forzar reacción para ver adjuntos',
	'REACTIONS_FORCE_ATTACH_EXPLAIN'	=> 'Si se habilita, los usuarios tendrán que reaccionar a un mensaje para poder ver sus archivos adjuntos.',
	'REACTIONS_FORCE_REPLY'				=> 'Forzar reacción para responder',
	'REACTIONS_FORCE_REPLY_EXPLAIN'		=> 'Si se habilita, los usuarios tendrán que reaccionar al primer mensaje de un tema para poder publicar respuestas en el mismo.',
	'REACTIONS_FORUMS'					=> 'Foros en los que se puede reaccionar',
	'REACTIONS_FORUMS_EXPLAIN'			=> 'Define los foros en los que se mostrarán las reacciones.<br />
		Si no selecciona ninguno, las reacciones se mostrarán en todos los foros.<br />
		Puede seleccionar tantos como quiera usando la tecla <em>Ctrl</em> de su teclado.',
	'REACTIONS_LIST_ORDER'				=> 'Orden de la lista',
	'REACTIONS_LIST_ORDER_EXPLAIN'		=> 'Define el criterio que se usará para ordenar los usuarios en la lista de reacciones.',
	'REACTIONS_ORDER_TIME'				=> 'Fecha de reacción',
	'REACTIONS_ORDER_USERNAME'			=> 'Nombre del usuario',
	'REACTIONS_SCORE_ON_PROFILE'		=> 'Mostrar puntuación en perfil',
	'REACTIONS_SCORE_ON_PROFILE_EXPLAIN'	=> 'Si se habilita, se mostrará la puntuación en reacciones obtenidas por cada usuario en su perfil.',
	'REACTIONS_ZONE_ALL'				=> 'Todos los mensajes',
	'REACTIONS_ZONE_ONLY_FIRST_POST'	=> 'Solo primer mensaje',
	'REACTIONS_ZONE_ONLY_REPLIES'		=> 'Solo respuestas',
	'REACTIONS_ZONES'					=> 'Mensajes en los que se puede reaccionar',
	'REACTIONS_ZONES_EXPLAIN'			=> 'Define en que mensajes aparecerá la opción para reaccionar.<br /><br />
		<em>La opción escogida aquí puede cambiar/limitar el comportamiento de alguna otra opción de la extensión.</em>',

	'SCORE_CUSTOM'			=> 'Personalizado',
	'SCORE_CUSTOM_VALUE'	=> 'Personalizado (%s)',
	'SELECT_COLOUR'			=> 'Seleccionar color',
	'SET_AS_DEFAULT'		=> 'Definir como predeterminada',

	'scores' 	=> [
		1 		=> 'Positivo (+1)',
		0 		=> 'Neutral',
		-1 		=> 'Negativo (-1)',
	],
]);
