<?php
/**
 * @package Ext Common Core
 * @version 1.1.4 26/01/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

use Symfony\Component\DependencyInjection\ContainerInterface;

class Report
{
	/** @var \phpbb\config\db */
	protected $config;

	/** @var \canidev\core\lib */
	protected $core;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\extension\manager */
	protected $extensions_manager;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	protected $data 		= [];
	protected $report_url 	= 'https://www.canidev.com/api/report';

	/**
	 * Constructor
	 * 
	 * @param ContainerInterface 	$container			Service Container
	 * @param string 				$extension_id 		CaniDev Extension ID
	 * @param string 				$version			CaniDev Extension Version
	 * 
	 */
	public function __construct(ContainerInterface $container, $extension_id, $version)
	{
		$this->config 				= $container->get('config');
		$this->core 				= $container->get('canidev.core.lib');
		$this->db 					= $container->get('dbal.conn');
		$this->extensions_manager 	= $container->get('ext.manager');
		$this->language 			= $container->get('language');
		$this->request 				= $container->get('request');
		$this->user 				= $container->get('user');

		$this->language->add_lang('main', 'canidev/core');

		$this->set([
			'extension_id'	=> $extension_id,
			'version'		=> $version,
		]);
	}

	/**
	 * Show dialog to request additional data
	 * 
	 * @param string 	$title 			Dialog Title
	 * @param string 	$tpl_name		Template file for dialog
	 * 
	 * @return bool
	 */
	public function requestData($title = 'REPORT_TITLE', $tpl_name = '@canidev_core/report_body.html')
	{
		if($this->core->modal_dialog(true))
		{
			if($tpl_name == '@canidev_core/report_body.html')
			{
				$this->set('description', $this->request->variable('description', '', true));
			}

			return true;
		}

		$this->core->modal_dialog(false, $title, [], $tpl_name);
		
		return false;
	}

	/**
	 * Send report
	 * 
	 * @param bool 		$collect_data 			Define if server data will be collected and send
	 */
	public function send($collect_data = false)
	{
		$response = new \canidev\core\JsonResponse;

		if(empty($this->data['extension_id']) || empty($this->data['version']))
		{
			$response->error($this->language->lang('FORM_INVALID'));
		}

		if($collect_data)
		{
			$this->data += $this->collectData();
		}

		// Send report to CaniDev.com
		$rsp = $this->core->apiRequest('phpbb', 'report', [
			'data'	=> $this->data
		]);

		if($rsp !== null)
		{
			$response->send([
				'message'	=> $this->language->lang('REPORT_SENDED'),
			], true);
		}

		$response->error($this->language->lang('ERROR_REPORT_SEND'));
	}

	/**
	 * Set value to send in report
	 * 
	 * @param string|array 		$key
	 * @param mixed 			$value
	 * 
	 * @return static
	 */
	public function set($key, $value = null)
	{
		if(is_array($key))
		{
			foreach ($key as $k => $v)
			{
				$this->set($k, $v);
			}
		}
		else
		{
			$this->data[$key] = $value;
		}

		return $this;
	}

	/**
	 * Collect server data
	 * 
	 * @return array
	 * @access private
	 */
	protected function collectData()
	{
		$user_agent = $this->request->header('User-Agent');
		$agents 	= ['firefox', 'msie', 'opera', 'chrome', 'safari', 'mozilla', 'seamonkey', 'konqueror', 'netscape', 'gecko', 'navigator', 'mosaic', 'lynx', 'amaya', 'omniweb', 'avant', 'camino', 'flock', 'aol'];

		// We check here 1 by 1 because some strings occur after others (for example Mozilla [...] Firefox/)
		foreach($agents as $agent)
		{
			if(preg_match('#(' . $agent . ')[/ ]?([0-9.]*)#i', $user_agent, $match))
			{
				$user_agent = $match[1] . ' ' . $match[2];
				break;
			}
		}
		
		return [
			'php'	=> [
				'version'						=> PHP_VERSION,
				'sapi'							=> PHP_SAPI,
				'int_size'						=> defined('PHP_INT_SIZE') ? PHP_INT_SIZE : '',
				'safe_mode'						=> (int) @ini_get('safe_mode'),
				'open_basedir'					=> (int) @ini_get('open_basedir'),
				'memory_limit'					=> @ini_get('memory_limit'),
				'allow_url_fopen'				=> (int) @ini_get('allow_url_fopen'),
				'allow_url_include'				=> (int) @ini_get('allow_url_include'),
				'file_uploads'					=> (int) @ini_get('file_uploads'),
				'upload_max_filesize'			=> @ini_get('upload_max_filesize'),
				'post_max_size'					=> @ini_get('post_max_size'),
				'disable_functions'				=> @ini_get('disable_functions'),
				'disable_classes'				=> @ini_get('disable_classes'),
				'extensions'					=> get_loaded_extensions(),
			],

			'site'		=> [
				'version'			=> PHPBB_VERSION,
				'domain'			=> $this->request->server('HTTP_HOST'),
				'contact'			=> $this->config['board_contact'],
				'style'				=> $this->user->style['style_name'],
				'language_default'	=> $this->config['default_lang'],
				'language_user'		=> $this->user->lang_name,
				'extensions'		=> array_keys($this->extensions_manager->all_enabled()),
			],

			'server'	=> [
				'os'			=> PHP_OS,
				'httpd'			=> htmlspecialchars_decode($this->request->server('SERVER_SOFTWARE')),
				'dbms_version'	=> $this->db->sql_server_info(true),
				'user_agent'	=> $user_agent,
			]
		];
	}
}
