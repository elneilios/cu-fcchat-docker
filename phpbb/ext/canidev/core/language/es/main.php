<?php
/**
 * [Spanish [Es]]
 * @package Ext Common Core
 * @version 1.1.6 17/06/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

// @ignore
if(!defined('IN_PHPBB'))
{
	exit;
}

if(empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACCEPT'					=> 'Aceptar',
	'ALL_GROUPS'				=> 'Todos los grupos',
	'APPLY'						=> 'Aplicar',
	'BATCH_ACTIONS'				=> 'Acciones en lote',
	'CLIPBOARD_LINK_ERROR'		=> 'No se ha podido copiar el enlace',
	'CLIPBOARD_LINK_SUCCESS'	=> 'Enlace copiado al portapapeles',
	'CLIPBOARD_TEXT_ERROR'		=> 'No se ha podido copiar el texto',
	'CLIPBOARD_TEXT_SUCCESS'	=> 'Texto copiado al portapapeles',
	'CONFIGURE'					=> 'Configurar',
	'COPY_TO_CLIPBOARD'			=> 'Copiar al portapapeles',
	'DOCUMENTATION_AND_SUPPORT'	=> 'Documentación y Soporte',
	'EDIT'						=> 'Editar',
	'ENABLE_DISABLE'			=> 'Activar/Desactivar',
	'NO_ITEMS'					=> 'No hay elementos para mostrar',
	'ONLY_DEFAULT_GROUPS'		=> 'Considerar solo grupo por defecto',
	'REPORT_EXPLAIN'			=> 'Detalle a continuación toda la información que pueda sobre el error.<br /><br />
		Además de lo descrito aquí, se enviará información básica sobre el entorno de ejecución para la correcta revisión del problema.<br />
		<a href="https://www.canidev.com/api/report/documentation" onclick="window.open(this.href); return false;">Ver detalles</a>',
	'REPORT_SENDED'				=> 'Reporte enviado con éxito',
	'REPORT_TITLE'				=> 'Reportar error',
	'SAVE'						=> 'Guardar',
	'SEND'						=> 'Enviar',

	'NO_LIMIT'			=> 'Sin límite',
	'ONE_DAY'			=> 'Un día',
	'ONE_HOUR'			=> 'Una hora',
	'ONE_WEEK'			=> 'Una semana',
	'ONE_MONTH'			=> 'Un mes',
	'ONE_YEAR'			=> 'Un año',
	'TWO_YEARS'			=> 'Dos años',

	// Errors
	'ERROR_REPORT_SEND'			=> 'No ha sido posible enviar su reporte',
]);
