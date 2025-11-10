<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

use Symfony\Component\DependencyInjection\ContainerInterface;

class editor
{
	protected $config;
	protected $container;
	protected $db;
	protected $dispatcher;
	protected $language;
	protected $path;
	protected $request;
	protected $template;
	protected $user;
	protected $phpbb_root_path;
	protected $php_ext;

	public $resources_loaded 	= false;
	protected $plugins 			= [];

	private $bbcodes 			= null;
	private $smilies 			= null;
	private $smilies_per_page 	= 50;

	static $instance;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface 	$container		Service container interface
	 *
	 * @access public
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->config 			= $container->get('config');
		$this->container 		= $container;
		$this->db 				= $container->get('dbal.conn');
		$this->dispatcher 		= $container->get('dispatcher');
		$this->language 		= $container->get('language');
		$this->path				= $container->get('path_helper');
		$this->request 			= $container->get('request');
		$this->template 		= $container->get('canidev.core.template');
		$this->user 			= $container->get('user');
		$this->phpbb_root_path	= $container->getParameter('core.root_path');
		$this->php_ext			= $container->getParameter('core.php_ext');

		$this->language->add_lang('posting');
		$this->language->add_lang('editor', 'canidev/core');
	}

	/**
	 * Add plugin by filename
	 * 
	 * @param string 	$plugin_filename
	 * @param bool 		$is_global
	 */
	public function add_plugin($plugin_filename, $is_global = false)
	{
		$class_name	= str_replace('/', '\\', $plugin_filename);

		if(!class_exists($class_name))
		{
			return false;
		}

		$plugin = new $class_name($this, $this->container);
		$plugin->is_global($is_global);

		$plugin_name = $plugin->get_name();

		if(!isset($this->plugins[$plugin_name]))
		{
			$this->plugins[$plugin_name] = $plugin;
		}
	}

	/**
	 * Build editor template
	 * 
	 * @param array 	$options 		Editor options
	 * @param bool 		$return_data 	Define if editor template must be returned as variable
	 * 
	 * @return array|void
	 */
	public function build($options, $return_data = false)
	{
		if(!function_exists('display_custom_bbcodes'))
		{
			include($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		}

		// test
		$this->dispatcher->trigger_event('canidev.core.editor_before_build');

		$default_options = array(
			'id'					=> gen_rand_string(4),
			'name'					=> '',
			'value'					=> '',
			'type'					=> 'basic',
			'rows'					=> '',
			'showRemoveFormat' 		=> true,
			'plugins' 				=> [],	
		);

		$options = array_merge($default_options, $options);

		if(is_string($options['plugins']))
		{
			$options['plugins'] = explode(',', $options['plugins']);
		}

		// Load plugins
		foreach($this->plugins as $plugin_name => $plugin)
		{
			if($plugin->is_global() || in_array($plugin_name, $options['plugins']))
			{
				$plugin->is_enabled(true);

				$options['plugins'][] = $plugin_name;

				if($plugin->get_lang())
				{
					$this->template->set_js_lang($plugin->get_lang());
				}

				$this->template->append_asset('js', $plugin->get_filename());
			}
		}

		$options['plugins'] = array_unique($options['plugins']);

		// Editor and template variables
		if($options['type'] == 'advanced')
		{
			$options = array_merge($options, array(
				'emoticons'		=> $this->generate_editor_emoticons(),
				'stylesheets'	=> array(
					$this->template->append_asset('css', 'stylesheet.css', true),
					$this->template->append_asset('css', (!empty($this->config['allow_cdn']) && !empty($this->config['load_font_awesome_url'])) ? $this->config['load_font_awesome_url'] : './assets/css/font-awesome.min.css', true),
					$this->template->append_asset('css', '@canidev_core/editor.css', true),
				)
			));
		}

		// Sanitize options
		$sanitize_keys = ['plugins', 'toolbar', 'toolbarExclude'];

		foreach($sanitize_keys as $key)
		{
			if(isset($options[$key]) && is_string($options[$key]))
			{
				$parts 	= preg_split('#(,|\|)#', $options[$key], 0, PREG_SPLIT_DELIM_CAPTURE);
				$ary 	= [];

				foreach($parts as $code)
				{
					if($code == ',')
					{
						continue;
					}

					$ary[] = $code;
				}

				$options[$key] = $ary;
			}
		}

		// test
		$vars = ['options'];
		extract($this->trigger_event('editor_after_config', compact($vars)));

		// Load default resources
		if(!$this->resources_loaded)
		{
			$this->bbcodes_to_template();

			if($options['type'] == 'advanced')
			{
				$this->template->prepend_assets = true;
				$this->template->append_asset('css', '@canidev_core/editor.css');
				$this->template->append_asset('js', '@canidev_core/js/editor.min.js');
				$this->template->prepend_assets = false;
			}

			$this->template->set_js_lang([
				'AUTHOR',
				'CODE',
				'DATE',
				'DESCRIPTION_OPTIONAL',
				'INSERT',
				'MAXIMIZE',
				'PRINT',
				'REMOVE_FORMAT',
				'UNLINK',
				'URL',
				'VIEW_SOURCE',
			]);
		}

		$this->template->assign_vars(array(
			'S_EDITOR_CONTENT'		=> $options['value'],
			'S_EDITOR_ID'			=> $options['id'],
			'S_EDITOR_NAME'			=> $options['name'],
			'S_EDITOR_ROWS'			=> $options['rows'],

			'MAX_FONT_SIZE'			=> (int)$this->config['max_post_font_size'],
			'S_BBCODE_URL'			=> ($this->config['allow_post_links']) ? true : false,
			'S_SHOW_REMOVEFORMAT'	=> $options['showRemoveFormat'],
		));

		unset($options['value'], $options['name'], $options['rows']);

		$this->template->assign_json_var('S_EDITOR_OPTIONS', $options);

		$this->resources_loaded = true;

		if($return_data)
		{
			$this->template->set_filenames(array(
				'editor'	=> '@canidev_core/editor_body.html'
			));

			return array(
				'id'		=> $options['id'],
				'template' 	=> $this->template->assign_display('editor'),
			);
		}
	}

	/**
	 * Generate smilies for editor
	 */
	protected function generate_smilies()
	{
		if($this->smilies !== null)
		{
			return;
		}

		$root_path		= (defined('PHPBB_USE_BOARD_URL_PATH') && PHPBB_USE_BOARD_URL_PATH) ? generate_board_url() . '/' : $this->path->get_web_root_path();
		$i 				= 0;
		$smiley_ary 	= [];

		$this->smilies = array(
			'path'		=> $root_path . $this->config['smilies_path'] . '/',
			'items' 	=> [],
			'current' 	=> [],
			'start'		=> $this->request->variable('smiley_start', 0),
		);

		if(!$this->config['allow_smilies'])
		{
			return;
		}

		$sql = 'SELECT *
			FROM ' . SMILIES_TABLE . '
			ORDER BY smiley_order';
		$result = $this->db->sql_query($sql, 3600);
		while($row = $this->db->sql_fetchrow($result))
		{
			if(!in_array($row['smiley_url'], $smiley_ary))
			{
				$i++;

				if($i > $this->smilies['start'] && sizeof($this->smilies['current']) < $this->smilies_per_page)
				{
					$this->smilies['current'][$i] = $row['code'];
				}

				$this->smilies['items'][$i] = $row;

				$smiley_ary[] = $row['smiley_url'];
			}
		}
		$this->db->sql_freeresult($result);

		unset($smiley_ary);
	}

	/**
	 * Generate emoticons
	 * @return array
	 */
	protected function generate_editor_emoticons()
	{
		$this->generate_smilies();

		$result = array(
			'path'		=> $this->smilies['path'],
			'hidden' 	=> [],
		);

		foreach($this->smilies['items'] as $i => $row)
		{
			$result['hidden'][$row['code']] = $row['smiley_url'];
		}

		return $result;
	}

	/**
	 * Get available bbcodes
	 * @return array
	 */
	public function get_bbcodes()
	{
		if($this->bbcodes === null)
		{
			$this->bbcodes = [];

			$sql_ary = array(
				'SELECT'	=> 'b.bbcode_id, b.bbcode_tag, b.bbcode_helpline',
				'FROM'		=> [BBCODES_TABLE => 'b'],
				'WHERE'		=> 'b.display_on_posting = 1',
				'ORDER_BY'	=> 'b.bbcode_tag',
			);

			/**
			 * @event canidev.core.editor_bbcodes_modify_sql
			 * @var	array	sql_ary		The SQL array to get the bbcode data
			 * @since 1.0.4
			 */
			$vars = ['sql_ary'];
			extract($this->trigger_event('editor_bbcodes_modify_sql', compact($vars)));

			$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_ary));
			while ($row = $this->db->sql_fetchrow($result))
			{
				$tag = str_replace('=', '', $row['bbcode_tag']);
				$this->bbcodes[$tag] = $row;
			}
			$this->db->sql_freeresult($result);
		}

		return $this->bbcodes;
	}

	/**
	 * Parse bbcodes and put on template
	 */
	public function bbcodes_to_template()
	{
		$this->get_bbcodes();

		foreach($this->bbcodes as $tag => $row)
		{
			// If the helpline is defined within the language file, we will use the localised version, else just use the database entry...
			if($this->language->is_set(strtoupper($row['bbcode_helpline'])))
			{
				$row['bbcode_helpline'] = $this->language->lang(strtoupper($row['bbcode_helpline']));
			}

			$prevent_default = false;

			$custom_tag = array(
				'BBCODE_CODE'		=> $row['bbcode_tag'],
				'BBCODE_TAG'		=> $tag,
				'BBCODE_HELPLINE'	=> $row['bbcode_helpline'],
			);

			/**
			 * @event canidev.core.editor_bbcodes_modify_row
			 * @var	array	custom_tag			Template data of the bbcode
			 * @var	array	row					The data of the bbcode
			 * @var 	bool 	prevent_default 	Define if the default action is override
			 * @since 1.0.4
			 */
			$vars = ['custom_tag', 'row', 'prevent_default'];
			extract($this->trigger_event('editor_bbcodes_modify_row', compact($vars)));

			if($prevent_default)
			{
				continue;
			}

			$this->template->assign_block_vars('editor_custom_tag', $custom_tag);
		}
	}

	/**
	 * Parse smilies and put on template
	 * 
	 * @param bool 	$use_pagination
	 */
	public function smilies_to_template($use_pagination = true)
	{
		$this->generate_smilies();

		$this->template->destroy_block_vars('smileyrow');
		$this->template->destroy_block_vars('smiley_pagination');

		foreach($this->smilies['items'] as $i => $row)
		{
			if($use_pagination && !isset($this->smilies['current'][$i]))
			{
				continue;
			}

			$this->template->assign_block_vars('smileyrow', array(
				'SMILEY_CODE'	=> $row['code'],
				'A_SMILEY_CODE'	=> addslashes($row['code']),
				'SMILEY_IMG'	=> $this->smilies['path'] . $row['smiley_url'],
				'SMILEY_WIDTH'	=> $row['smiley_width'],
				'SMILEY_HEIGHT'	=> $row['smiley_height'],
				'SMILEY_DESC'	=> $row['emotion']
			));
		}

		// Generate pagination
		$total_smiley_count = sizeof($this->smilies['items']);

		if($use_pagination && $total_smiley_count > $this->smilies_per_page)
		{
			$pagination = $this->container->get('pagination');
			
			// Make sure $start is set to the last page if it exceeds the amount
			$this->smilies['start'] = $pagination->validate_start($this->smilies['start'], $this->smilies_per_page, $total_smiley_count);
			
			$pagination->generate_template_pagination('#?cbbModule=editor', 'smiley_pagination', 'smiley_start', $total_smiley_count, $this->smilies_per_page, $this->smilies['start']);
		}
	}

	/**
	 * Do ajax actions
	 */
	public function do_actions()
	{
		if(!($this->request->variable('cbbModule', '') == 'editor') && ($this->request->is_ajax() || $this->request->variable('ajaxRequest', false)))
		{
			return false;
		}

		if($this->request->variable('pagination', false))
		{
			$this->smilies_to_template();

			$this->template->set_filenames([
				'smiley'	=> '@canidev_core/smiley_box.html'
			]);

			\canidev\core\JsonResponse::getInstance()->send([
				'htmlContent'	=> $this->template->assign_display('smiley')
			]);
		}
	}

	/**
	 * Trigger plugins events
	 * 
	 * @param string 		$name 		Event name
	 * @param array 		$event
	 * 
	 * @return array
	 * 
	 */
	protected function trigger_event($name, $event)
	{
		$plugin_method = 'event_' . $name;

		foreach($this->plugins as $plugin_name => $plugin)
		{
			if($plugin->is_enabled() && method_exists($plugin, $plugin_method))
			{
				$event = call_user_func([$plugin, $plugin_method], $event);
			}
		}

		$event = $this->dispatcher->trigger_event('canidev.core.' . $name, $event);

		return $event;
	}

	/**
	 * Get editor instance
	 * 
	 * @param ContainerInterface 	$container		Service container interface
	 * @return editor
	 */
	static public function get_instance($container)
	{
		if(!self::$instance)
		{
			self::$instance = new self($container);
		}

		return self::$instance;
	}
}
