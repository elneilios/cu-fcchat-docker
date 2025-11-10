<?php
/**
 * @package Ext Common Core
 * @version 1.1.3 20/10/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

/**
 * @deprecated 		To be removed in v1.2.0
 */
class image_parser
{
	const RESIZE 	= 1;
	const THUMBNAIL = 2;
	const WATERMARK = 4;

	protected $quality = [
		'bmp'	=> false,
		'gif'	=> false,
		'jpeg' 	=> 80,
		'png' 	=> 2,
		'webp'	=> 80,
	];

	protected $max_sizes = [
		'img_height'	=> 1000,
		'img_width'		=> 1500,
		'thumb_height'	=> 150,
		'thumb_width'	=> 200,
	];

	protected $data;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->reset();
	}

	/**
	 * Parse image
	 * 
	 * @param string 	$filename
	 * @param string 	$type 		Image type (bmp | gif | jpeg | png | webp)
	 * @param int 		$flags 		ImageParser::RESIZE | ImageParser::THUMBAIL | ImageParser::WATERMARK
	 */
	public function parse($filename, $type, $flags = self::WATERMARK)
	{
		if(!file_exists($filename) || !isset($this->quality[$type]) || !$flags)
		{
			return;
		}

		$path = pathinfo($filename);
		
		$this->data['type'] 	= $type;
		$this->data['basename'] = $path['basename'];

		if(!$this->data['path'])
		{
			$this->data['path'] = $path['dirname'] . '/';
		}

		$size = getimagesize($filename);

		$this->data['width'] 	= $size[0];
		$this->data['height'] 	= $size[1];

		// Create image resource
		$fn = 'imagecreatefrom' . $this->data['type'];
		$this->data['resource'] = $fn($filename);

		if($flags & self::WATERMARK)
		{
			$this->addWatermark(40, 'left_bottom');
		}

		if($flags & self::RESIZE)
		{
			$this->resize();
		}

		$this->save($this->data['resource'], $this->data['basename']);

		if($flags & self::THUMBNAIL)
		{
			$this->generateThumb();
		}

		$this->reset();
	}

	/**
	 * Set base path
	 * @return static
	 */
	public function setPath($path)
	{
		if(substr($path, -1) != '/')
		{
			$path .= '/';
		}

		$this->data['path'] = $path;

		return $this;
	}

	/**
	 * Set resize dimensions
	 * 
	 * @param int|null 		$img_width
	 * @param int|null 		$img_height
	 * @param int|null 		$thumb_width
	 * @param int|null 		$thumb_height
	 * 
	 * @return static
	 */
	public function setResizeLimit($img_width = null, $img_height = null, $thumb_width = null, $thumb_height = null)
	{
		foreach($this->max_sizes as $key => $value)
		{
			if(${$key} !== null)
			{
				$this->max_sizes[$key] = (int)${$key};
			}
		}

		return $this;
	}

	/**
	 * Set watermark file
	 * 
	 * @param string 	$filename
	 * @return static
	 */
	public function setWatermark($filename)
	{
		$this->data['watermark_file'] = $filename;

		return $this;
	}

	/**
	 * Add watermark to image
	 * 
	 * @param int 		$alpha_level 		Watermark transparency
	 * @param string 	$position 			Watermark position (left_top | left_center | left_bottom | right_top | right_center | right_bottom | center)
	 */
	protected function addWatermark($alpha_level = 100, $position = '')
	{
		if(!$this->data['watermark_file'] || !file_exists($this->data['watermark_file']))
		{
			return;
		}

		$water = imagecreatefrompng($this->data['watermark_file']);

		$watermark_width	= imagesx($water);
		$watermark_height	= imagesy($water);
		$image_width 		= $this->data['width'];
		$image_height 		= $this->data['height'];
		
		// watermark size (15% of image)
		$dest_w	= ($image_width * 15) / 100;
		$dest_h = $watermark_height * $dest_w / $watermark_width;
		
		switch($position)
		{
			case 'left_bottom':
				$x_shift = $image_width - $dest_w - 20;
				$y_shift = 20;
			break;
			
			case 'right_top':
				$x_shift = 20;
				$y_shift = $image_height - $dest_h - 20;
			break;
			
			case 'left_top':
				$x_shift = $image_width - $dest_w - 20;
				$y_shift = $image_height - $dest_h - 20;
			break;
			
			case 'left_center':
				$x_shift = $image_width - $dest_w - 20;
				$y_shift = (int)($image_height / 2) - (int)($dest_h / 2) - 20;
			break;
			
			case 'right_center':
				$x_shift = 20;
				$y_shift = (int)($image_height / 2) - (int)($dest_h / 2) - 20;
			break;
			
			case 'right_bottom':
				$x_shift = 20;
				$y_shift = 20;
			break;
			
			case 'center':
			default:
				$x_shift = (int)($image_width / 2) - (int)($dest_w / 2) - 20;
				$y_shift = (int)($image_height / 2) - (int)($dest_h / 2) - 20;
			break;
		}

		$dest_x = $image_width - $dest_w - $x_shift;
		$dest_y = $image_height - $dest_h - $y_shift;
		
		$pct 		= $alpha_level / 100;
		$w 			= $watermark_width;
		$h 			= $watermark_height;
		$minalpha 	= 127;
		
		imagealphablending($water, false);
		
		for($x = 0; $x < $w; $x++)
		{
			for($y = 0; $y < $h; $y++)
			{
				$alpha = (imagecolorat($water, $x, $y) >> 24) & 0xFF;
				
				if($alpha < $minalpha)
				{
					$minalpha = $alpha;
				}
			}
		}
		
		for($x = 0; $x < $w; $x++)
		{
			for($y = 0; $y < $h; $y++)
			{
				$colorxy 	= imagecolorat($water, $x, $y);
				$alpha 		= ($colorxy >> 24) & 0xFF;
				
				if($minalpha !== 127)
				{
					$alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minalpha);
				}
				else
				{
					$alpha += 127 * $pct;
				}
				
				$alphacolorxy = imagecolorallocatealpha($water, ($colorxy >> 16) & 0xFF, ($colorxy >> 8) & 0xFF, $colorxy & 0xFF, $alpha);
				
				if(!imagesetpixel($water, $x, $y, $alphacolorxy))
				{
					return false;
				}
			}
		}

		imagecopyresampled($this->data['resource'], $water, $dest_x , $dest_y , 0, 0, $dest_w, $dest_h, $watermark_width, $watermark_height);
	}

	/**
	 * Generate thumbnail file
	 */
	protected function generateThumb()
	{
		$sizes = $this->validateSize('thumb');
		$thumb = imagecreatetruecolor($sizes['width'], $sizes['height']);
		imagecopyresampled($thumb, $this->data['resource'], 0, 0, 0, 0, $sizes['width'], $sizes['height'], $this->data['width'], $this->data['height']);

		$this->save($thumb, 'thumb_' . $this->data['basename']);

		imagedestroy($thumb);
	}

	/**
	 * Resize image
	 */
	protected function resize()
	{
		$sizes = $this->validateSize('img');

		if($sizes['width'] != $this->data['width'])
		{
			$resize = imagecreatetruecolor($sizes['width'], $sizes['height']);
			imagecopyresampled($resize, $this->data['resource'], 0, 0, 0, 0, $sizes['width'], $sizes['height'], $this->data['width'], $this->data['height']);

			$this->data['resource'] = $resize;
			$this->data['width'] 	= $sizes['width'];
			$this->data['height'] 	= $sizes['height'];
		}
	}

	/**
	 * Save image to file
	 * 
	 * @param \resource|\GdImage|false 	$resource
	 * @param string 					$filename
	 */
	protected function save($resource, $filename)
	{
		if($resource === false)
		{
			return;
		}

		$fn = 'image' . $this->data['type'];

		if(is_numeric($this->quality[$this->data['type']]))
		{
			return $fn($resource, $this->data['path'] . $filename, $this->quality[$this->data['type']]);
		}

		return $fn($resource, $this->data['path'] . $filename);
	}

	/**
	 * Reset image
	 */
	protected function reset()
	{
		if($this->data['resource'] !== null)
		{
			imagedestroy($this->data['resource']);
		}

		$this->data = [
			'resource' 			=> null,
			'width' 			=> 0,
			'height'			=> 0,
			'type'				=> '',
			'basename'			=> '',
			'path' 				=> '',
			'watermark_file'	=> null,
		];
	}

	/**
	 * Validate image size
	 * 
	 * @param string 	$prefix 		(img | thumb)
	 * @return array
	 */
	protected function validateSize($prefix = 'img')
	{
		$orig_width = $width = $this->data['width'];
		$orig_height = $height = $this->data['height'];

		$max_width 	= $this->max_sizes[$prefix . '_width'];
		$max_height = $this->max_sizes[$prefix . '_height'];

		if($width > $height)
		{
			// Landscape image

			if($width > $max_width)
			{
				$width 	= $max_width;
				$height = ceil($orig_height / ($orig_width / $max_width));
			}
		}
		else
		{
			// Square or portrait

			if($height > $max_height)
			{
				$height = $max_height;
				$width 	= ceil($orig_width / ($orig_height / $max_height));
			}
		}

		return [
			'height' 	=> $height,
			'width'		=> $width,
		];
	}
}
