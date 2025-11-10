<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 * 
 */

namespace canidev\core;

use Symfony\Component\DependencyInjection\ContainerInterface;
use \canidev\core\JsonResponse;

class media
{
	private $params = [
		'action'		=> '',
		'allow_gallery'	=> true,
		'custom'		=> false,
		'sources'		=> [],
		'type'			=> 'image', // image, icon, attachment
		'query'			=> '',
		'query_mode'	=> '',
		'start'			=> 0,
	];
	
	private $upload_path		= '';
	private $referer_ext		= '';
	private $events_assigned	= false;
	private $instance_id 		= null;

	private $options = [
		'allowPagination'		=> false,
		'allowRemoteOrigin'		=> true,
		'allowedExtensions'		=> ['jpg', 'png', 'gif', 'webp', 'svg', 'ico', 'mp4', 'mov'],
		'imageMaxHeight'		=> 0,
		'imageMaxWidth' 		=> 0,
		'itemsPerPage'			=> 100,
		'max_filesize'			=> 0,
		'preserveFilenames'		=> false,
	];

	/** @var \phpbb\config\db */
	protected $config;

	/** @var ContainerInterface */
	protected $container;

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

	/** @var JsonResponse */
	protected $json;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\path_helper */
	protected $path;

	/** @var \phpbb\php\ini */
	protected $php_ini;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \canidev\core\template */
	protected $template;

	/** @var \phpbb\files\upload */
	protected $uploader;

	/** @var \phpbb\user */
	protected $user;
	
	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;
	
	static $instances = [];
	
	/**
	 * Constructor
	 *
	 * @param ContainerInterface 	$container			Service container interface
	 * @param string 				$instance_name 		Identifier of the instance
	 *
	 * @access public
	 */
	public function __construct(ContainerInterface $container, $instance_name)
	{
		$this->config			= $container->get('config');
		$this->container		= $container;
		$this->dispatcher		= $container->get('dispatcher');
		$this->json				= JsonResponse::getInstance();
		$this->language			= $container->get('language');
		$this->path				= $container->get('path_helper');
		$this->php_ini			= new \phpbb\php\ini;
		$this->request			= $container->get('request');
		$this->template			= $container->get('canidev.core.template');
		$this->uploader 		= $container->get('files.upload');
		$this->user				= $container->get('user');
		$this->phpbb_root_path	= $container->getParameter('core.root_path');
		$this->php_ext			= $container->getParameter('core.php_ext');

		$this->instance_id 		= $instance_name;
	}
	
	/**
	 * Build icon/image html template
	 * 
	 * @param string 	$image
	 * @return string
	 */
	public function build_icon_html($image)
	{
		if(!$image)
		{
			return '';
		}

		if($this->is_icon($image))
		{
			return '<i class="icon cbb-fa-icon fa ' . $image . '" aria-hidden="true"></i>';
		}
		
		return '<img src="' . $this->get_full_path($image) . '" alt="" class="cbb-media-icon" />';
	}
	
	/**
	 * Build html selector
	 * 
	 * @param string 		$key 			Input key
	 * @param string 		$type 			Type of media selector
	 * @param string|int 	$value 			Initial Value
	 * @param int 			$use_config 	Define if use "config" array on input name
	 * 
	 * @return string
	 */
	public function build_selector($key, $type, $value, $use_config = 0)
	{
		$label = strtoupper($type);

		return sprintf(
			'<a href="#" data-module="media" data-type="%1$s" data-value="%2$s" data-value-parsed="%3$s" data-id="%4$s" data-key="%5$s" data-label-btn="%6$s" data-label-delete="%7$s"></a>',
			$type,
			$value,
			($this->is_icon($value)) ? '' : $this->get_full_path($value),
			$this->instance_id,
			($use_config) ? "config[$key]" : $key,
			$this->language->lang("SELECT_$label"),
			$this->language->lang("DELETE_$label")
		);
	}
	
	/**
	 * Delete selected items
	 * 
	 * @param array 	$item_ids 		AFfected items
	 */
	public function delete_items($item_ids)
	{
		$this->params['items'] = array_map(
			function($value) {
				return is_numeric($value) ? intval($value) : $value;
			},
			$item_ids
		);

		$params 	= &$this->params;
		$rowset		= [];
		$instance 	= $this;
		
		/**
		 * @event media.gallery.delete
		 * @var array		params		The input params
		 * @var array		rowset		Array with files to delete
		 * @var media 		instance 	This media instance
		 * @since 1.0.1
		 */
		$vars = ['params', 'rowset', 'instance'];
		extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.delete', compact($vars)));
		
		// Try to delete the files from the server
		foreach($rowset as $filename)
		{
			@unlink($this->get_full_path($filename, false, true));
		}
	}

	/**
	 * Do ajax actions
	 */
	public function do_actions()
	{
		$instance_name = $this->request->variable('mediaInstance', '');
		
		if(!(
			$this->request->variable('cbbModule', '') == 'media' &&
			$this->request->is_ajax() &&
			(!$instance_name || $instance_name == $this->instance_id))
		)
		{
			return false;
		}
		
		$error 	= [];
		$post 	= $this->request->get_super_global(\phpbb\request\request_interface::POST);

		if(isset($post['media']))
		{
			$this->params = array_merge($this->params, $post['media']);
			$this->params['custom'] = (int)$this->params['custom'];
		}

		$this->json->add([
			'status'	=> JsonResponse::JSON_STATUS_SUCCESS,
			'action'	=> $this->params['action'],
		]);
		
		$this->options['max_filesize'] = $this->config['max_filesize'];
		
		if(function_exists('ini_get'))
		{
			$server_max_filesize = min(
				$this->php_ini->get_bytes('post_max_size'),
				max(1, $this->php_ini->get_bytes('memory_limit')),
				$this->php_ini->get_bytes('upload_max_filesize')
			);
			
			if($server_max_filesize)
			{
				$this->options['max_filesize'] = ($this->options['max_filesize']) ? min($this->options['max_filesize'], $server_max_filesize) : $server_max_filesize;
			}
		}

		$params 	= &$this->params;
		$json 		= &$this->json;
		$options 	= []; // Deprecated, to be removed in v1.2.0
		$instance 	= $this;

		/**
		 * @event media.gallery.before_action
		 * @var array 			params				Input params
		 * @var JsonResponse 	json				JSON response object
		 * @var array 			options 			Common Options (Deprecated)
		 * @var media 			instance 			This media instance
		 * @since 1.0.7
		 */
		$vars = ['params', 'json', 'options', 'instance'];
		extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.before_action', compact($vars)));
		
		switch($this->params['action'])
		{
			case 'create':
				switch($params['type'])
				{
					case 'icon':
						$submit_label = 'SET_ICON';
					break;
					
					case 'image':
						$submit_label = ($params['custom']) ? 'SAVE' : 'SET_IMAGE';
					break;
					
					default:
						$submit_label = 'INSERT_ON_ENTRY';
					break;
				}

				/**
				 * @event media.gallery.create
				 * @var array 			params				Input params
				 * @var JsonResponse 	json				JSON response object
				 * @var string			submit_label		Label for the submit button
				 * @var array 			options 			Common Options (Deprecated)
				 * @var media 			instance 			This media instance
				 * @since 1.0.7
				 */
				$vars = ['params', 'json', 'submit_label', 'options', 'instance'];
				extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.create', compact($vars)));

				$this->template->assign_vars([
					'CAN_INSERT_COPY'	=> ($this->upload_path) ? true : false,
					'S_MAX_IMAGESIZE'	=> ($this->options['imageMaxWidth'] && $this->options['imageMaxHeight']) ? $this->language->lang('MAX_IMAGESIZE', $this->options['imageMaxWidth'], $this->options['imageMaxHeight']) : '',
					'S_MAX_FILESIZE'	=> ($this->options['max_filesize']) ? $this->language->lang('MAX_FILESIZE', get_formatted_filesize($this->options['max_filesize'])) : '',
					'S_SUBMIT_LABEL'	=> $this->language->lang($submit_label),
				]);

				$this->json->add(['options' => $this->options]);
			
				$this
					->load_sections()
					->display();
			break;
			
			case 'delete':
				if(confirm_box(true))
				{
					if(!empty($this->params['items']))
					{
						$this->delete_items($this->params['items']);
					
						$this->json->send([
							'message'	=> $this->language->lang('FILES_DELETED'),
							'items'		=> $this->params['items'],
						]);
					}
					
					$error[] = $this->language->lang('AJAX_ERROR_TEXT');
				}
				else
				{
					confirm_box(
						false,
						$this->language->lang('FILES_REMOVE_CONFIRM'),
						build_hidden_fields([
							'action'	=> 'delete',
						]),
						'@canidev_core/confirmbox.html'
					);

					return false;
				}
			break;
			
			case 'insert':
				$headers 	= @get_headers($this->params['url'], 1);
				$params		= &$this->params;
				$filename	= $this->params['url'];
					
				if(!isset($headers['Content-Type']) || !preg_match('#image/(.*)#', $headers['Content-Type'], $match))
				{
					$error[] = $this->language->lang('FORMAT_INVALID');
					break;
				}

				switch($match[1])
				{
					case 'jpeg':
						$extension = 'jpg';
					break;
					
					case 'vnd.microsoft.icon':
					case 'x-icon':
						$extension = 'ico';
					break;

					case 'svg+xml':
						$extension = 'svg';
					break;

					default:
						$extension = $match[1];
					break;
				}

				if(!in_array($extension, $this->options['allowedExtensions']))
				{
					$error[] = $this->language->lang('FORMAT_INVALID');
					break;
				}
				
				if($this->params['copy'] && $this->upload_path)
				{
					$basename 	= $this->parse_basename($filename, $extension);
					$filename	= $this->get_full_path($basename, false, true);
					
					if($this->php_ini->get_bool('allow_url_fopen'))
					{
						$image_data = @file_get_contents($this->params['url']);
						
						if(!@file_put_contents($filename, $image_data))
						{
							$error[] = $this->language->lang('IMAGE_SAVE_ERROR');
							break;
						}
					}
					else
					{
						if(($ch = @curl_init($this->params['url'])) === false)
						{
							$error[] = $this->language->lang('IMAGE_SAVE_ERROR');
							break;
						}
						
						$fp = fopen($filename, 'wb');
						curl_setopt($ch, CURLOPT_FILE, $fp);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
						curl_exec($ch);
						curl_close($ch);
						fclose($fp);
					}

					// Resize uploaded image
					if($this->options['imageMaxWidth'] && $this->options['imageMaxHeight'] && in_array($extension, ['jpg', 'png', 'gif', 'webp']))
					{
						$image_parser = new image_parser();

						$image_parser
							->set_resize_limit($this->options['imageMaxWidth'], $this->options['imageMaxHeight'])
							->parse($filename, (($extension == 'jpg') ? 'jpeg' : $extension), image_parser::RESIZE);
					}

					list($this->params['width'], $this->params['height']) = $this->get_imagesize($filename, $extension);

					$filesize = @filesize($filename);
				}
				else
				{
					$basename = $filename;
					$filesize = (int)$headers['Content-Length'];
				}
				
				$rowset = [
					'id'			=> time(),
					'description'	=> isset($this->params['description']) ? $this->params['description'] : '',
					'title'			=> isset($this->params['title']) ? $this->params['title'] : '',
					'time'			=> time(),
					'filename'		=> $basename,
					'filesize'		=> ($filesize) ? $filesize : '',
					'width'			=> isset($this->params['width']) ? (int)$this->params['width'] : 0,
					'height'		=> isset($this->params['height']) ? (int)$this->params['height'] : 0,
				];
				
				/**
				 * @event media.gallery.insert
				 * @var array		rowset		The output array
				 * @var array		params		The input params
				 * @var array		headers		Returned headers of image url
				 * @var string		filename	Complete route of the file
				 * @var media 		instance 	This media instance
				 * @since 1.0.1
				 */
				$vars = ['rowset', 'params', 'headers', 'filename', 'instance'];
				extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.insert', compact($vars)));

				$this->json->send([
					'item'	=> $this->get_output('attachment', $rowset)
				]);
			break;

			case 'pagination':
			case 'search':
				$section = $this->load_sections();

				$this->json->send([
					'section' => $section
				]);
			break;
			
			case 'update':
				$params = &$this->params;

				/**
				 * @event media.gallery.update
				 * @var array		params		Array with item variables
				 * @var media 		instance 	This media instance
				 * @since 1.0.1
				 */
				$vars = ['params', 'instance'];
				extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.update', compact($vars)));
				
				$this->json->send([
					'message'	=> $this->language->lang('MEDIA_UPDATED'),
				]);
			break;
			
			case 'upload':
				$this->language->add_lang('posting');
				
				if(!$this->upload_path)
				{
					break;
				}

				if(!$this->uploader->is_valid('file'))
				{
					$error[] = $this->language->lang('FORM_INVALID');
				}
				
				if(!sizeof($error))
				{
					$this->uploader->set_allowed_extensions($this->options['allowedExtensions']);
					
					if($this->options['max_filesize'])
					{
						$this->uploader->set_max_filesize($this->options['max_filesize']);
					}

					$file = $this->uploader->handle_upload('files.types.form', 'file');

					$this->parse_basename($file);

					$file->move_file($this->upload_path, false, true);

					if(sizeof($file->error))
					{
						$file->remove();
						$error = array_merge($error, $file->error);
					}
					else if($free_space = @disk_free_space($this->phpbb_root_path . $this->upload_path))
					{
						// Check free disk space
						if($free_space <= $file->get('filesize'))
						{
							$error[] = $this->language->lang('ATTACH_QUOTA_REACHED');
							$file->remove();
						}
					}
					
					if(!sizeof($error))
					{
						$filename	= $this->get_full_path($file->get('realname'), false, true);
						$rowset		= [];

						@chmod($filename, 0644);

						list($width, $height) = $this->get_imagesize($filename, $file->get('extension'));
						
						$rowset = [
							'id'			=> time(),
							'description'	=> '',
							'title'			=> str_replace('.' . $file->get('extension'), '', $file->get('uploadname')),
							'time'			=> time(),
							'filename'		=> $file->get('realname'),
							'filesize'		=> $file->get('filesize'),
							'width'			=> (int)$width,
							'height'		=> (int)$height,
						];
						
						/**
						 * @event media.gallery.upload
						 * @var array		rowset		The output array
						 * @var object		file		The uploaded file object
						 * @var string		filename	Complete route of the file
						 * @var media 		instance 	This media instance
						 * @since 1.0.1
						 */
						$vars = ['rowset', 'file', 'filename', 'instance'];
						extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.upload', compact($vars)));
						
						$this->json->send([
							'item'	=> $this->get_output('attachment', $rowset)
						]);
					}
				}
			break;
		}
		
		if(sizeof($error))
		{
			$this->json->send([
				'status'	=> JsonResponse::JSON_STATUS_ERROR,
				'message'	=> implode('<br />', $error)
			]);
		}
	}

	/**
	 * Get images from path
	 * 
	 * @param string 	$path 	Path where search
	 * 
	 * @return array
	 */
	public function get_images($path = '')
	{
		$image_ary	= [];
		$query 		= strtolower($this->get_param('query', ''));

		if($path)
		{
			$full_path = $this->get_full_path($path, false, true);
		}
		else
		{
			$full_path = $this->get_upload_path();
		}
		
		if(!file_exists($full_path))
		{
			return [];
		}

		$directory	= new \DirectoryIterator($full_path);
		$regex		= new \RegexIterator($directory, '#(' . (($query) ? '(?!/|\\\).*' . preg_quote($query) . '.*' : '[^&\'"<>]+') . ')\.(?:' . implode('|', $this->options['allowedExtensions']) . ')$#i');

		foreach($regex as $fileinfo)
		{
			$file 		= $fileinfo->getFilename();
			$basename 	= substr($file, 0, strrpos($file, '.'));

			$image_ary[] = $this->get_output('image', array(
				'code'		=> $path . $file,
				'name'		=> ucfirst(str_replace('_', ' ', $basename)),
			));
		}

		return $image_ary;
	}

	/**
	 * Get formatted output
	 * 
	 * @param string 	$type
	 * @param array 	$rowset
	 * @param bool		$return_ids
	 * 
	 * @return array
	 */
	public function get_output($type, $rowset, $return_ids = false)
	{
		$output = [];
		
		if(empty($rowset))
		{
			return $output;
		}

		// Recursive task
		if(isset($rowset[0]))
		{
			foreach($rowset as $row)
			{
				if($type == 'attachment' && $return_ids)
				{
					$output[$row['id']] = $this->get_output($type, $row);
					continue;
				}

				$output[] = $this->get_output($type, $row);
			}
			
			return $output;
		}
		
		switch($type)
		{
			case 'attachment':
				$output = array(
					'originalId'	=> is_numeric($rowset['id']) ? (int)$rowset['id'] : $rowset['id'],
					'code'			=> $rowset['filename'],
					'title'			=> $rowset['title'],
					'description'	=> $rowset['description'],
					'filename'		=> $this->get_full_path($rowset['filename']),
					'filesize'		=> get_formatted_filesize($rowset['filesize']),
					'url'			=> $this->get_full_path($rowset['filename'], true),
					'width'			=> $rowset['width'],
					'height'		=> $rowset['height'],
				);
			break;
			
			case 'image':
				$filename = $this->get_full_path($rowset['code'], false, true);
				list($width, $height) = $this->get_imagesize($filename);
				
				$output = array_merge($rowset, array(
					'filename'	=> $this->get_full_path($rowset['code']),
					'filesize'	=> get_formatted_filesize(@filesize($filename)),
					'url'		=> $this->get_full_path($rowset['code'], true),
					'width'		=> $width,
					'height'	=> $height,
				));
			break;
		}
		
		$output['type'] = $type;
		
		return ($return_ids && $type == 'attachment') ? [$rowset['id'] => $output] : $output;
	}
	
	/**
	 * Get full path url
	 * 
	 * @param string 	$image 			Image name
	 * @param bool 		$full_url 		Define if append full domain to url
	 * @param bool 		$server_path 	Define if return a relative url to be used by php
	 * 
	 * @return string
	 */
	public function get_full_path($image, $full_url = false, $server_path = false)
	{
		$filename = '';
		$web_path = ($server_path) ? $this->path->get_phpbb_root_path() : $this->path->get_web_root_path();
		
		if(substr($image, 0, 2) == './')
		{
			$filename = $web_path . (($this->referer_ext) ? 'ext/canidev/' . $this->referer_ext . '/' : '') . 'images/' . substr($image, 2);
		}
		else if(substr($image, 0, 4) == 'http' || substr($image, 0, 2) == '//')
		{
			return $image;
		}
		else if($image)
		{
			$filename = $web_path . $this->upload_path . $image;
		}
		
		if($full_url && $filename)
		{
			return generate_board_url() . '/' . str_replace($web_path, '', $filename);
		}
		
		return $filename;
	}

	/**
	 * Get size of an image
	 * 
	 * @param string 	$filename
	 * @param string 	$extension
	 * 
	 * @return array
	*/
	public function get_imagesize($filename, $extension = '')
	{
		$height = $width = 0;

		if(!$extension && preg_match('#^([^&\'"<>]+)\.([a-z0-9]+)$#i', $filename, $match))
		{
			$extension = $match[2];
		}
		
		if($extension == 'svg')
		{
			$xml 		= \canidev\core\dom::simple_xml_file($filename);
			$xml_attr 	= $xml->attributes();
			$viewbox 	= explode(' ', $xml_attr->viewBox);

			$width 		= $viewbox[2]; 
			$height 	= $viewbox[3];
		}
		else if(in_array($extension, ['jpg', 'png', 'gif', 'webp', 'ico']))
		{
			list($width, $height) = @getimagesize($filename);
		}

		return array(
			(int)$width,
			(int)$height,
		);
	}

	/**
	 * Get option from this instance
	 * 
	 * @param string $key 	Key to search
	 * @return mixed
	 */
	public function get_option($key)
	{
		return isset($this->options[$key]) ? $this->options[$key] : null;
	}

	/**
	 * Get param from this instance
	 * 
	 * @param string 	$key 			Key to search
	 * @param mixed 	$default 		Returned value if param is not defined
	 * @return mixed
	 */
	public function get_param($key, $default = null)
	{
		return isset($this->params[$key]) ? $this->params[$key] : $default;
	}

	/**
	 * Get upload path
	 * @return string
	 */
	public function get_upload_path()
	{
		return $this->path->get_phpbb_root_path() . $this->upload_path;
	}

	/**
	 * Define if image is an icon
	 * @return bool
	 */
	public function is_icon($image)
	{
		return strpos($image, 'fa-') === 0;
	}

	/**
	 * Load language and assets
	 * @return media
	 */
	public function preload()
	{
		$this->language->add_lang('media', 'canidev/core');

		$this->template->append_asset('css', '@canidev_core/mediaManager.css');
		$this->template->append_asset('js', '@canidev_core/js/jquery-ui.min.js');
		$this->template->append_asset('js', '@canidev_core/js/mediaManager.js');

		return $this;
	}

	/**
	 * Set option to this instance
	 * 
	 * @string 	$key 	Key to set
	 * @mixed 	$value 	Value to set
	 * 
	 * @return media
	 */
	public function set_option($key, $value = null)
	{
		if(is_array($key))
		{
			foreach($key as $k => $v)
			{
				$this->set_option($k, $v);
			}

			return $this;
		}

		$this->options[$key] = $value;
		return $this;
	}

	/**
	 * Set param to this instance
	 * 
	 * @string 	$key 	Key to set
	 * @mixed 	$value 	Value to set
	 * 
	 * @return media
	 */
	public function set_param($key, $value = null)
	{
		if(is_array($key))
		{
			foreach($key as $k => $v)
			{
				$this->set_param($k, $v);
			}

			return $this;
		}

		$this->params[$key] = $value;
		return $this;
	}

	/**
	 * Set events for this instance
	 * 
	 * @param array 	$event_ary 		Array with Events
	 * @return media
	 */
	public function set_events($event_ary)
	{
		if(!$this->events_assigned)
		{
			foreach($event_ary as $event_name => $params)
			{
				$this->dispatcher->addListener('media.' . $this->instance_id . '.' . $event_name, $params);
			}
			
			$this->events_assigned = true;
		}
		
		return $this;
	}
	
	/**
	 * Set associated extension
	 * 
	 * @param string 	$ext_name 	Extension name
	 * @return media
	 */
	public function set_referer_ext($ext_name)
	{
		if($this->referer_ext == $ext_name)
		{
			return $this;
		}

		$this->referer_ext = $ext_name;
		
		// Try to autoload extension events
		$service_name = 'canidev.' . $ext_name . '.media_events';

		if($this->container->has($service_name))
		{
			$this->set_events($this->container->get($service_name)->getSubscribedEvents());
		}
		
		return $this;
	}

	/**
	 * Set upload path
	 * @return media
	 */
	public function set_upload_path($path)
	{
		$this->upload_path = $path;
		
		if(substr($this->upload_path, -1) != '/')
		{
			$this->upload_path .= '/';
		}
		
		return $this;
	}

	/**
	 * Display
	 */
	private function display()
	{
		$this->json->send(array(
			'htmlContent'	=> $this->template->render('media', '@canidev_core/media_body.html', true),
		));
	}
	
	/**
	 * Get galley items
	 * 
	 * @param array|false 	$item_ary		The input array or false if no items
	 * @param bool 			$return_index 	Return image index
	 * 
	 * @return array
	 * @access private
	 */
	private function get_gallery($item_ary = false, $return_index = false)
	{
		$rowset 		= [];
		$instance 		= $this;
		$total_items 	= 0;

		/** @deprecated	To be removed in v1.2.0 */
		$start = 0;

		/**
		 * @event media.gallery.load
		 * @var array		rowset			The output array
		 * @var array|bool	item_ary		The input array or false if no items
		 * @var int 		total_items 	Total item count (for pagination)
		 * @var media 		instance 		This media instance
		 * @since 1.0.1
		 */
		$vars = ['rowset', 'item_ary', 'total_items', 'start', 'instance'];
		extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.' . 'gallery.load', compact($vars)));

		if(empty($total_items))
		{
			$total_items = sizeof($rowset);
		}

		return [
			'items'		=> $this->get_output('attachment', $rowset, $return_index),
			'length'	=> $total_items,
		];
	}
	
	/**
	 * Get items for custom list
	 * 
	 * @return array
	 * @access private
	 */
	private function get_custom_list()
	{
		$items = $gallery_items = [];
		
		foreach($this->params['sources'] as $i => $source)
		{
			if(is_numeric($source))
			{
				$source = (int)$source;
				$gallery_items[$source] = $source;
			}

			$items[] = $source;
		}
		
		$gallery_items = $this->get_gallery($gallery_items, true);

		foreach($items as $i => $source)
		{
			if(is_int($source) && isset($gallery_items[$source]))
			{
				$items[$i] = $gallery_items[$source];
			}
			else if(preg_match('#^([^&\'"<>]+)\.(?:gif|png|jpg|jpeg|webp|svg)$#i', $source, $match))
			{
				$items[$i] = $this->get_output('image', array(
					'code'		=> $source,
					'name'		=> ucfirst(str_replace('_', ' ', $match[1])),
				));
			}
			else
			{
				unset($items[$i]);
			}
		}

		return [
			'items'		=> array_values($items),
			'length'	=> sizeof($items),
		];
	}

	/**
	 * Load menu sections
	 * @return media
	 * 
	 * @access private
	 */
	private function load_sections()
	{
		$sections		= [];
		$type			= $this->params['type'];
		$instance 		= $this;
		$section_id 	= !empty($this->params['section']) ? $this->params['section'] : null;
		$start 			= $this->get_param('start', 0);
		$query 			= $this->get_param('query', '');

		if($this->params['type'] == 'icon')
		{
			$core 		= $this->container->get('canidev.core.lib');
			$json_ary 	= @json_decode($core->get_data('font-awesome.json'), true);
				
			if($json_ary !== null)
			{
				foreach($json_ary as $cat_title => $row)
				{
					$items = [];

					if($section_id && $cat_title != $section_id)
					{
						continue;
					}

					// Perform search
					if($query)
					{
						foreach($row['items'] as $index => $icon_code)
						{
							if(stripos($icon_code, $query) === false)
							{
								unset($row['items'][$index]);
							}
						}

						$row['items'] = array_values($row['items']);
					}
					
					foreach($row['items'] as $index => $icon_code)
					{
						if($index < $start)
						{
							continue;
						}

						if(sizeof($items) >= $this->options['itemsPerPage'])
						{
							break;
						}
						
						$items[] = [
							'code'	=> $icon_code,
							'type'	=> 'icon',
						];
					}

					$sections[] = [
						'id'		=> $cat_title,
						'icon'		=> $row['icon'],
						'title'		=> $this->language->lang($cat_title),
						'editable'	=> false,
						'items'		=> $items,
						'length'	=> sizeof($row['items']),
					];
				}
			}
		}
		else
		{
			/**
			 * @event media.sections.load
			 * @var array			sections		The output array
			 * @var string			type			Type of media required (image | attachment)
			 * @var string|null 	section_id		ID of requested section (for pagination and seeker)
			 * @var media 			instance 		This media instance
			 * @since 1.0.1
			 */
			$vars = ['sections', 'type', 'section_id', 'instance'];
			extract($this->dispatcher->trigger_event('media.' . $this->instance_id . '.sections.load', compact($vars)));
			
			foreach($sections as $id => $section)
			{
				if($section_id && $section['id'] != $section_id)
				{
					unset($sections[$id]);
					continue;
				}

				if(isset($section['image_paths']))
				{
					$image_ary = [];
					
					foreach($section['image_paths'] as $path)
					{
						$image_ary = array_merge(
							$image_ary,
							$this->get_images($path)
						);
					}

					// Pagination
					$max = min(sizeof($image_ary), $start + $this->options['itemsPerPage']);

					for($i = $start; $i < $max; $i++)
					{
						$sections[$id]['items'][] = $image_ary[$i];
					}

					$sections[$id]['length'] = sizeof($image_ary);

					// Unnecessary elements
					unset($image_ary, $sections[$id]['image_paths']);
				}
			}
		}

		if(sizeof($sections))
		{
			$sections[] = 'separator';
		}
		
		if($this->params['allow_gallery'] && (!$section_id || $section_id == 'gallery'))
		{
			$sections[] = array_merge([
				'id'		=> 'gallery',
				'icon'		=> 'fa-camera',
				'title'		=> $this->language->lang('GALLERY'),
				'editable'	=> true,
				'sortable'	=> false,
			], $this->get_gallery());
		}
		
		if($this->params['type'] == 'image' && $this->params['custom'] && (!$section_id || $section_id == 'custom'))
		{
			$sections[] = array_merge([
				'id'		=> 'list',
				'icon'		=> 'fa-file-o',
				'title'		=> $this->language->lang('CURRENT_LIST'),
				'editable'	=> true,
				'sortable'	=> true,
			], $this->get_custom_list());
		}

		if(!$section_id)
		{
			$sections[] = 'separator';
		}
		
		if(!$section_id && $this->upload_path)
		{
			$sections[] = [
				'id'		=> 'upload',
				'icon'		=> 'fa-upload',
				'title'		=> $this->language->lang('UPLOAD_IMAGE'),
				'editable'	=> true,
				'sortable'	=> false,
			];
		}

		if(($this->options['allowRemoteOrigin'] || $this->upload_path) && !$section_id)
		{
			$sections[] = [
				'id'		=> 'insert',
				'icon'		=> 'fa-cloud-download',
				'title'		=> $this->language->lang('INSERT_URL'),
				'editable'	=> false,
				'sortable'	=> false,
			];
		}

		if($section_id)
		{
			return array_values($sections)[0];
		}

		$this->json->add([
			'sections'	=> $sections
		]);

		return $this;
	}

	/**
	 * Set image name
	 * 
	 * @param string|\phpbb\files\filespec 	$file 			Original file
	 * @param string 						$extension 		File extension
	 * 
	 * @return string|false
	 * @access private
	 */
	protected function parse_basename($file, $extension = '')
	{
		if(is_string($file))
		{
			if($this->options['preserveFilenames'])
			{
				$parts 		= parse_url($file);
				$basename 	= basename(strtolower($parts['path']));
				$basename 	= gen_rand_string(4) . '_' . str_replace(' ', '_', urldecode($basename));

				// Remove original extension
				$basename = preg_replace('#\.([a-z0-9]+)$#', '', $basename);
			}
			else
			{
				$basename = $this->user->data['user_id'] . '_' . md5(unique_id());
			}

			if($extension)
			{
				$basename .= '.' . $extension;
			}

			return $basename;
		}
		
		if($file instanceof \phpbb\files\filespec)
		{
			if($this->options['preserveFilenames'])
			{
				$file->clean_filename('real', gen_rand_string(4) . '_');
			}
			else
			{
				$file->clean_filename('unique_ext', $this->user->data['user_id'] . '_');
			}
		}

		return false;
	}
	
	/**
	 * Get media instance
	 * 
	 * @param ContainerInterface 	$container			Service container interface
	 * @param string 				$instance_name 		Name of the instance
	 * 
	 * @return static
	 */
	static public function get_instance(ContainerInterface $container, $instance_name = 'default')
	{
		if(!isset(self::$instances[$instance_name]))
		{
			self::$instances[$instance_name] = new self($container, $instance_name);
		}

		return self::$instances[$instance_name];
	}
}
