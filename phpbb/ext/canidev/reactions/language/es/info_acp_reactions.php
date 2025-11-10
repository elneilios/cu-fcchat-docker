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
	'ACP_REACTIONS'			=> 'Reacciones',
	'ACP_REACTIONS_CONFIG'	=> 'Opciones',
	'ACP_REACTIONS_MANAGE'	=> 'Configurar Reacciones',
	
	'EXT_INSTALL_ERROR'		=> 'Esta extensión no es compatible con alguna extensión instalada.<br />Revise la documentación para más información.',
	'CORE_INSTALL_ERROR'	=> 'No ha subido todos los archivos del paquete de instalación o está intentando instalar un paquete antiguo.<br />
		Por favor, asegúrese de subir todos los archivos (incluída la carpeta <em>core</em>) y de utilizar un paquete descargado de la web oficial.',
]);
