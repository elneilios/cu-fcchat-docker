<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core\plugins;

use Symfony\Component\DependencyInjection\ContainerInterface;

class base
{
	public $name = '';

	protected $container;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface 	$container		Service container interface
	 *
	 * @access public
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}
	
	/**
	 * Define if plugin can be loaded
	 * @return bool
	 */
	public function is_runnable()
	{
		if($this->name)
		{
			return $this->container->get('ext.manager')->is_enabled($this->name);
		}
		
		return true;
	}
	
	/**
	 * Obtain php events of the plugin
	 * @return array
	 */
	public function core_events()
	{
		return [];
	}

	/**
	 * Obtain main javascript events of the plugin
	 * @return array
	 */
	public function frontend_js_events()
	{
		return [];
	}
	
	/**
	 * Obtain admin javascript events of the plugin
	 * @return array
	 */
	public function admin_js_events()
	{
		return [];
	}
}
