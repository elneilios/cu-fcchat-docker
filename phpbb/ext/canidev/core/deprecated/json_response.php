<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

/**
 * JSON class
 * @deprecated		Use JsonResponse instead. To be removed in v1.2.0
 */
class json_response extends JsonResponse
{
	static $instance;

	/**
	 * Add component to response
	 * 
	 * @param string 	$type
	 * @param array 	$component
	 * 
	 * @deprecated
	 */
	public function add_component($type, $component)
	{
		return $this->addComponent($type, $component);
	}
	
	/**
	 * Add HTML to response
	 * 
	 * @param string 	$html
	 * @param bool 		$append 		Set if defined html must be overwrite
	 * 
	 * @return static
	 * @deprecated
	 */
	public function add_html($html, $append = false)
	{
		return $this->addHtml($html, $append);
	}

	/**
	 * Check if response is initialized
	 * 
	 * @return bool
	 * @deprecated
	 */
	public function is_requested()
	{
		return $this->isRequested();
	}

	/**
	 * Set response status
	 * 
	 * @param int 	$status 	json_response::JSON_STATUS_SUCCESS | json_response::JSON_STATUS_ERROR
	 * @return static
	 * @deprecated
	 */
	public function set_status($status = JsonResponse::JSON_STATUS_SUCCESS)
	{
		return $this->setStatus($status);
	}

	/**
	 * Get class instance
	 * @return static
	 * @deprecated
	 */
	static public function get_instance()
	{
		if(!self::$instance)
		{
			self::$instance = new self();
		}

		return self::$instance;
	}
}
