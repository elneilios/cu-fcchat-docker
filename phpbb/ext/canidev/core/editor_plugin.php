<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

class editor_plugin
{
	protected $is_global 	= false;
	protected $is_enabled 	= false;
	
	protected $container;
	protected $wrapper;

	/**
	 * Constructor
	 * 
	 * @param \canidev\core\editor 											$wrapper 		Editor class
	 * @param \Symfony\Component\DependencyInjection\ContainerInterface 	$container 		Service container interface
	 */
	public function __construct(
		\canidev\core\editor $wrapper,
		\Symfony\Component\DependencyInjection\ContainerInterface $container)
	{
		$this->container 	= $container;
		$this->wrapper 		= $wrapper;
	}

	/**
	 * Get javascript filename
	 * @return string
	 */
	public function get_filename()
	{
		return '';
	}

	/**
	 * Get language variables
	 * @return array|false
	 */
	public function get_lang()
	{
		return false;
	}

	/**
	 * Get unique plugin name
	 * @return string
	 */
	public function get_name()
	{
		return '';
	}

	/**
	 * Get if plugin is enabled
	 * @return bool
	 */
	public function is_enabled($status = null)
	{
		if($status === null)
		{
			return $this->is_enabled;
		}

		$this->is_enabled = (bool)$status;
	}

	/**
	 * Get if plugin is global
	 * @return bool
	 */
	public function is_global($status = null)
	{
		if($status === null)
		{
			return $this->is_global;
		}

		$this->is_global = (bool)$status;
	}
}
