<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

class asset extends \phpbb\template\asset
{
	protected $phpbb_template;
	protected $request;

	/**
	 * Constructor
	 * 
	 * @param string 						$url 				Asset url or filename
	 * @param \phpbb\path_helper 			$path_helper
	 * @param \phpbb\filesystem\filesystem 	$filesystem
	 * @param \phpbb\template\template 		$phpbb_template
	 * @param \phpbb\symfony_request 		$request
	 */
	public function __construct(
		$url,
		\phpbb\path_helper $path_helper,
		\phpbb\filesystem\filesystem $filesystem,
		\phpbb\template\template $phpbb_template,
		\phpbb\symfony_request $request
	)
	{
		$this->path_helper 		= $path_helper;
		$this->filesystem 		= $filesystem;
		$this->phpbb_template 	= $phpbb_template;
		$this->request 			= $request;

		$this->set_url($url);
	}

	/**
	 * Find asset filename
	 * @return bool
	 */
	public function allocate()
	{
		$path 	= $this->get_path();
		$paths 	= [];

		if(!$this->is_relative())
		{
			return true;
		}

		if(!$path)
		{
			return false;
		}

		if(strpos($path, '@') === 0)
		{
			$uri = preg_replace_callback('#^@([A-Za-z\_0-9]+)/#', function($match) {
					return '%1$sext/' . str_replace('_', '/', $match[1]) . '/%2$s/';
				}, $path);
		}
		else if(strpos($path, './') === 0)
		{
			$uri = '%1$s' . substr($path, 2);
		}
		else
		{
			$uri = '%1$s%2$s/' . $path;
		}

		foreach(array_merge($this->phpbb_template->get_user_style(), ['all']) as $style_name)
		{
			$paths[] = 'styles/' . $style_name . '/template';
			$paths[] = 'styles/' . $style_name . '/theme';
		}

		if(defined('IN_ADMIN'))
		{
			array_unshift($paths, 'adm/style');
		}
			
		foreach($paths as $style_path)
		{
			$filename = sprintf($uri, $this->path_helper->get_phpbb_root_path(), $style_path);
			$filename = $this->filesystem->clean_path($filename);

			if(file_exists($filename))
			{
				$this->set_url($filename);
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_url()
	{
		$url = $this->join_url($this->components);

		if(substr($url, 0, 2) == './')
		{
			$url = $this->request->getBasePath() . '/' . $url;
		}

		return $this->filesystem->clean_path($url);
	}
}
