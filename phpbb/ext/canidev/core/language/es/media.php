<?php
/**
 * [Spanish [Es]]
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
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
	'COPY_TO_LIST'			=> 'Copiar a lista actual',
	'CREATE_SERVER_COPY'	=> 'Crear copia en este servidor',
	'DELETE_FROM_LIST'		=> 'Quitar de la lista',
	'DELETE_ICON'			=> 'Quitar icono',
	'DELETE_IMAGE'			=> 'Quitar imagen',
	'DELETE_PERMANENTLY'	=> 'Eliminar permanentemente',
	'ICON_PREVIEW'			=> 'Vista previa del icono',
	'IMAGE_DESCRIPTION'		=> 'Descripción',
	'IMAGE_DETAILS'			=> 'Detalles de imagen',
	'IMAGE_DIMENSIONS'		=> '<%width%> × <%height%> píxeles',
	'IMAGE_SAVE_ERROR'		=> 'No se ha podido guardar la imagen',
	'IMAGE_TITLE'			=> 'Título',
	'IMAGE_URL'				=> 'Url',
	'INSERT_ON_ENTRY'		=> 'Insertar en entrada',
	'FILES_DELETED'			=> 'Archivos eliminados',
	'FILES_DRAG'			=> 'Arrastre y suelte los archivos para subirlos',
	'FILES_DROP'			=> 'Suelte los archivos para subirlos',
	'FILES_REMOVE_CONFIRM'	=> 'Está a punto de eliminar permanentemente los archivos seleccionados<br />¿Desea continuar?',
	'FORMAT_INVALID'		=> 'Formato no válido',
	'MAX_IMAGESIZE'			=> 'Las imágenes se redimensionarán a %d x %d píxeles.',
	'MAX_FILESIZE'			=> 'Tamaño máximo de archivo: %s.',
	'MEDIA_UPDATED'			=> 'Cambios guardados',
	'OR'					=> 'o',
	'PROCCESS'				=> 'Procesar',
	'SELECT_ICON'			=> 'Seleccionar icono nuevo',
	'SELECT_IMAGE'			=> 'Seleccionar imagen nueva',
	'SELECT_FILE'			=> 'Seleccionar archivo',
	'SELECT_FILES'			=> 'Seleccionar archivos',
	'SELECTED_COUNT'		=> '<span class="value">0</span> seleccionados',
	'SET_ICON'				=> 'Establecer icono',
	'SET_IMAGE'				=> 'Establecer imagen',
	'UPDATE'				=> 'Actualizar',
	'UPLOADING_FILES'		=> 'Subiendo archivos...',
	
	'CURRENT_LIST'		=> 'Lista actual',
	'GALLERY'			=> 'Galería',
	'ICONS'				=> 'Iconos',
	'INSERT_URL'		=> 'Insertar desde URL',
	'UPLOAD_IMAGE'		=> 'Subir Imagen',

	'ICON_BRAND'			=> 'Marcas',
	'ICON_DIRECTIONAL'		=> 'Direcional',
	'ICON_FILE_TYPE'		=> 'Tipos de archivo',
	'ICON_GENDER'			=> 'Género',
	'ICON_HAND'				=> 'Mano',
	'ICON_MEDICAL'			=> 'Médico',
	'ICON_PAYMENT'			=> 'Pago y moneda',
	'ICON_TEXT_EDITOR'		=> 'Editor de texto',
	'ICON_TRANSPORTATION'	=> 'Transporte',
	'ICON_VIDEO_PLAYER'		=> 'Reproductor de video',
	'ICON_WEB_APPLICATION'	=> 'Aplicación Web',
]);
