<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core\di\pass;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

class core_pass extends RegisterListenersPass
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct('dispatcher', 'canidev.core.listener_listener', 'canidev.core.listener');
	}

	/**
	 * {@inheritDoc}
	 */
    public function process(ContainerBuilder $container)
    {
		if(!$this->has_dependency_extensions($container))
		{
			return;
		}

		$services_directory = $container->getParameter('core.root_path') . 'ext/canidev/core/config/';
		$services_file 		= 'services.yml';

		if(file_exists($services_directory . $services_file))
		{
			$filesystem = new \phpbb\filesystem\filesystem();
			$loader = new YamlFileLoader($container, new FileLocator($filesystem->realpath($services_directory)));
			$loader->load($services_file);

			parent::process($container);
		}
    }

	/**
	 * Check if core listener must be loaded
	 * 
	 * @param ContainerBuilder 	$container
	 * @return bool
	 */
	protected function has_dependency_extensions(ContainerBuilder $container)
	{
		$config_php_file = new \phpbb\config_php_file(
			$container->getParameter('core.root_path'),
			$container->getParameter('core.php_ext')
		);

		$config_data = $config_php_file->get_all();
		
		if(!empty($config_data))
		{
			$dbal_driver_class = $config_php_file->convert_30_dbms_to_31($config_php_file->get('dbms'));
			/** @var \phpbb\db\driver\driver_interface */
			$db = new $dbal_driver_class();
			$db->sql_connect(
				$config_php_file->get('dbhost'),
				$config_php_file->get('dbuser'),
				$config_php_file->get('dbpasswd'),
				$config_php_file->get('dbname'),
				$config_php_file->get('dbport')
			);

			$sql = 'SELECT *
				FROM ' . $config_php_file->get('table_prefix') . 'ext
				WHERE ext_name ' . $db->sql_like_expression('canidev/' . $db->get_any_char()) . '
				AND ext_active = 1';
			$result = $db->sql_query_limit($sql, 1);
			$ext_row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$db->sql_close();

			return ($ext_row) ? true : false;
		}

		return false;
	}
}
