<?php
/**
 * [English [En]]]
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
	'ACCEPT'					=> 'Accept',
	'ALL_GROUPS'				=> 'All groups',
	'APPLY'						=> 'Apply',
	'BATCH_ACTIONS'				=> 'Batch actions',
	'CLIPBOARD_LINK_ERROR'		=> 'The link could not be copied',
	'CLIPBOARD_LINK_SUCCESS'	=> 'Link copied to clipboard',
	'CLIPBOARD_TEXT_ERROR'		=> 'The text could not be copied',
	'CLIPBOARD_TEXT_SUCCESS'	=> 'Text copied to clipboard',
	'CONFIGURE'					=> 'Configure',
	'COPY_TO_CLIPBOARD'			=> 'Copy to clipboard',
	'DOCUMENTATION_AND_SUPPORT'	=> 'Documentation and Support',
	'EDIT'						=> 'Edit',
	'ENABLE_DISABLE'			=> 'Enable/Disable',
	'NO_ITEMS'					=> 'No items to show',
	'ONLY_DEFAULT_GROUPS'		=> 'Consider only default group',
	'REPORT_EXPLAIN'			=> 'List below as much information as you can about the error.<br /><br />
		In addition to what is described here, basic information about the execution environment will be sent for the correct review of the problem.<br />
		<a href="https://www.canidev.com/api/report/documentation" onclick="window.open(this.href); return false;">View details</a>',
	'REPORT_SENDED'				=> 'Report sent successfully',
	'REPORT_TITLE'				=> 'Report error',
	'SAVE'						=> 'Save',
	'SEND'						=> 'Send',

	'NO_LIMIT'			=> 'No limit',
	'ONE_DAY'			=> 'One day',
	'ONE_HOUR'			=> 'One hour',
	'ONE_WEEK'			=> 'One week',
	'ONE_MONTH'			=> 'One month',
	'ONE_YEAR'			=> 'One year',
	'TWO_YEARS'			=> 'Two years',

	// Errors
	'ERROR_REPORT_SEND'			=> 'Your report could not be sent',
]);
