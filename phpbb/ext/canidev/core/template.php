<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

use \Symfony\Component\DependencyInjection\ContainerInterface;

class template
{
	protected $config;
	protected $filesystem;
	protected $language;
	protected $path_helper;
	protected $phpbb_template;
	protected $request;
	protected $twig;

	protected $assets 			= [];
	protected $assets_cache		= [];
	protected $context_ary		= [];
	protected $js_lang_keys 	= [];
	protected $in_ajax_request 	= false;

	public $prepend_assets = false;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config 						$config				Config Object
	 * @param \phpbb\filesystem\filesystem 				$filesystem 		The filesystem object
	 * @param \phpbb\language\language 					$language 			Language Object
	 * @param \phpbb\path_helper						$path_helper		Path Helper Object
	 * @param \phpbb\symfony_request					$request			Symfony Request object
	 * @param \phpbb\template\template					$template     		Template object
	 * @param \phpbb\template\twig\environment 			$twig_environment
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\filesystem\filesystem $filesystem,
		\phpbb\language\language $language,
		\phpbb\path_helper $path_helper,
		$request,
		\phpbb\template\template $template,
		\phpbb\template\twig\environment $twig_environment
	)
	{
		$this->config			= $config;
		$this->filesystem 		= $filesystem;
		$this->language 		= $language;
		$this->path_helper		= $path_helper;
		$this->phpbb_template	= $template;
		$this->request 			= $request;
		$this->twig				= $twig_environment;

		if((($this->request instanceof \phpbb\symfony_request) && $this->request->isXmlHttpRequest()) ||
			(($this->request instanceof \phpbb\request\request_interface) && $this->request->is_ajax()))
		{
			$this->in_ajax_request = true;
		}
	}
	
	/**
	 * Workaround for default template methods
	 * 
	 * @param string 	$name 		Method name
	 * @param array 	$args 		Method arguments
	 */
	public function __call($name, $args)
	{
		if(method_exists($this->phpbb_template, $name))
		{
			return call_user_func_array([$this->phpbb_template, $name], $args);
		}
		
		return false;
	}
	
	/**
	 * Add custom namespace for templates
	 * 
	 * @param string 	$namespace 			Namespace tag
	 * @param string 	$path 				Templates path
	 * @param bool 		$add_style_paths 	Set if "template" and "theme" folders must be included
	 */
	public function add_custom_namespace($namespace, $path, $add_style_paths = true)
	{
		if(!file_exists($path))
		{
			return;
		}

		$this->twig->getLoader()->addSafeDirectory($path);

		if($add_style_paths)
		{
			$style_ary	= array_merge($this->phpbb_template->get_user_style(), ['all']);
			$path_ary	= [];

			foreach($style_ary as $style_path)
			{
				$style_template_path 	= $path . 'styles/' . $style_path . '/template/';
				$style_theme_path		= $path . 'styles/' . $style_path . '/theme/';
				
				if(file_exists($style_template_path))
				{
					$path_ary[] = $style_template_path;
				}
				
				if(file_exists($style_theme_path))
				{
					$path_ary[] = $style_theme_path;
				}
			}

			$this->twig->getLoader()->setPaths($path_ary, $namespace);
		}
	}

	/**
	 * Append asset
	 * 
	 * @param string 		$type 				(js | css | javascript | script | style)
	 * @param array|string 	$data 				Asset data
	 * @param bool 			$return_filename 	Set if asset filename must be returned
	 * @param string 		$space 				(common | admin | frontend)
	 * 
	 * @return string|bool
	 */
	public function append_asset($type, $data, $return_filename = false, $space = 'common')
	{
		// Recursive
		if(is_array($data) && isset($data[0]))
		{
			foreach($data as $item)
			{
				$this->append_asset($type, $item, $return_filename, $space);
			}

			return true;
		}

		// Don't load admin assets in normal styles and vice versa
		if(($space == 'admin' && !defined('IN_ADMIN')) || ($space == 'frontend' && defined('IN_ADMIN')))
		{
			return false;
		}

		$namespace 	= 'all';
		$is_single	= false;
		
		if(strpos($type, '.') !== false)
		{
			list($namespace, $type) = explode('.', $type, 2);
		}
		
		if(!is_array($data))
		{
			$data = ['content' => $data];
		}
		
		$data = array_merge(
			[
				'id'		=> '',
				'type'		=> '',
			],
			$data
		);

		switch($type)
		{
			case 'css':
				$data['rel'] = 'stylesheet';
				
			// no break

			case 'js':
				$asset_url = $data['content'];

				if(!isset($this->assets_cache[$asset_url]))
				{
					if($this->request instanceof \phpbb\symfony_request)
					{
						$asset = new asset($asset_url, $this->path_helper, $this->filesystem, $this->phpbb_template, $this->request);

						if(!$asset->allocate())
						{
							return false;
						}

						$asset->add_assets_version($this->config['assets_version']);

						$data['content'] = $this->assets_cache[$asset_url] = $asset->get_url();
					}
					else
					{
						/** @deprecated To be removed in v1.2.0 */
						$asset = new \phpbb\template\asset($asset_url, $this->path_helper, $this->filesystem);
					
						if(substr($asset_url, 0, 2) == './')
						{
							$root_path = $this->path_helper->get_phpbb_root_path();
	
							$data['content'] = $this->assets_cache[$asset_url] = $root_path . substr($asset->get_url(), 2);
						}
						else if($asset->is_relative())
						{
							$asset_path = $asset->get_path();
							$local_file = $this->path_helper->get_phpbb_root_path() . $asset_path;
	
							if(!file_exists($local_file))
							{
								if(!($local_file = $this->find_asset($asset_path, ($space == 'admin'))))
								{
									return false;
								}
								
								$asset->set_path($local_file, true);
								$asset->add_assets_version($this->config['assets_version']);
	
								$data['content'] = $this->assets_cache[$asset_url] = $asset->get_url();
							}
						}
					}
					
					unset($asset);
				}
				else
				{
					$data['content'] = $this->assets_cache[$asset_url];
				}
				
				// If we only need the asset url, return here
				if($return_filename)
				{
					return $data['content'];
				}
				
				$is_single	= true;
				$file_key	= ($type == 'js') ? 'src' : 'href';

				$data[$file_key] 	= $data['content'];
				$data['content']	= '';

			// no break
			
			case 'javascript':
				if($type == 'javascript' || $type == 'js')
				{
					$data['type']	= 'text/javascript';
					$type			= 'script';
				}
				
			// no break

			case 'style':
			case 'script':
				if(!isset($this->assets[$namespace][$type]))
				{
					if(!isset($this->assets[$namespace]))
					{
						$this->assets[$namespace] = [];
					}

					$this->assets[$namespace][$type] = [];
				}
				else if($data['id'] || $is_single)
				{
					// Check, if the asset is already loaded, don't load it again
					foreach($this->assets[$namespace][$type] as $row)
					{
						if(($data['id'] && $row['ID'] == $data['id']) ||
							($is_single && isset($row['SRC']) && $row['SRC'] == $data[$file_key]))
						{
							return false;
						}
					}
				}
				
				$params = [];
				
				foreach($data as $key => $value)
				{
					if($key != 'content' && $value && $value != 'text/javascript')
					{
						$params[] = $key . '="' . $value . '"';
					}
				}
				
				if($data['content'])
				{
					if($data['type'] == 'text/javascript')
					{
						$data['content'] = "// <![CDATA[\n" . $data['content'] . "\n// ]]>";
					}
				
					$data['content'] = "\n" . trim($data['content']) . "\n";
				}
				else if(!$is_single)
				{
					break;
				}
				
				$new_asset = [
					'ID'		=> $data['id'],
					'DATA'		=> $data['content'] ,
					'PARAMS'	=> sizeof($params) ? ' ' . implode(' ', $params) : '',
					'IS_SINGLE'	=> $is_single,
					'TYPE'		=> $data['type'],
					'SRC'		=> ($is_single) ? $data[$file_key] : '',
				];

				$function = ($this->prepend_assets) ? 'array_unshift' : 'array_push';

				$function($this->assets[$namespace][$type], $new_asset);

				if($this->in_ajax_request)
				{
					\canidev\core\JsonResponse::getInstance()->addComponent('asset', $new_asset);
				}
			break;
			
			default:
				return false;
		}
	}
	
	/**
	 * Put assets on template
	 * 
	 * @param string 	$namespace
	 */
	public function assets_to_template($namespace = 'all')
	{
		if(sizeof($this->js_lang_keys))
		{
			$this->append_asset('script', [
				'id'		=> 'core-lang',
				'type'		=> 'application/json',
				'content'	=> json_encode($this->js_lang_keys, JSON_PRETTY_PRINT),
			]);
		}

		if(!isset($this->assets[$namespace]))
		{
			return false;
		}

		foreach($this->assets[$namespace] as $asset_type => $rows)
		{
			$key = 'core_' . (($namespace == 'all') ? '' : $namespace . '_') . $asset_type;
			
			foreach($rows as $row)
			{
				$this->phpbb_template->assign_block_vars($key, $row);
			}
		}
	}
	
	/**
	 * Assign vars to template
	 * 
	 * @param array 	$vars
	 */
	public function assign_vars(array $vars)
	{
		$this->phpbb_template->assign_vars($vars);
		$this->context_ary = array_merge($this->context_ary, $vars);
	}

	/**
	 * Assign JSON variable to template
	 * 
	 * @param string 	$key
	 * @param array 	$options
	 * 
	 * @return static
	 */
	public function assign_json_var($key, $options)
	{
		$options = array_map(
			function($item) {
				if(is_string($item))
				{
					return htmlspecialchars_decode($item);
				}

				return $item;
			},
			$options
		);

		$this->assign_var($key, json_encode($options));

		return $this;
	}
	
	/**
	 * Get template variable
	 * 
	 * @param string 	$name 			Variable name
	 * @param mixed 	$default 		Default value if variable don't exists
	 */
	public function get_var($name, $default = '')
	{
		return (isset($this->context_ary['.'][0][$name]) ? $this->context_ary['.'][0][$name] : $default);
	}
	
	/**
	 * Load template context
	 * 
	 * @param ContainerInterface 	$container
	 * @return array
	 */
	public function load_context(ContainerInterface $container)
	{
		$this->context_ary = $container->get('template_context')->get_data_ref();
		return $this->context_ary;
	}
	
	/**
	 * Render template
	 * 
	 * @param string 	$id 		Filename ID
	 * @param string 	$filename 	Template filename
	 * @param bool 		$return 	Set if result must be return to variable
	 * 
	 * @return string|void
	 */
	public function render($id, $filename, $return = false)
	{
		$this->phpbb_template->set_filenames([
			$id		=> $filename
		]);
		
		if($return)
		{
			return $this->phpbb_template->assign_display($id, null, true);
		}

		$this->phpbb_template->display($id);
	}

	/**
	 * Set javascript language variables
	 * 
	 * @param array 	$ary 			Language keys
	 * @param bool 		$convert_keys 	Convert phpBB keys into javascript keys
	 * 
	 * @return static
	 */
	public function set_js_lang($ary, $convert_keys = false)
	{
		if(array_keys($ary) === range(0, count($ary) - 1))
		{
			$new_ary = [];

			foreach($ary as $key)
			{
				$new_ary[$key] = $this->language->lang($key);
			}

			return $this->set_js_lang($new_ary, $convert_keys);
		}

		if($convert_keys)
		{
			foreach($ary as $key => $value)
			{
				$new_key = preg_replace_callback(
					'#_(\w)#', 
					function($m) {
						return strtoupper($m[1]);
					},
					strtolower($key)
				);

				$ary[$new_key] = $value;
				unset($ary[$key]);
			}
		}

		$this->js_lang_keys = array_merge($this->js_lang_keys, $ary);

		if($this->in_ajax_request)
		{
			\canidev\core\JsonResponse::getInstance()->addComponent('lang', $this->js_lang_keys);
		}

		return $this;
	}
	
	/**
	 * Find specific asset
	 * 
	 * @param string 	$path
	 * @param bool 		$is_admin
	 * 
	 * @return string|false
	 * @access private
	 * @deprecated 		To be removed in v1.2.0
	 */
	private function find_asset($path, $is_admin = false)
	{
		$paths = [];

		if(strpos($path, '@') === 0)
		{
			$uri = preg_replace_callback('#^@([A-Za-z\_0-9]+)/#', function($match) {
					return '%1$sext/' . str_replace('_', '/', $match[1]) . '/%2$s/';
				}, $path);
		}
		else
		{
			$uri = '%1$s%2$s/' . $path;
		}
		
		if($is_admin)
		{
			$filename = sprintf($uri, $this->path_helper->get_phpbb_root_path(), 'adm/style');
			
			if(file_exists($filename))
			{
				return $filename;
			}
		}

		if(!defined('IN_ADMIN'))
		{
			$paths = $this->phpbb_template->get_user_style();
		}

		$paths[] = 'all';
			
		foreach($paths as $style_path)
		{
			$filename = sprintf($uri, $this->path_helper->get_phpbb_root_path(), 'styles/' . $style_path . '/template');

			if(file_exists($filename))
			{
				return $filename;
			}
		}

		return false;
	}
	
	/**
	 * Get template instance
	 * 
	 * @param ContainerInterface  $container 	Service container
	 * @return template
	 * @deprecated 								To be removed in v 1.2.0. Use services instead
	 */
	static public function get_instance(ContainerInterface $container)
	{
		// Make sure services are loaded
		\canidev\core\lib::get_instance($container);

		// Return service
		return $container->get('canidev.core.template');
	}
}
