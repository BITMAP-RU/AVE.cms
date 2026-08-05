<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/WatermarkService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\RuntimeDirectoryGuard;
	use App\Frontend\PublicSettings;
	use App\Helpers\Dir;
	use App\Helpers\File;

	/** Applies the configured watermark while preserving the original copy. */
	class WatermarkService
	{
		public function apply($file, $position = 'center', $transparency = 100)
		{
			$file = (string) $file;
			if (!defined('WATERMARKS_DIR') || !defined('WATERMARKS_FILE')) {
				return $file;
			}

			$info = pathinfo($file);
			if (empty($info['dirname']) || empty($info['basename'])) {
				return $file;
			}

			$watermarkFile = BASEPATH . '/' . ltrim(WATERMARKS_FILE, '/');
			$watermarkRoot = BASEPATH . '/' . trim(WATERMARKS_DIR, '/');
			$imagePath = BASEPATH . '/' . trim($info['dirname'], '/');
			$imageFile = $imagePath . '/' . $info['basename'];
			$copyPath = $watermarkRoot . '/' . trim($info['dirname'], '/');
			$copyFile = $copyPath . '/' . $info['basename'];

			if (is_file($watermarkRoot . $file) || is_file($copyFile) || !is_file($imageFile) || !is_file($watermarkFile)) {
				return $file;
			}

			$imageSize = @getimagesize($imageFile);
			$watermarkSize = @getimagesize($watermarkFile);
			if (!$imageSize || !$watermarkSize || $imageSize[0] < $watermarkSize[0] || $imageSize[1] < $watermarkSize[1]) {
				return $file;
			}

			if (!Dir::create($copyPath)) {
				return $file;
			}

			self::denyAccess($watermarkRoot);

			require_once BASEPATH . '/system/vendor/ImageToolbox/ImageToolbox.php';
			$watermark = new \Image_Toolbox($imageFile);
			if (!@rename($imageFile, $copyFile)) {
				return $file;
			}

			@chmod($copyFile, 0664);

			list($x, $y) = self::position($position, 10);
			$watermark->addImage($watermarkFile);
			$watermark->blend($x, $y, IMAGE_TOOLBOX_BLEND_COPY, (int) $transparency);
			if (!$watermark->save($imageFile)) {
				@rename($copyFile, $imageFile);
				unset($watermark);
				return $file;
			}

			ThumbnailStorage::removeForSource($imageFile);

			if (isset($watermark->_img['main']['type']) && (int) $watermark->_img['main']['type'] === 2) {
				self::writeIptc($imageFile);
			}

			unset($watermark);
			return $file;
		}

		public function fromTag(array $match)
		{
			return isset($match[1], $match[2], $match[3])
				? $this->apply($match[1], $match[2], $match[3])
				: '';
		}

		public static function iptcTag($record, $dataset, $value)
		{
			$length = strlen($value);
			$result = chr(0x1C) . chr($record) . chr($dataset);
			if ($length < 0x8000) {
				$result .= chr($length >> 8) . chr($length & 0xFF);
			} else {
				$result .= chr(0x80) . chr(0x04)
					. chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF)
					. chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
			}

			return $result . $value;
		}

		protected static function position($position, $margin)
		{
			$positions = array(
				'top' => array('center -', 'top +' . $margin),
				'topcenter' => array('center -', 'top +' . $margin),
				'topleft' => array('left +' . $margin, 'top +' . $margin),
				'topright' => array('right -' . $margin, 'top +' . $margin),
				'center' => array('center -', 'center -'),
				'centerleft' => array('left +' . $margin, 'center -'),
				'centerright' => array('right -' . $margin, 'center -'),
				'bottom' => array('center -', 'bottom -' . $margin),
				'bottomcenter' => array('center -', 'bottom -' . $margin),
				'bottomleft' => array('left +' . $margin, 'bottom -' . $margin),
				'bottomright' => array('right -' . $margin, 'bottom -' . $margin),
				'repeat' => array('repeat ', 'repeat '),
			);
			return isset($positions[$position]) ? $positions[$position] : $positions['center'];
		}

		protected static function writeIptc($imageFile)
		{
			$imageInfo = array();
			@getimagesize($imageFile, $imageInfo);
			if (isset($imageInfo['APP13']) || !function_exists('iptcembed')) {
				return;
			}

			$siteName = (string) PublicSettings::get('site_name', '');
			$host = isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : '';
			$values = array(
				'2#120' => iconv('UTF-8', 'WINDOWS-1251', $siteName),
				'2#116' => 'http://' . $host,
			);
			$data = '';
			foreach ($values as $tag => $value) {
				$data .= self::iptcTag(2, substr($tag, 2), $value);
			}

			$content = @iptcembed($data, $imageFile);
			if ($content !== false) {
				File::putAtomic($imageFile, $content);
			}
		}

		protected static function denyAccess($directory)
		{
			if (!is_dir($directory)) {
				Dir::create($directory);
			}

			RuntimeDirectoryGuard::protect(rtrim($directory, '/'));
		}
	}
