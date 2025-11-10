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

class lib
{
	/** @deprecated use constants::VERSION instead */
	const VERSION	= constants::VERSION;
	
	/** @var \phpbb\config\db */
	private $config;
	
	/** @var \phpbb\db\driver\driver_interface */
	private $db;

	/** @var \phpbb\event\dispatcher_interface */
	private $dispatcher;

	/** @var \phpbb\language\language */
	private $language;

	/** @var \phpbb\path_helper */
	private $path_helper;

	/** @var \phpbb\request\request_interface */
	private $request;

	/** @var \canidev\core\template */
	private $template;

	/** @var \phpbb\user */
	private $user;

	/** @var string */
	private $phpbb_root_path;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface 	$container		Service container interface
	 * @access public
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->config			= $container->get('config');
		$this->db				= $container->get('dbal.conn');
		$this->dispatcher 		= $container->get('dispatcher');
		$this->language			= $container->get('language');
		$this->path_helper 		= $container->get('path_helper');
		$this->request 			= $container->get('request');
		$this->template			= $container->get('canidev.core.template');
		$this->user				= $container->get('user');
		$this->phpbb_root_path	= $container->getParameter('core.root_path');
	}
	
	/** 
	 * List all the groups for the current user
	 * @return array 	Array with user group IDs
	 */
	public function get_user_groups()
	{
		if(empty($this->user->data['group_ary']))
		{
			$this->user->data['group_ary'] = [];

			$sql = 'SELECT group_id
				FROM ' . USER_GROUP_TABLE . '
				WHERE user_id = ' . (int)$this->user->data['user_id'] . '
				AND user_pending = 0';
			$result = $this->db->sql_query($sql);
			while($row = $this->db->sql_fetchrow($result))
			{
				$this->user->data['group_ary'][] = (int)$row['group_id'];
			}
			$this->db->sql_freeresult($result);
		}
		
		return $this->user->data['group_ary'];
	}
	
	/**
	 * Check if user has permissions, based on groups
	 * 
	 * @param array|string 			$valid_groups 		Valid Group's IDs
	 * @param array|string|false 	$check_groups 		Group's IDs to check
	 * @param int|null 				$default_group_id 	Default user group (user with $check_groups is defined)
	 * 
	 * @return bool
	 */
	public function group_auth($valid_groups, $check_groups = false, $default_group_id = null)
	{
		if(is_string($check_groups))
		{
			$check_groups = explode(',', $check_groups);
		}
		
		if(is_string($valid_groups))
		{
			if(strpos($valid_groups, 'default;') === 0)
			{
				$check_groups = ($check_groups === false) ? [$this->user->data['group_id']] : (($default_group_id) ? [$default_group_id] : $check_groups[0]);
				$valid_groups = str_replace('default;', '', $valid_groups);
			}
		}

		$check_groups 	= ($check_groups === false) ? $this->get_user_groups() : $check_groups;
		$group_ary 		= (is_array($valid_groups) ? $valid_groups : explode(',', $valid_groups));

		return ($valid_groups == 'all' || count(array_intersect($group_ary, $check_groups)));
	}
	
	/**
	 * Get data from core file
	 * 
	 * @param string 	$file
	 * @param bool 		$process 	Define if language variables will be parsed
	 * 
	 * @return string
	 */
	public function get_data($file, $process = false)
	{
		$filename = $this->phpbb_root_path . 'ext/canidev/core/data/' . $file;
		$output = @file_get_contents($filename);
		
		if($process)
		{
			$output = preg_replace_callback('#\{L_([A-Z0-9_\-]+)\}#', function($matches) {
				return $this->language->is_set($matches[1]) ? $this->language->lang($matches[1]) : '';
			}, $output);
		}

		return $output;
	}

	/**
	 * @deprecated use template->set_js_lang instead
	 */
	public function set_js_lang($ary)
	{
		$this->template->set_js_lang($ary);
	}

	/**
	 * Show modal dialog (only for ajax request)
	 * 
	 * @param bool 			$check 				Set where dialog submit must be checked
	 * @param string 		$title				Dialog title
	 * @param array 		$hidden 			Custom hidden fields
	 * @param string 		$html_body 			Template filename to be loaded
	 * @param array|null 	$json_data 			Additional json data sended to response
	 * @param string 		$u_action 			Action for dialog form
	 * 
	 * @return bool|void
	*/
	public function modal_dialog($check, $title = '', $hidden = [], $html_body = '@canidev_core/confirmbox.html', $json_data = null, $u_action = '')
	{
		if(!$this->request->is_ajax())
		{
			return false;
		}

		$confirm = $this->request->variable('confirm', false, false, \phpbb\request\request_interface::POST);

		if($check)
		{
			$user_id 		= $this->request->variable('confirm_uid', 0);
			$session_id 	= $this->request->variable('sess', '');

			if(!$confirm || $user_id != $this->user->data['user_id'] || $session_id != $this->user->session_id)
			{
				return false;
			}

			return true;
		}

		$hidden = array_merge($hidden, [
			'confirm_uid'	=> $this->user->data['user_id'],
			'sess'			=> $this->user->session_id,
			'sid'			=> $this->user->session_id,
		]);

		$formatted_title = (!$this->language->is_set($title)) ? $this->language->lang('CONFIRM') : $this->language->lang($title);

		if(defined('IN_ADMIN') && isset($this->user->data['session_admin']) && $this->user->data['session_admin'])
		{
			adm_page_header($formatted_title);
		}
		else
		{
			page_header($formatted_title);
		}

		// re-add sid / transform & to &amp; for user->page (user->page is always using &)
		$use_page = ($u_action) ? $u_action : str_replace('&', '&amp;', $this->user->page['page']);
		$u_action = reapply_sid($this->path_helper->get_valid_page($use_page, $this->config['enable_mod_rewrite']));

		$this->template->assign_vars([
			'MESSAGE_TITLE'		=> $formatted_title,
			'MESSAGE_TEXT'		=> (!$this->language->is_set($title . '_CONFIRM')) ? $title : $this->language->lang($title . '_CONFIRM'),

			'YES_VALUE'			=> $this->language->lang('YES'),
			'S_CONFIRM_ACTION'	=> $u_action,
			'S_HIDDEN_FIELDS'	=> build_hidden_fields($hidden),
			'S_AJAX_REQUEST'	=> true,
		]);

		/**
		 * @event canidev.core.dialog
		 * 
		 * @var string 		title		Dialog title
		 * @var array 		hidden 		Additional hidden fields
		 * @var string 		html_body 	Template filename
		 * @var array 		json_data 	JSON Data
		 * @since 1.1.0
		 */
		$vars = ['title', 'hidden', 'html_body', 'json_data'];
		extract($this->dispatcher->trigger_event('canidev.core.modal_dialog', compact($vars)));

		$this->template->set_filenames([
			'body' => $html_body
		]);

		$response = \canidev\core\JsonResponse::getInstance();

		if($json_data !== null)
		{
			$response->add($json_data);
		}

		$response->send([
			'isModalDialog'		=> true,
			
			'MESSAGE_BODY'		=> $this->template->assign_display('body'),
			'MESSAGE_TITLE'		=> $formatted_title,
			'MESSAGE_TEXT'		=> (!$this->language->is_set($title . '_CONFIRM')) ? $title : $this->language->lang($title . '_CONFIRM'),

			'YES_VALUE'			=> $this->language->lang('YES'),
			'S_CONFIRM_ACTION'	=> str_replace('&amp;', '&', $u_action),
			'S_HIDDEN_FIELDS'	=> build_hidden_fields($hidden),
		]);
	}

	/**
	 * Perform a request to api
	 * 
	 * @param string 		$namespace
	 * @param string 		$action
	 * @param array 		$data
	 * 
	 * @return array|null
	 */
	public function apiRequest($namespace, $action, $data = [])
	{
		$api 	= $this->apiData($namespace, $action, 'array');
		$client = new \GuzzleHttp\Client();
		$key 	= phpbb_version_compare(PHPBB_VERSION, '3.3.0', '<') ? 'body' : 'form_params';

		if($api !== null)
		{
			try
			{
				$host = $this->request->header('HOST');
				$host = ($host) ? $host : $this->config['server_name']; // For CLI

				$response = $client->post($api['url'], [
					'exceptions' 	=> false,
					'timeout' 		=> 10,
					$key			=> $data,
					'headers' => [
						'User-Agent' 	=> $api['agent'],
						'X-Api-Token'	=> $api['token'],
						'X-Api-Host'	=> $host,
					]
				]);

				$content_type = $response->getHeader('Content-Type');
				$content_type = is_array($content_type) ? $content_type[0] : $content_type;

				if($content_type == 'application/json')
				{
					return array_merge(
						[
							'code'	=> $response->getStatusCode(),
						],
						json_decode($response->getBody()->getContents(), true)
					);
				}
			}
			catch(\Throwable $th) {}
		}

		return null;
	}

	public function apiData($namespace, $action, $return_key = null, $data = null)
	{
		$config_file 	= __DIR__ . '/config/api.php';
		$api_url 		= sprintf('https://api.canidev.com/%s/%s', $namespace, $action);
		$data 			= ($data === null) ? [] : $data;

		if(file_exists($config_file))
		{
			require($config_file);

			switch($return_key)
			{
				case 'array':
					return [
						'agent'		=> 'canidev-request/1.0',
						'token'		=> $api_token,
						'url'		=> $api_url,
					];
				
				case 'url':
					$data['token'] = $api_token;
					return $api_url . '?' . http_build_query($data, '', '&');
			}
		}

		return null;
	}

	/**
	 * 
	 * @param ContainerInterface|null 	$container		Service container interface
	 * @return static
	 * @deprecated Use service instead
	 */
	static public function get_instance($container = null)
	{
		if($container === null)
		{
			global $phpbb_container;

			$container = $phpbb_container;
		}

		$request = $container->get('request');

		if(!$container->has('canidev.core.lib') && !$request->variable('refresh', false))
		{
			$protocol 	= $request->is_secure() ? 'https://' : 'http://';
			$uri 		= $request->server('REQUEST_URI');

			$container->get('cache.driver')->purge();

			$uri .= ((strpos($uri, '?') === false) ? '?' : '&');
			$uri .= 'refresh=1';

			redirect($protocol . $request->server('HTTP_HOST') . $uri);
		}
		
		return $container->get('canidev.core.lib');
	}
}
