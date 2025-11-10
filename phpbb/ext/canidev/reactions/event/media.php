<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\event;

class media
{
	protected $language;
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\language\language				$language		Language Object
	 * @param \phpbb\user							$user			User object
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\language\language $language,
		\phpbb\user $user)
	{
		$this->language	= $language;
		$this->user		= $user;
	}

	/**
	 * Get media events
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'gallery.before_action' => [$this, 'media_gallery_options'],
			'gallery.delete'		=> [$this, 'media_gallery_delete'],
			'gallery.insert'		=> [$this, 'media_gallery_upload'],
			'gallery.load'			=> [$this, 'media_gallery_load'],
			'gallery.upload'		=> [$this, 'media_gallery_upload'],
			'sections.load'			=> [$this, 'media_sections_load'],
		];
	}

	/**
	 * Set gallery options
	 * 
	 * @param array 	$event 		Event Data
	 */
	public function media_gallery_options($event)
	{
		$event['instance']->setOption([
			'allowPagination'		=> true,
			'allowedExtensions' 	=> ['jpg', 'png', 'gif', 'svg'],
			'allowImageInfo' 		=> false,
			'preserveFilenames'		=> true,
		]);
	}

	/**
	 * Delete gallery item
	 * 
	 * @param array 	$event 		Event Data
	 */
	public function media_gallery_delete($event)
	{
		$event['rowset'] = $event['params']['items'];
	}

	/**
	 * Load gallery items
	 * 
	 * @param array 	$event 		Event Data
	 */
	public function media_gallery_load($event)
	{
		$rowset 	= [];
		$start 		= $event['instance']->getParam('start', 0);
		$image_ary 	= $event['instance']->getImages();

		// Pagination
		$max = min(sizeof($image_ary), $start + $event['instance']->getOption('itemsPerPage', 0));

		for($i = $start; $i < $max; $i++)
		{
			$row 		= $image_ary[$i];
			$basename 	= basename($row['code']);
	
			$rowset[] = [
				'id'			=> $basename,
				'title'			=> '',
				'description'	=> '',
				'filename'		=> $basename,
				'filesize'		=> filesize($row['filename']),
				'width'			=> $row['width'],
				'height'		=> $row['height'],
			];
		}
		
		$event['rowset'] 		= $rowset;
		$event['total_items'] 	= sizeof($image_ary);
	}

	/**
	 * Upload item to gallery
	 * 
	 * @param array 	$event 		Event Data
	 */
	public function media_gallery_upload($event)
	{
		$rowset = $event['rowset'];
		$rowset['id'] = $rowset['filename'];
		$event['rowset'] = $rowset;
	}

	/**
	 * Load Custom sections
	 * 
	 * @param array 	$event 		Event Data
	 */
	public function media_sections_load($event)
	{
		$sections = [
			[
				'id'			=> 'default',
				'icon'			=> 'fa-folder',
				'title'			=> $this->language->lang('REACTIONS'),
				'editable'		=> false,
				'sortable'		=> false,
				'image_paths'	=> ['./reaction/'],
			],
		];
		
		$event['sections'] = $sections;
	}
}
