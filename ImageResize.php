<?php

namespace gromovfjodor\imageresize;

use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Yii;
use yii\helpers\Url;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use yii\base\Exception;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;

class ImageResize {

	const IMAGE_OUTBOUND = ManipulatorInterface::THUMBNAIL_OUTBOUND;
	const IMAGE_INSET = ManipulatorInterface::THUMBNAIL_INSET;
	const IMAGE_CUSTOM = 'custom';

	const CROP_CENTER = 'center';
	const CROP_TOP = 'top';
	const CROP_BOTTOM = 'bottom';
	const CROP_LEFT = 'left';
	const CROP_RIGHT = 'right';

	/** @var array $cachePath path alias relative with webroot where the cache files are kept, use diffrent path  */
	public $cachePath = ['assets/images'];

	/** @var int $cacheExpire */
	public $cacheExpire = 0;

	/** @var int $imageQuality */
	public $imageQuality = 80;

	/** @var int $useFilename if true show filename in url */
	public $useFilename = true;

	/** @var int $absoluteUrl if true include domain in url */
	public $absoluteUrl = false;

	/**
	 * Creates and caches the image thumbnail and returns full path from thumbnail file.
	 *
	 * @param string $filePath to original file
	 * @param integer $width
	 * @param integer $height
	 * @param string $mode
	 * @param integer $quality (1 - 100 quality)
	 * @param string $chosenFileName (custome filename)
	 * @return string
	 * @throws Exception
	 */
	public function generateImage($filePath, $width, $height, $mode = "outbound", $quality = null, $chosenFileName = null) {
		$filePath = FileHelper::normalizePath(Yii::getAlias($filePath));
		if (!is_file($filePath)) {
			throw new Exception("File $filePath doesn't exist");
		}

		//set resize mode
		$resizeMode = null;
		$horzMode = null;
		$vertMode = null;
		switch ($mode) {
			case "outbound":
				$resizeMode = ImageResize::IMAGE_OUTBOUND;
				break;
			case "inset":
				$resizeMode = ImageResize::IMAGE_INSET;
				break;
			default:
				$hv = explode(':', $mode);
				if(count($hv) != 2)
					throw new Exception("generateImage $mode is not valid choose for 'outbound', 'inset' or 'horz:vert'");

				$resizeMode = self::IMAGE_CUSTOM;
				list($horzMode, $vertMode) = $hv;

				if(!in_array($horzMode, [self::CROP_LEFT, self::CROP_CENTER, self::CROP_RIGHT]))
					throw new Exception("generateImage $horzMode is not valid choose for 'left', 'center' or 'right'");
				if(!in_array($vertMode, [self::CROP_TOP, self::CROP_CENTER, self::CROP_BOTTOM]))
					throw new Exception("generateImage $vertMode is not valid choose for 'top', 'center' or 'bottom'");
				break;
		}

		//get fileinfo
		$aFileInfo = pathinfo($filePath);

		//set default filename
		$sFileHash = md5($filePath . $width . $height . $mode . filemtime($filePath));
		$imageFileName = null;

		//if $this->useFilename set to true? use seo friendly name
		if ($this->useFilename === true) {
			//set hash and default name
			$sFileHashShort = substr($sFileHash, 0, 6);
			$sFileName = $aFileInfo['filename'];
			//set choosen filename if $chosenFileName not null.
			if ($chosenFileName !== null) {
				$sFileName = preg_replace('/(\.\w+)$/', '', $chosenFileName);
			}
			//replace for seo friendly file name
			$sFilenameReplace = preg_replace("/[^\w\.\-]+/", '-', $sFileName);
			//set filename
			$imageFileName = $sFileHashShort . "_" . $sFilenameReplace;
			//else use file hash as filename	
		} else {
			$imageFileName = $sFileHash;
		}

		$imageFileExt = "." . $aFileInfo['extension'];
		$images = [];
		foreach ($this->cachePath as $cachePath)
		{
			$cachePath     = Yii::getAlias('@webroot/' . $cachePath);
			$imageFilePath = $cachePath . DIRECTORY_SEPARATOR . substr($imageFileName, 0, 2);
			$imageFile     = $imageFilePath . DIRECTORY_SEPARATOR . $imageFileName . $imageFileExt;

			if (file_exists($imageFile))
			{
				if ($this->cacheExpire !== 0 && (time() - filemtime($imageFile)) > $this->cacheExpire)
				{
					unlink($imageFile);
				}
				else
				{
					return $imageFile;
				}
			}
			//if dir not exist create cache edir
			if (!is_dir($imageFilePath))
			{
				FileHelper::createDirectory($imageFilePath, 0755);
			}
      
      //create image
      $box = new Box($width, $height);
      $image = Image::getImagine()->open($filePath);
      if($resizeMode == self::IMAGE_CUSTOM) {
        $cropRect = $this->getCropRectangle($image, $box, $horzMode, $vertMode);
        $image = $image->crop(new Point($cropRect[0], $cropRect[1]), new Box($cropRect[2], $cropRect[3]))->resize($box);
      } else {
        $image = $image->thumbnail($box, $resizeMode);
      }

			$options = [
				'quality' => $quality === null ? $this->imageQuality : $quality
			];
			$image->save($imageFile, $options);
			$images[] = $imageFile;
		}

		return $images[0];
	}

	/**
	 * Creates and caches the image and returns URL from resized file.
	 *
	 * @param string $filePath to original file
	 * @param integer $width
	 * @param integer $height
	 * @param string $mode
	 * @param integer $quality (1 - 100 quality)
	 * @param string $fileName (custome filename)
	 * @return string
	 */
	public function getUrl($filePath, $width, $height, $mode = "outbound", $quality = null, $fileName = null) {
		//get original file 
		$normalizePath = FileHelper::normalizePath(Yii::getAlias($filePath));
		//get cache url
		$filesUrls = [];
		foreach ($this->cachePath as $cachePath)
		{
			$cacheUrl = Yii::getAlias($cachePath);
			//generate file
			$resizedFilePath = self::generateImage($normalizePath, $width, $height, $mode, $quality, $fileName);
			//get resized file
			$normalizeResizedFilePath = FileHelper::normalizePath($resizedFilePath);
			$resizedFileName          = pathinfo($normalizeResizedFilePath, PATHINFO_BASENAME);
			//get url
			$sFileUrl = Url::to('@web/' . $cacheUrl . '/' . substr($resizedFileName, 0, 2) . '/' . $resizedFileName, $this->absoluteUrl);

			//return path
			$filesUrls[] = $sFileUrl;
		}

		return $filesUrls[0];
	}

	/**
	 * Clear cache directory.
	 *
	 * @return bool
	 */
	public function clearCache() {
		$cachePath = Yii::getAlias('@webroot/' . $this->cachePath);
		//remove dir
		FileHelper::removeDirectory($cachePath);
		//creat dir
		return FileHelper::createDirectory($cachePath, 0755);
	}

	/**
	 * Get crop rectangle for custom thumbnail mode
	 *
	 * @param ImageInterface $image
	 * @param Box $targetBox
	 * @param string $horzMode
	 * @param string $vertMode
	 * @return int[]
	 */
	private function getCropRectangle($image, $targetBox, $horzMode, $vertMode)
	{
		$imageBox = $image->getSize();
		$kw = $imageBox->getWidth() / $targetBox->getWidth();
		$kh = $imageBox->getHeight() / $targetBox->getHeight();
		$x = $y = $w = $h = 0;
		if($kh > $kw) {
			$x = 0;
			$w = $imageBox->getWidth();
			$h = $targetBox->getHeight() * $kw;
			switch ($vertMode) {
				case self::CROP_TOP:
					$y = 0;
					break;
				case self::CROP_BOTTOM:
					$y = $imageBox->getHeight() - $h;
					break;
				case self::CROP_CENTER:
					$y = ($imageBox->getHeight() - $h) / 2;
					break;
			}
		} else {
			$y = 0;
			$h = $imageBox->getHeight();
			$w  = $targetBox->getWidth() * $kh;
			switch ($horzMode) {
				case self::CROP_LEFT:
					$x = 0;
					break;
				case self::CROP_RIGHT:
					$x = $imageBox->getWidth() - $w;
					break;
				case self::CROP_CENTER:
					$x = ($imageBox->getWidth() - $w) / 2;
					break;
			}
		}
		return [$x, $y, $w, $h];
	}

}
