<?php
/**
 * @package Ext Common Core
 * @version 1.1.4 26/01/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

use Symfony\Component\HttpFoundation\File\File;

class Image
{
	const REFERENCE_BOTTOM_LEFT 	= 'bottom_left';
	const REFERENCE_BOTTOM_RIGHT 	= 'bottom_right';
	const REFERENCE_TOP_LEFT 		= 'top_left';
	const REFERENCE_TOP_RIGHT 		= 'top_right';
	const REFERENCE_CENTER 			= 'center';

	protected $file;
	protected $resource;

	protected $preserve_exif = true;

	protected $defaults = [
		'thumb_width'		=> 200,
		'watermark'			=> [
			'alpha'			=> 80, // percent
			'x'				=> 50, // percent
			'y'				=> 50, // percent
			'reference'		=> self::REFERENCE_CENTER,
			'size'			=> 15, // percent
		]
	];

	/**
	 * Constructor
	 * 
	 * @param string|\SplFileInfo|File 		$file
	 */
	public function __construct($file)
	{
		if(!$file instanceof File)
		{
			$file = new File(($file instanceof \SplFileInfo) ? $file->getPathname() : (string)$file, false);
		}

		if(!$file->isReadable())
		{
			return;
		}

		$this->file 		= $file;
		$this->resource 	= $this->resourceLoad($file);
	}

	/**
	 * Add watermark image
	 * 
	 * @param array 	$options
	 * @return static
	 */
	public function addWatermark($options)
	{
		$options 	= $this->assertWatermarkOptions($options);
		$file 		= new File($options['filename'], false);
		$resource 	= $this->resourceLoad($file);

		if(!$this->resource || !$resource)
		{
			return $this;
		}

		$image_size 	= $this->getResourceSize($this->resource);
		$watermark_size = $this->getResourceSize($resource);

		// watermark size (% of image)
		$dest_w	= ($image_size['width'] * $options['size']) / 100;
		$dest_h = $watermark_size['height'] * $dest_w / $watermark_size['width'];

		// Dest position in pixels
		$dest_x = ($image_size['width'] * $options['x']) / 100;
		$dest_y = ($image_size['height'] * $options['y']) / 100;

		switch($options['reference'])
		{
			case self::REFERENCE_BOTTOM_LEFT:
				$dest_y -= $dest_h;
			break;

			case self::REFERENCE_BOTTOM_RIGHT:
				$dest_x -= $dest_w;
				$dest_y -= $dest_h;
			break;
			
			case self::REFERENCE_TOP_LEFT:
				// Nothing
			break;

			case self::REFERENCE_TOP_RIGHT:
				$dest_x -= $dest_w;
			break;

			case self::REFERENCE_CENTER:
			default:
				$dest_x -= ($dest_w / 2);
				$dest_y -= ($dest_h / 2);
			break;
		}

		$resource = $this->resourceResize($resource, $dest_w, $dest_h);

		imagecopymerge(
			$this->resource,
			$resource,
			$dest_x,
			$dest_y,
			0,
			0,
			$dest_w,
			$dest_h,
			$options['alpha']
		);

		return $this;
	}

	/**
	 * Resize image
	 * 
	 * @param int 			$width
	 * @param int|null		$height		[Optional]
	 * @return static
	 */
	public function resize($width, $height = null)
	{
		if($this->resource)
		{
			$this->resource = $this->resourceResize($this->resource, $width, $height);
		}

		return $this;
	}

	/**
	 * Save image (overwrite original file)
	 * 
	 * @param int 		$quality 			Quality percent
	 * @return bool
	 */
	public function save($quality = 90)
	{
		if(!$this->file)
		{
			return false;
		}

		return $this->saveAs(null, $this->file->getPathname(), $quality);
	}

	/** 
	 * Save image with other filename or format (preserve original if filename is different)
	 * 
	 * @param string 			$type 				New Image type (bmp | gif | jpeg | png | webp). Set null to preserve original.
	 * @param string|null 		$filename 			New filename. Set null to use original filename
	 * @param int 				$quality 			Quality percent
	 * 
	 * @return bool
	 */
	public function saveAs($type = null, $filename = null, $quality = 90)
	{
		if(!$this->file)
		{
			return false;
		}

		$type 		= (!$type) ? $this->getImageType($this->file) : $type;
		$filename 	= (!$filename) ? $this->file->getPathname() : $filename;
		$quality 	= (int)$quality;

		if(!$this->resource)
		{
			// Save an exact copy
			if($filename != $this->file->getPathname())
			{
				return @copy($this->file->getPathname(), $filename);
			}

			return false;
		}

		return $this->resourceWrite($this->resource, $type, $filename, $quality);
	}

	/**
	 * Generate thumbnail
	 * 
	 * @param int|null 		$width 			Set null to use default value
	 * @return bool
	 */
	public function saveThumbnail($width = null)
	{
		if(!$this->resource)
		{
			return false;
		}

		$width 		= (!$width) ? $this->defaults['thumb_width'] : $width;
		$thumbnail 	= $this->resourceResize($this->resource, $width);

		return $this->resourceWrite(
			$thumbnail,
			$this->getImageType($this->file), 
			$this->file->getPath() . '/thumb_' . $this->file->getBasename(),
			100
		);
	}

	public function withExif($preserve_exif = true)
	{
		$this->preserve_exif = $preserve_exif;
		return $this;
	}

	protected function assertWatermarkOptions($options)
	{
		$options = array_merge(
			[
				'filename'		=> '',
			],
			$this->defaults['watermark'],
			$options
		);

		foreach($options as $key => $value)
		{
			if($key == 'filename' || $key == 'reference')
			{
				continue;
			}

			if(is_numeric($value))
			{
				$options[$key] = min($value, 100);
			}
		}

		return $options;
	}

	protected function getImageType(File $file)
	{
		switch($file->getMimeType())
		{
			case 'image/bmp':
			case 'image/gif':
			case 'image/jpeg':
			case 'image/png':
			case 'image/webp':
				return str_replace('image/', '', $file->getMimeType());
		}

		return null;
	}

	protected function getResourceSize($resource)
	{
		return [
			'height'	=> imagesy($resource),
			'width'		=> imagesx($resource),
		];
	}

	protected function readExif(File $file)
	{
		if($file->getMimeType() != 'image/jpeg')
		{
			return false;
		}

		@getimagesize($file->getPathname(), $imageinfo);
		
		$result 	= [
			'exif'		=> null,
			'iptcdata'	=> null,
		];
		
		// Prepare EXIF data bytes from source file
		$exifdata = (is_array($imageinfo) && key_exists('APP1', $imageinfo)) ? $imageinfo['APP1'] : null;
		
		if ($exifdata)
		{
			$exiflength = strlen($exifdata) + 2;
			
			if($exiflength <= 0xFFFF)
			{
				// Construct EXIF segment
				$result['exif'] = chr(0xFF) . chr(0xE1) . chr(($exiflength >> 8) & 0xFF) . chr($exiflength & 0xFF) . $exifdata;
			}
		}
		
		// Prepare IPTC data bytes from source file
		$iptcdata = (is_array($imageinfo) && key_exists('APP13', $imageinfo)) ? $imageinfo['APP13'] : null;
		
		if($iptcdata)
		{
			$iptclength = strlen($iptcdata) + 2;
			
			if($iptclength <= 0xFFFF)
			{
				// Construct IPTC segment
				$result['iptcdata'] = chr(0xFF) . chr(0xED) . chr(($iptclength >> 8) & 0xFF) . chr($iptclength & 0xFF) . $iptcdata;
			}
		}
	
		return $result;
	}

	protected function resourceLoad(File $file)
	{
		$fn = 'imagecreatefrom' . $this->getImageType($file);

		if(function_exists($fn) && $file->isFile() && $file->isReadable())
		{
			$resource = $fn($file->getPathname());
			imagealphablending($resource, false);

			return $resource;
		}

		return false;
	}

	protected function resourceResize($resource, $width, $height = null)
	{
		$original_size = $this->getResourceSize($resource);

		if(!$height)
		{
			$height = $original_size['height'] * ($width / $original_size['width']);	
		}

		if($width != $original_size['width'])
		{
			$resize = imagecreatetruecolor($width, $height);
			imagecolortransparent($resize, imagecolorallocatealpha($resize, 0, 0, 0, 127));
			imagealphablending($resize, false);
			imagesavealpha($resize, true);
			imagecopyresampled($resize, $resource, 0, 0, 0, 0, $width, $height, $original_size['width'], $original_size['height']);

			return $resize;
		}

		return $resource;
	}

	protected function resourceWrite($resource, $type, $filename, $quality)
	{
		$fn 		= 'image' . $type;
		$result 	= false;

		if(!function_exists($fn))
		{
			return false;
		}

		// Apply correct extension
		$filename = preg_replace('#\.(bmp|gif|png|jpeg|jpg|webp)$#', '.' . str_replace('jpeg', 'jpg', $type), $filename);

		if($this->preserve_exif)
		{
			$exif_data = $this->readExif($this->file);
		}

		imagesavealpha($resource, true);

		// Save
		switch($type)
		{
			case 'bmp':
			case 'gif':
				$result = $fn($resource, $filename);
			break;

			case 'png':
				// Convert quality percent to compression level
				$quality = ((0 - $quality) / 10) + 10;

			// no break
			
			case 'jpeg':
			case 'webp':
				$result = $fn($resource, $filename, $quality);
			break;
		}

		if($result && $this->preserve_exif)
		{
			$result = $this->writeExif($filename, $exif_data);
		}

		return $result;
	}

	protected function writeExif($destfile, $data)
	{
		if(!$data)
		{
			return true;
		}

		$destfilecontent = @file_get_contents($destfile);
	
		if(strlen($destfilecontent) > 0)
		{
			$destfilecontent 	= substr($destfilecontent, 2);
			$portiontoadd 		= chr(0xFF) . chr(0xD8);          // Variable accumulates new & original IPTC application segments
	
			//  Add EXIF data
			if($data['exif'])
			{
				$portiontoadd .= $data['exif'];
			}
	
			// Add IPTC data
			if($data['iptcdata'])
			{
				$portiontoadd .= $data['iptcdata'];
			}
	
			$outputfile = @fopen($destfile, 'w');
			
			if($outputfile)
			{
				fwrite($outputfile, $portiontoadd . $destfilecontent);
				fclose($outputfile);
				
				return true;
			}
		}
		
		return false;
	}
}
