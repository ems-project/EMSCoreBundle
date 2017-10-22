<?php
namespace EMS\CoreBundle\Helper;



use Symfony\Component\HttpKernel\KernelInterface;

class Image {
	
	public $fileName;
	public $config = [
			'_resize' => false,
			'_width' => '*',
			'_quality' => false,
			'_height' => '*',
			'_gravity' => 'center',
			'_radius' => false,
			'_background' => 'FFFFFF',
			'_radius_geometry' => 'topleft-topright-bottomright-bottomleft',
			'_watermark' => false,
	];
	/**@var KernelInterface */
	private $kernel;

	public function __construct(KernelInterface $kernel, $fileName, array $config) {
		$this->kernel = $kernel;
		$this->fileName = $fileName;
		$this->config = array_merge($this->config, $config);
	}

	private function applyWatermark($image, $width, $height, $watermark){
	
		$stamp = imagecreatefrompng($watermark);
		$sx = imagesx($stamp);
		$sy = imagesy($stamp);
		imagecopy($image, $stamp, ($width - $sx) /2, ($height - $sy)/2, 0, 0, $sx, $sy);
		return $image;
	}
	
	private function applyCorner($source_image, $source_width, $source_height, $radius, $radius_geometry, $colour){
		$corner_image = imagecreatetruecolor(
				$radius,
				$radius
				);
	
	
		$clear_colour = imagecolorallocate(
				$corner_image,
				0,
				0,
				0
				);
	
	
		$solid_colour = imagecolorallocate(
				$corner_image,
				hexdec(substr($colour, 1, 2)),
				hexdec(substr($colour, 3, 2)),
				hexdec(substr($colour, 5, 2))
				);
	
		imagecolortransparent(
				$corner_image,
				$clear_colour
				);
	
		imagefill(
				$corner_image,
				0,
				0,
				$solid_colour
				);
	
		imagefilledellipse(
				$corner_image,
				$radius,
				$radius,
				$radius * 2,
				$radius * 2,
				$clear_colour
				);
	
		/*
		 * render the top-left, bottom-left, bottom-right, top-right corners by rotating and copying the mask
		 */
	
		if(in_array("topleft", $radius_geometry) !== FALSE){
			imagecopymerge(
					$source_image,
					$corner_image,
					0,
					0,
					0,
					0,
					$radius,
					$radius,
					100
					);
		}
	
		$corner_image = imagerotate($corner_image, 90, 0);
	
		if(in_array("bottomleft", $radius_geometry) !== FALSE){
			imagecopymerge(
					$source_image,
					$corner_image,
					0,
					$source_height - $radius,
					0,
					0,
					$radius,
					$radius,
					100
					);
		}
	
		$corner_image = imagerotate($corner_image, 90, 0);
	
		if(in_array("bottomright", $radius_geometry) !== FALSE){
			imagecopymerge(
					$source_image,
					$corner_image,
					$source_width - $radius,
					$source_height - $radius,
					0,
					0,
					$radius,
					$radius,
					100
					);
		}
	
		$corner_image = imagerotate($corner_image, 90, 0);
	
		if(in_array("topright", $radius_geometry) !== FALSE){
			imagecopymerge(
					$source_image,
					$corner_image,
					$source_width - $radius,
					0,
					0,
					0,
					$radius,
					$radius,
					100
					);
		}
	
		$tansparent_colour = imagecolorallocate(
				$source_image,
				hexdec(substr($colour, 1, 2)),
				hexdec(substr($colour, 3, 2)),
				hexdec(substr($colour, 5, 2))
				);
			
		imagecolortransparent(
				$source_image,
				$tansparent_colour
				);
	
		return $source_image;
	
	}
	
	private function applyResize($resize, $image, $width, $height, $size, $background, $gravity){
		if (function_exists("imagecreatetruecolor") && ($temp = imagecreatetruecolor ($width, $height))) {
			$resizeFunction = 'imagecopyresampled';
		} else {
			$temp = imagecreate($width, $height);
			$resizeFunction = 'imagecopyresized';
		}
	
	
		$solid_colour = imagecolorallocate(
				$temp,
				hexdec(substr($background, 0, 2)),
				hexdec(substr($background, 2, 2)),
				hexdec(substr($background, 4, 2))
				);
	
		imagefill(
				$temp,
				0,
				0,
				$solid_colour
				);
	
	
		if($resize == 'fillArea'){
			if(($size[1]/$height) < ($size[0]/$width)){
				$cal_width = $size[1] * $width / $height;
				if(stripos($gravity, 'west') !== false)
				{
					call_user_func($resizeFunction, $temp, $image, 0, 0, 0, 0, $width, $height, $cal_width, $size[1]);
				}
				else if(stripos($gravity, 'est') !== false)
				{
					call_user_func($resizeFunction, $temp, $image, 0, 0, $size[0]-$cal_width, 0, $width, $height, $cal_width, $size[1]);
				}
				else{
					call_user_func($resizeFunction, $temp, $image, 0, 0, ($size[0]-$cal_width)/2, 0, $width, $height, $cal_width, $size[1]);
				}
			}
			else{
				$cal_height = $size[0] / $width * $height;
				if(stripos($gravity, 'north') !== false)
				{
					call_user_func($resizeFunction, $temp, $image, 0, 0, 0, 0, $width, $height, $size[0], $cal_height);
				}
				else if(stripos($gravity, 'south') !== false)
				{
					call_user_func($resizeFunction, $temp, $image, 0, 0, 0, $size[1]-$cal_height, $width, $height, $size[0], $cal_height);
				}
				else{
					call_user_func($resizeFunction, $temp, $image, 0, 0, 0, ($size[1]-$cal_height)/2, $width, $height, $size[0], $cal_height);
				}
			}
		}
		else if($resize == 'fill'){
			if(($size[1]/$height) < ($size[0]/$width)){
	
				$thumb_height = $width*$size[1]/$size[0];
				call_user_func($resizeFunction, $temp, $image, 0, ($height-$thumb_height)/2, 0, 0, $width, $thumb_height, $size[0], $size[1]);
			}
			else {
				$thumb_width = ($size[0]*$height)/$size[1];
				call_user_func($resizeFunction, $temp, $image, ($width-$thumb_width)/2, 0, 0, 0, $thumb_width, $height, $size[0], $size[1]);
			}
	
		}
		else{
			call_user_func($resizeFunction, $temp, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
		}
		//imagedestroy($image);
		return $temp;
	}
	
	public function generateImage(){
		$handle = fopen($this->fileName, "r");
		$contents = fread($handle, filesize($this->fileName));
		fclose($handle);
		
		$path = tempnam(sys_get_temp_dir(), 'ems_image');
		$size	= @getimagesizefromstring($contents);
		$image = @imagecreatefromstring($contents);	
		
		if($image === false){
			
			$ballPath= $this->kernel->locateResource('@EMSCoreBundle/Resources/public/images/big-logo.png');
			$handle = fopen($ballPath, "r");
			$contents = fread($handle, filesize($ballPath));
			fclose($handle);
			
			$size	= @getimagesizefromstring($contents);
			$image = @imagecreatefromstring($contents);
		}
		$width = $this->config['_width'];
		$height = $this->config['_height'];
	
		//adjuste width or height in case of ratio resize
		if($this->config['_resize'] && $this->config['_resize'] == 'ratio'){
			// if either width or height is an asterix
			if($width == '*' || $height == '*') {
				if($height == '*') {
					// recalculate height
					$height = ceil($width / ($size[0]/$size[1]));
				} else {
					// recalculate width
					$width = ceil(($size[0]/$size[1]) * $height);
				}
			} else {
				if (($size[1]/$height) > ($size[0]/$width)) {
					$width = ceil(($size[0]/$size[1]) * $height);
				} else {
					$height = ceil($width / ($size[0]/$size[1]));
				}
			}
		}
	
	
		if($this->config['_resize']){
			$image = $this->applyResize($this->config['_resize'], $image, $width, $height, $size, $this->config['_background'], $this->config['_gravity']);
		}
		if($this->config['_radius']){
			$image = $this->applyCorner($image, $width, $height, $this->config['_radius'], $this->config['_radius_geometry'], $this->config['_background']);
		}
		if(isset($this->config['_watermark']['_path']) && file_exists($this->config['_watermark']['_path'])) {
			$image = $this->applyWatermark($image, $width, $height, $this->config['_watermark']['_path']);
		}
	
		//convert into jpeg or png
		if($this->config['_quality']){
			imagejpeg($image, $path, $this->config['_quality']);
		}
		else {
			imagepng($image, $path);
		}
		imagedestroy($image);
		return $path;
	}
}