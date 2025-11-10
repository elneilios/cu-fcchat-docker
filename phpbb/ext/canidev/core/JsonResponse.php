<?php
/**
 * @package Ext Common Core
 * @version 1.1.4 26/01/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

/**
 * JSON class
 */
class JsonResponse
{
	const JSON_STATUS_ERROR		= 1;
	const JSON_STATUS_SUCCESS	= 2;

	protected $cache;

	static $instance;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->reset();
	}
	
	/**
	 * Add values to response
	 * 
	 * @param array|string 			$key 		Simple key or array with key => value
	 * @param string|array|null 	$value 		Set a single value if key is a string
	 * 
	 * @return static
	 */
	public function add($key, $value = null)
	{
		if(is_array($key))
		{
			foreach($key as $k => $value)
			{
				$this->add($k, $value);
			}

			return $this;
		}

		if(is_array($value) && isset($this->cache[$key]) && is_array($this->cache[$key]))
		{
			$this->cache[$key] = array_merge($this->cache[$key], $value);
		}
		else
		{
			$this->cache[$key] = $value;
		}

		return $this;
	}

	/**
	 * Add component to response
	 * 
	 * @param string 	$type
	 * @param array 	$component
	 */
	public function addComponent($type, $component)
	{
		if(!isset($this->cache['components'][$type]))
		{
			$this->cache['components'][$type] = [];
		}

		if($type == 'lang')
		{
			$this->cache['components'][$type] = array_merge($this->cache['components'][$type], $component);
			return;
		}

		$this->cache['components'][$type][] = $component;
	}
	
	/**
	 * Add HTML to response
	 * 
	 * @param string 	$html
	 * @param bool 		$append 		Set if defined html must be overwrite
	 * 
	 * @return static
	 */
	public function addHtml($html, $append = false)
	{
		if(!$append)
		{
			$this->cache['htmlContent'] = '';
		}
		
		$this->cache['htmlContent'] .= trim($html);
		
		return $this;
	}

	/**
	 * Send error message as return
	 * 
	 * @param string 	$message
	 */
	public function error($message)
	{
		$this
			->reset()
			->send([
				'status'	=> self::JSON_STATUS_ERROR,
				'message'	=> $message,
			]);
	}

	/**
	 * Get response value
	 * @return mixed
	 */
	public function get($key)
	{
		return isset($this->cache[$key]) ? $this->cache[$key] : '';
	}
	
	/**
	 * Check if response is initialized
	 * @return bool
	 */
	public function isRequested()
	{
		return (sizeof($this->cache) > 2 || !empty($this->cache['htmlContent']));
	}
	
	/**
	 * Reset all data
	 * @return static
	 */
	public function reset()
	{
		$this->cache = [
			'components'	=> [],
			'htmlContent'	=> '',
		];

		return $this;
	}
	
	/**
	 * Send response to browser
	 * 
	 * @param array|false 		$data 			Additional data to be appened
	 * @param bool 				$ignore_html 	Set if html must be send with response
	 */
	public function send($data = false, $ignore_html = false)
	{
		header('Content-Type: application/json');
		
		if($data !== false)
		{
			$this->add($data);
		}
		
		if($ignore_html)
		{
			unset($this->cache['htmlContent']);
		}

		if(!isset($this->cache['status']))
		{
			$this->cache['status'] = self::JSON_STATUS_SUCCESS;
		}
		
		echo json_encode($this->cache);

		garbage_collection();
		exit_handler();
	}

	/**
	 * Set response status
	 * 
	 * @param int 	$status 	jsonResponse::JSON_STATUS_SUCCESS | JsonResponse::JSON_STATUS_ERROR
	 * @return static
	 */
	public function setStatus($status = self::JSON_STATUS_SUCCESS)
	{
		$this->cache['status'] = $status;
		return $this;
	}

	/**
	 * Get class instance
	 * @return static
	 */
	static public function getInstance()
	{
		if(!static::$instance)
		{
			static::$instance = new static();
		}

		return static::$instance;
	}
}
