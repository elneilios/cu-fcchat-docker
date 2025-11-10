<?php
/**
 * @package Ext Common Core
 * @version 1.1.7 12/07/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core\controller;

use canidev\core\Dom;
use \Symfony\Component\DependencyInjection\ContainerInterface;

class acp
{
	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;
	
	/** @var \phpbb\config\db */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\symfony_request */
	protected $symfony_request;

	/** @var \canidev\core\template */
	protected $template;

	protected $page_title;
	protected $tpl_name;

	protected $form_action 	= '';
	protected $new_config 	= [];

	/** @var \canidev\core\tools */
	public $tools;

	/** @var ContainerInterface */
	private $container;

	/**
	 * Set basic services for this class
	 *
	 * @param ContainerInterface  $container 	Service container
	 */
	public function set_basic_services(ContainerInterface $container)
	{
		$this->cache 			= $container->get('cache.driver');
		$this->config 			= $container->get('config');
		$this->container 		= $container;
		$this->language 		= $container->get('language');
		$this->request 			= $container->get('request');
		$this->symfony_request 	= $container->get('symfony_request');
		$this->template 		= $container->get('canidev.core.template');
		$this->tools 			= $container->get('canidev.core.tools');

		if(!function_exists('build_cfg_template'))
		{
			include($container->getParameter('core.root_path') . 'includes/functions_acp.' . $container->getParameter('core.php_ext'));
		}
	}

	/**
	 * Do basic actions on acp controller
	 * 
	 * @param string 	$ext_namespace 		CaniDev extension namespace
	 */
	public function pageHeader($ext_namespace)
	{
		$ext_info = $this->tools->getExtInfo($ext_namespace);

		if($ext_info !== false)
		{
			// Check is symfony_request is present to make sure old versions of extensions are compatible
			if($this->symfony_request !== null)
			{
				$this->template->assign_var('U_REPORT', $this->symfony_request->getUri());

				if($this->request->is_ajax() && $this->request->is_set_post('send_report'))
				{
					$report = new \canidev\core\Report(
						$this->container,
						str_replace('/store/', '', $ext_info->extra->{'version-check'}->directory),
						$ext_info->version
					);

					if($report->requestData())
					{
						$report->send(true);
					}
				}
			}

			$this->template->assign_var('IN_EXT_' . strtoupper($ext_info->basename), true);
		}

		$this->template->assign_vars([
			'LOAD_CORE_ACP'		=> true,
			'U_ACTION'			=> $this->form_action,
			'U_HELP'			=> ($ext_info !== false) ? 'https://www.canidev.com' . $ext_info->extra->{'version-check'}->directory . '?rq=documentation&v=' . $ext_info->version : '',
		]);
		
		// Delete the cache in the first call after the installation (prevent error to load extension template events without clear the cache manually)
		if($this->config['cbb_asset_check'] != $this->config['assets_version'])
		{
			$this->config->set('cbb_asset_check', $this->config['assets_version']);
			$this->cache->purge();
		}
	}

	/**
	 * Parse display_vars to template
	 * 
	 * @param array 		$display_vars 		Variables to show
	 * @param string 		$tpl_key 			Key for template loop
	 * @param array|null 	$cfg_array 			Variables to parse. Set null to use $this->new_config
	 * @param bool 			$individual_name 	Set to true to use key and not "config[key]" in names
	 */
	public function display_vars($display_vars, $tpl_key = 'options', $cfg_array = null, $individual_name = false)
	{
		if($cfg_array === null)
		{
			$cfg_array = $this->new_config;
		}

		foreach($display_vars as $config_key => $vars)
		{
			if(!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if(strpos($config_key, 'legend') !== false)
			{
				$this->template->assign_block_vars($tpl_key, [
					'S_LEGEND'		=> true,
					'LEGEND'		=> $this->language->lang($vars)
				]);
				
				continue;
			}

			if(isset($vars['builder']))
			{
				$this->template->assign_block_vars($tpl_key, [
					'INCLUDE'		=> call_user_func($vars['builder'], $vars, $cfg_array),
				]);

				continue;
			}
			
			if(!isset($cfg_array[$config_key]) && isset($vars['default']))
			{
				$cfg_array[$config_key] = $vars['default'];
			}

			if(isset($vars['function']) && is_array($vars['function']))
			{
				switch($vars['function'][0])
				{
					case 'controller':
						$vars['function'][0] = $this;
					break;

					default:
						if(is_string($vars['function'][0]) && property_exists($this, $vars['function'][0]))
						{
							$vars['function'][0] = $this->{$vars['function'][0]};
						}
					break;
				}
			}

			$type 		= explode(':', $vars['type']);
			$content 	= build_cfg_template($type, $config_key, $cfg_array, $config_key, $vars);
			
			if(empty($content))
			{
				continue;
			}

			// Add custom class
			if(!preg_match('#^<[a-z]+[^>]*class=[^>]*>#', $content))
			{
				$content = preg_replace('#^<(input|select|textarea)#', '<$1 class="cbb-inputbox"', $content);
			}

			// Add custom attributes
			if(isset($vars['attributes']))
			{
				$content = preg_replace_callback(
					'#<(input|select|textarea)([^>]*?)(/)?>#', function($match) use ($vars) {
						$attr = array_merge(Dom::attrToArray($match[2]), $vars['attributes']);
						
						return '<' . $match[1] . ' ' . Dom::arrayToAttr($attr) . ' ' . $match[3] . '>';
					},
					$content
				);
			}

			if($individual_name)
			{
				$content = preg_replace('#name="config\[([a-z0-9\-_]+)\]"#i', 'name="$1"', $content);
			}

			$this->template->assign_block_vars($tpl_key, [
				'ID'			=> str_replace('_', '-', $config_key),
				'KEY'			=> $config_key,
				'TITLE'			=> $this->language->lang($vars['lang']),
				'S_EXPLAIN'		=> ($this->language->is_set($vars['lang'] . '_EXPLAIN') ? $this->language->lang($vars['lang'] . '_EXPLAIN') : ''),
				'CONTENT'		=> $content,
				'DISPLAY'		=> (isset($vars['display']) && !$vars['display']) ? false : true,
			]);

			unset($display_vars[$config_key]);
		}
	}

	/**
	 * Obtain controller action
	 * @return string
	 */
	public function get_form_action()
	{
		return $this->form_action;
	}

	/**
	 * Obtain controller template
	 * @return string
	 */
	public function get_template()
	{
		return $this->tpl_name;
	}
	
	/**
	 * Obtain controller title
	 * 
	 * @param string 	$prefix 	Prefix to be added to title
	 * @return string
	 */
	public function get_title($prefix = '')
	{
		if($prefix)
		{
			return $this->language->lang($prefix) . ' &bull; ' . $this->language->lang($this->page_title);
		}

		return $this->page_title;
	}

	/**
	 * Set controller action
	 * 
	 * @param string 	$u_action 		Form action to be set
	 * @return static
	 */
	public function set_form_action($u_action)
	{
		$this->form_action = $u_action;
		return $this;
	}

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public function page_header()
	{
		$class = str_replace('\\', '/', get_called_class());
		$parts = explode('/', $class);

		return $this->pageHeader($parts[0] . '/' . $parts[1]);
	}
}
