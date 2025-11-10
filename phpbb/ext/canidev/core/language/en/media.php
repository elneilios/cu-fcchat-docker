<?php
/**
 * [English [En]]
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

$lang = array_merge($lang, array(
	'COPY_TO_LIST'			=> 'Copy to current list',
	'CREATE_SERVER_COPY'	=> 'Create copy on this server',
	'DELETE_FROM_LIST'		=> 'Remove from list',
	'DELETE_ICON'			=> 'Delete icon',
	'DELETE_IMAGE'			=> 'Delete image',
	'DELETE_PERMANENTLY'	=> 'Delete permanently',
	'ICON_PREVIEW'			=> 'Icon preview',
	'IMAGE_DESCRIPTION'		=> 'Description',
	'IMAGE_DETAILS'			=> 'Image details',
	'IMAGE_DIMENSIONS'		=> '<%width%> × <%height%> pixels',
	'IMAGE_SAVE_ERROR'		=> 'Could not save image',
	'IMAGE_TITLE'			=> 'Title',
	'IMAGE_URL'				=> 'Url',
	'INSERT_ON_ENTRY'		=> 'Insert on text',
	'FILES_DELETED'			=> 'Files deleted',
	'FILES_DRAG'			=> 'Drag and drop files to upload',
	'FILES_DROP'			=> 'Drop files to upload',
	'FILES_REMOVE_CONFIRM'	=> 'You are about to permanently delete the selected files<br />Do you want to continue?',
	'FORMAT_INVALID'		=> 'Invalid format',
	'MAX_IMAGESIZE'			=> 'Images will be resized to %d x %d pixels.',
	'MAX_FILESIZE'			=> 'Maximum file size: %s.',
	'MEDIA_UPDATED'			=> 'Changes saved',
	'OR'					=> 'or',
	'PROCCESS'				=> 'Process',
	'SELECT_ICON'			=> 'Select new icon',
	'SELECT_IMAGE'			=> 'Select new image',
	'SELECT_FILE'			=> 'Select file',
	'SELECT_FILES'			=> 'Select files',
	'SELECTED_COUNT'		=> '<span class="value">0</span> selected',
	'SET_ICON'				=> 'Set icon',
	'SET_IMAGE'				=> 'Set image',
	'UPDATE'				=> 'Update',
	'UPLOADING_FILES'		=> 'Uploading files...',
	
	'CURRENT_LIST'		=> 'Current list',
	'GALLERY'			=> 'Gallery',
	'ICONS'				=> 'Icons',
	'INSERT_URL'		=> 'Insert from URL',
	'UPLOAD_IMAGE'		=> 'Upload Image',
	
	'ICON_BRAND'			=> 'Brand',
	'ICON_DIRECTIONAL'		=> 'Directional',
	'ICON_FILE_TYPE'		=> 'File Type',
	'ICON_GENDER'			=> 'Gender',
	'ICON_HAND'				=> 'Hand',
	'ICON_MEDICAL'			=> 'Medical',
	'ICON_PAYMENT'			=> 'Payment and Currency',
	'ICON_TEXT_EDITOR'		=> 'Text Editor',
	'ICON_TRANSPORTATION'	=> 'Transportation',
	'ICON_VIDEO_PLAYER'		=> 'Video Player',
	'ICON_WEB_APPLICATION'	=> 'Web Application',
));
