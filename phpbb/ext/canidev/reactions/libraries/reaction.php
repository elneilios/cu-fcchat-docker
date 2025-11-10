<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\libraries;

use Symfony\Component\DependencyInjection\ContainerInterface;

class reaction
{
	protected $language;
	protected $media;

	protected $data 				= [];
	protected $def_launcher_icon 	= null;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface 		$container				Service container interface
	 * @param array|false 				$reaction_data 			Inital Data for this reaction
	 *
	 * @access public
	 */
	public function __construct(
		ContainerInterface $container,
		$reaction_data = false)
	{
		$this->data 		= ($reaction_data === false) ? [] : $reaction_data;
		$this->language 	= $container->get('language');
		$this->media 		= \canidev\core\Media::getInstance($container, 'reactions');

		$this->media
			->setRefererExt('reactions')
			->setUploadPath('files/reactions/');
	}

	/**
	 * Get reaction color
	 * @return string
	 */
	public function get_color()
	{
		return isset($this->data['reaction_color']) ? $this->data['reaction_color'] : '';
	}

	/**
	 * Get reaction ID
	 * @return int
	 */
	public function get_id()
	{
		return isset($this->data['reaction_id']) ? (int)$this->data['reaction_id'] : 0;
	}

	/**
	 * Get reaction image
	 * @return string
	 */
	public function get_image_url()
	{
		return isset($this->data['reaction_image']) ? $this->media->getFullPath($this->data['reaction_image'], true) : '';
	}

	/**
	 * Get reaction score
	 * @return int
	 */
	public function get_score()
	{
		return isset($this->data['reaction_score']) ? intval($this->data['reaction_score']) : 0;
	}

	/**
	 * Get reaction title
	 * @return string
	 */
	public function get_title()
	{
		return isset($this->data['reaction_title']) ? $this->language->lang($this->data['reaction_title']) : '';
	}

	/**
	 * Get reaction launcher icon
	 * @return string
	 */
	public function get_launcher_html()
	{
		if(!$this->has_data() || !$this->is_enabled())
		{
			return $this->get_default_launcher_icon();
		}

		return '<img src="' . $this->get_image_url() . '" alt="reaction" />
			<span style="color:' . $this->get_color() . ';">' . $this->get_title() . '</span>';
	}

	/**
	 * Determine if reaction is empty/invalid
	 * @return bool
	 */
	public function has_data()
	{
		return !empty($this->data);
	}

	/**
	 * Determine is reaction is enabled
	 * @return bool
	 */
	public function is_enabled()
	{
		return !empty($this->data['reaction_enabled']);
	}

	/**
	 * Output reaction data to template
	 * @return array
	 */
	public function to_template()
	{
		return [
			'ID'				=> $this->get_id(),
			'S_COLOR'			=> $this->get_color(),
			'S_IMAGE_URL'		=> $this->get_image_url(),
			'S_TITLE'			=> $this->get_title(),
		];
	}

	/**
	 * Get default launcher icon (no valid reaction is present)
	 * @return string
	 */
	protected function get_default_launcher_icon()
	{
		if($this->def_launcher_icon === null)
		{
			$this->def_launcher_icon = @file_get_contents($this->media->getFullPath('./launcher.svg', false, true));
		}

		return $this->def_launcher_icon;
	}
}
