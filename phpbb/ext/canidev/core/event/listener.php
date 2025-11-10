<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core\event;

use canidev\core\Dom;
use canidev\core\JsonResponse;
use canidev\core\constants;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class listener implements EventSubscriberInterface
{
	protected $cache;
	protected $config;
	protected $container;
	protected $dispatcher;
	protected $language;
	protected $request;
	protected $template;
	protected $user;

	protected $phpbb_root_path;
	protected $php_ext;

	private $items				= [];
	private $in_acp_extensions 	= false;

	/**
	 * Constructor
	 * 
	 * @param \phpbb\cache\driver\driver_interface 			$cache 				Cache instance
	 * @param \phpbb\config\config 							$config				Config Object
	 * @param ContainerInterface 							$container			Service container interface
	 * @param \phpbb\event\dispatcher_interface				$dispatcher			Event dispatcher
	 * @param \phpbb\language\language 						$language			Language Object
	 * @param \phpbb\request\request 						$request			Request object
	 * @param \canidev\core\template 						$template 			Core Template object
	 * @param \phpbb\user									$user				User object
	 * @param string										$phpbb_root_path	phpBB root path
	 * @param string										$php_ext			phpEx
	 */
	public function __construct(
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\config\config $config,
		ContainerInterface  $container,
		\phpbb\event\dispatcher_interface $dispatcher,
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\canidev\core\template $template,
		\phpbb\user $user,
		$phpbb_root_path,
		$php_ext
	)
	{
		$this->cache 		= $cache;
		$this->config 		= $config;
		$this->container 	= $container;
		$this->dispatcher 	= $dispatcher;
		$this->language 	= $language;
		$this->request 		= $request;
		$this->template 	= $template;
		$this->user 		= $user;

		$this->phpbb_root_path 	= $phpbb_root_path;
		$this->php_ext 			= $php_ext;
	}

	/**
	 * {@inheritDoc}
	 */
	static public function getSubscribedEvents()
	{
		return [
			'core.common'								=> 'loadMainLibraries',
			'core.user_setup_after' 					=> ['userSetupAfter',		99], // Make sure it is loaded first
			'core.page_footer' 							=> ['pageFooter',			-10],
			'core.adm_page_footer' 						=> ['admPageFooter',		-10],
			'core.append_sid' 							=> 'fixControllerUrls',
			'core.acp_extensions_run_action' 			=> 'checkAcpExtensionsModule', // for phpBB < 3.2.1
			'core.acp_extensions_run_action_before' 	=> 'checkAcpExtensionsModule', // for phpBB >= 3.2.1

			'core.modify_text_for_display_after' 		=> 'modifyTextForDisplay',
			'core.modify_format_display_text_after' 	=> 'modifyTextForDisplay',

			KernelEvents::REQUEST 						=> ['onKernelRequest', 33],
		];
	}

	/**
	 * Execute actions on admin footer
	 */
	public function admPageFooter()
	{
		$this->pageFooter();

		// Build links to extensions configurations
		if($this->in_acp_extensions)
		{
			$template_ary = $this->template->load_context($this->container);

			if(!isset($template_ary['enabled']))
			{
				return;
			}

			$module_ary = $this->cache->get('_modules_acp');
			$modules 	= [];

			if($module_ary !== false)
			{
				foreach($module_ary['modules'] as $row)
				{
					if(preg_match('#^\\\(canidev\\\[a-z_\-0-9]+)\\\acp\\\([a-z_\-0-9]+)$#', $row['module_basename'], $match))
					{
						$ext_key = str_replace('\\', '/', $match[1]);

						if(!isset($modules[$ext_key]))
						{
							$modules[$ext_key] = $match[2];
						}
					}
				}
			}

			foreach($template_ary['enabled'] as $ext_key => $ext_data)
			{
				if(isset($modules[$ext_data['NAME']]))
				{
					$this->template->alter_block_array(
						'enabled[' . $ext_key . '].actions',
						[
							'ACTION_AJAX' 	=> 'false',
							'L_ACTION'		=> $this->language->lang('CONFIGURE'),
		                    'U_ACTION'	 	=> append_sid($this->phpbb_root_path . 'adm/index.' . $this->php_ext, 'i=-' . str_replace('/', '-', $ext_data['NAME']) . '-acp-' . $modules[$ext_data['NAME']], true, $this->user->session_id),
						]
					);
				}
			}
		}
	}

	/**
	 * Workaround to check if the user is in acp extensions module
	 */
	public function checkAcpExtensionsModule()
	{
		$this->in_acp_extensions = true;
	}

	/**
	 * In some strange cases, the url is not formed correctly, doubling "app.php" so, we fix it.
	 * 
	 * @param \phpbb\event\data 	$event
	 */
	public function fixControllerUrls($event)
	{
		if(!$this->config['enable_mod_rewrite'] && substr_count($event['url'], 'app.') > 1)
		{
			$event['url'] = preg_replace('#(app\.' . $this->php_ext . '/){2,}#', '$1', $event['url']);
		}
	}

	/**
	 * Try to load deprecated classes of core
	 * 
	 * @param string 	$class		Class name
	 */
	public function loadDeprecatedClass($class)
	{
		if($class[0] !== '\\')
		{
			$class = '\\' . $class;
		}

		$class 			= str_replace('\\', '/', $class);
		$namespace 		= '/canidev/core/';

		if(strpos($class, $namespace) === 0)
		{
			$basename 	= basename($class);
		
			$old_basename = preg_replace_callback('#([a-z]|^)([A-Z])#', function($match) {
				return $match[1] . '_' . $match[2];
			}, $basename);
			
			$old_basename = strtolower(trim($old_basename, '_'));

			// try to remove old class
			if(strtolower($basename) === $basename)
			{
				$deprecated_class_filename 	= $this->phpbb_root_path . 'ext' . $namespace . 'deprecated/' . str_replace($namespace, '', $class) .  '.' . $this->php_ext;
				$class_filename 			= $this->phpbb_root_path . 'ext' . $class . '.' . $this->php_ext;

				if(file_exists($deprecated_class_filename))
				{
					if(file_exists($class_filename) && (strpos($class_filename, '_') !== false || strtoupper(substr(\PHP_OS, 0, 3)) !== 'WIN'))
					{
						@unlink($class_filename);
					}

					require($deprecated_class_filename);
				}
			}
			else
			{
				$deprecated_class_filename 	= $this->phpbb_root_path . 'ext' . $namespace . 'deprecated/' . $old_basename .  '.' . $this->php_ext;
				$class_filename 			= $this->phpbb_root_path . 'ext/' . $namespace . $old_basename . '.' . $this->php_ext;
				
				if(file_exists($deprecated_class_filename) && (strpos($class_filename, '_') !== false || strtoupper(substr(\PHP_OS, 0, 3)) !== 'WIN'))
				{
					if(strtolower($class_filename) !== $class_filename && file_exists($class_filename))
					{
						@unlink($class_filename);
					}
				}
			}
		}
	}

	/**
	 * Get additional libraries and plugins for the extensions 
	 */
	public function loadMainLibraries()
	{
		spl_autoload_register([$this, 'loadDeprecatedClass'], true, true);

		if(($this->items = $this->cache->get('_cbbext_plugins')) === false)
		{
			$this->items = [];

			$base_path	= $this->phpbb_root_path . 'ext/' . constants::PLUGINS_PATH;
			$directory	= new \RecursiveDirectoryIterator($base_path);
			$iterator	= new \RecursiveIteratorIterator($directory);
			$regex		= new \RegexIterator($iterator, '/([a-z0-9_\-]+)\.' . $this->php_ext . '$/i');

			foreach($regex as $file)
			{
				$basename = $file->getBasename('.' . $this->php_ext);
				
				if($basename == 'base')
				{
					continue;
				}

				$path = str_replace(
				 ['\\', $base_path],
				 ['/', ''],
					$file->getPathInfo()->getPathname()
				);

				if(strpos($path, '/') !== false)
				{
					$path_parts = explode('/', $path, 2);
					
					$path 		= $path_parts[0];
					$basename	= $path_parts[1] . '/' . $basename;
				}

				if(!isset($this->items[$path]))
				{
					$this->items[$path] = [];
				}
				
				$this->items[$path][] = $basename;
			}

			$this->cache->put('_cbbext_plugins', $this->items);
		}
		
		// Initialize items and parse core events

		/** @var \phpbb\extension\manager */
		$ext_manager = $this->container->get('ext.manager');

		foreach($this->items as $namespace => $items)
		{
			if($namespace != 'all' && !$ext_manager->is_enabled('canidev/' . $namespace))
			{
				unset($this->items[$namespace]);
				continue;
			}

			foreach($items as $id => $item)
			{
				$fullname		= constants::PLUGINS_PATH . $namespace . '/' . $item;
				$filename		= $this->phpbb_root_path . 'ext/' . $fullname . '.' . $this->php_ext;
				$class_name 	= '\\' . str_replace('/', '\\', $fullname);
				$has_own_dir 	= strpos($item, '/') !== false;

				if(!file_exists($filename) || !class_exists($class_name))
				{
					unset($this->items[$namespace][$id]);
					continue;
				}

				$this->items[$namespace][$id] = $item = new $class_name($this->container);
				
				if($item->is_runnable())
				{
					if($has_own_dir)
					{
						$base_path = dirname($filename) . '/';
						
						if(is_dir($base_path . 'styles'))
						{
							$this->template->add_custom_namespace('plugin_' . $namespace . '_' . basename($base_path), $base_path);
						}
					}

					foreach($item->core_events() as $event_name => $params)
					{
						if(is_string($params))
						{
							$this->dispatcher->addListener($event_name, [$item, $params], -1);
						}
						else if(is_string($params[0]))
						{
							$this->dispatcher->addListener($event_name, [$item, $params[0]], isset($params[1]) ? $params[1] : -1);
						}
						else
						{
							foreach($params as $listener)
							{
								$this->dispatcher->addListener($event_name, [$item, $listener[0]], isset($listener[1]) ? $listener[1] : -1);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @deprecated 		Preserved only for compatibility. To be removed on 1.2.0
	 */
	public function load_plugins()
	{
		return $this->loadMainLibraries();
	}

	/**
	 * Wordaround to modify text for posts with custom event
	 * 
	 * @param \phpbb\event\data 	$event
	 */
	public function modifyTextForDisplay($event)
	{
		if($this->dispatcher->hasListeners('canidev.core.modify_text_for_display'))
		{
			$text 	= $event['text'];
			$flags 	= $event['flags'];
			$finder = Dom::stringToXpath($text);

			if($finder === false)
			{
				return $text;
			}
			
			/**
			 * @event canidev.core.modify_text_for_display
			 * @var	\DomXPath		finder		XPath Object
			 * @var	string			text		Original Post Text (Read Only)
			 * @var int				flags		The BBCode Flags
			 * @since 1.0.4
			 */
			$vars = ['finder', 'text', 'flags'];
			extract($this->dispatcher->trigger_event('canidev.core.modify_text_for_display', compact($vars)));

			$event['text'] = Dom::xpathToString($finder);
		}
	}

	/**
	 * Show empty 404 response for non-existent files inside "files" directory to prevent that phpBB shows default "Not found" page
	 * for "media" files and saves the request as "session_page" in DB
	 * 
	 * @param GetResponseEvent 	$event
	 */
	public function onKernelRequest(GetResponseEvent $event)
	{
		$uri = $event->getRequest()->getRequestUri();

		if(preg_match('#/files/[a-z0-9_\-]+/.*?\.[a-z0-9]+$#', $uri))
		{
			$response = new Response('', 404);
			$event->setResponse($response);
		}
	}

	/**
	 * Do actions on footer
	 */
	public function pageFooter()
	{
		$is_admin	= defined('IN_ADMIN');
		$js_events 	= [];

		// Execute ajax request
		if($this->request->is_ajax())
		{
			$json_request = JsonResponse::getInstance();

			if($json_request->isRequested())
			{
				$json_request
					->addHtml($this->template->assign_display('body'))
					->send();
			}
		}

		foreach($this->items as $namespace => $null)
		{
			if($event_string = $this->getJsEvents($namespace, $is_admin))
			{
				$js_events[] = $event_string;
			}
		}
		
		$this->template->assign_var('PHPBB_BRANCH', str_replace('.', '', substr(PHPBB_VERSION, 0, 3)) . 'x');
		
		// Add core assets
		$this->template->prepend_assets = true;
		$this->template->append_asset('css', '@canidev_core/cbbcore-acp.css', false, 'admin');
		$this->template->append_asset('css', '@canidev_core/' . $this->user->style['style_path'] . '.css');
		$this->template->append_asset('css', '@canidev_core/cbbcore.css');

		$this->template->append_asset('script', [
			'type'		=> 'text/javascript',
			'content'	=> implode("\n", $js_events),
		]);

		$this->template->append_asset('js', '@canidev_core/js/cbbcore.min.js');

		$this->template->assets_to_template();
	}

	/**
	 * Load core language and namespace
	 */
	public function userSetupAfter()
	{
		$this->language->add_lang('main', 'canidev/core');

		// Set Core template directories
		$this->template->add_custom_namespace('canidev_core', $this->phpbb_root_path . 'ext/canidev/core/', true);
		$this->template->assign_var('CANIDEV_CORE_STARTED', true);
	}

	/**
	 * Get javascript events of plugins
	 * 
	 * @param string 		$namespace 		Plugin namespace
	 * @param bool 			$is_admin 		Determine if load admin or frontend events
	 * 
	 * @return string
	 * @access private
	 */
	private function getJsEvents($namespace, $is_admin)
	{
		$js_code = [];

		foreach($this->items[$namespace] as $item)
		{
			if($item->is_runnable())
			{
				$events = (!$is_admin) ? $item->frontend_js_events() : $item->admin_js_events();
				
				if(sizeof($events))
				{
					foreach($events as $event_name => $code)
					{
						$js_code[] = "cbbCore.addListener('$event_name'," . str_replace(["\n", "\t", '{ ', ' }'], ['', '', '{', '}'], $code) . ');';
					}
				}
			}
		}
		
		if(sizeof($js_code))
		{
			array_unshift($js_code, "\n/* " . $namespace . ' */');
		}
		
		return trim(implode("\n", $js_code));
	}

	/**
	 * Magic method for compatibility, to load obsolete functions
	 * To be removed in v1.2.0
	 */
	public function __call($name, $arguments)
	{
		$name = preg_replace_callback('#_([a-z])#', function($match) {
			return strtoupper($match[1]);
		}, $name);

		if(method_exists($this, $name))
		{
			return call_user_func_array([$this, $name], $arguments);
		}
	}
}
