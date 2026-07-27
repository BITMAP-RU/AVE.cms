<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/ThumbnailGateway.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\OutboundHttpClient;
	use App\Common\Lock;
	use App\Helpers\Dir;
	use App\Helpers\File;

	/** Serves and lazily generates public thumbnails from deterministic URLs. */
	class ThumbnailGateway
	{
		public static function handle()
		{
			self::loadConfiguration();
			$imageFile = self::requestedFile();
			if ($imageFile === '/inc/thumb.php') {
				self::notFound(false);
			}

			$descriptor = self::descriptor($imageFile);
			if (!$descriptor) {
				self::notFound();
			}

			ThumbnailStorage::ensureCurrent($descriptor['source']);
			if (self::isFresh($descriptor)) {
				self::sendFile($descriptor['target']);
			}

			if (!defined('HOST')) {
				\App\Common\PublicBootstrap::initializeEnvironment();
			}

			self::generate($descriptor);
		}

		protected static function loadConfiguration()
		{
			\App\Common\RuntimeConstants::load();
			\App\Common\PublicConfiguration::load();
		}

		protected static function requestedFile()
		{
			$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
			$path = parse_url($uri, PHP_URL_PATH);
			return '/' . ltrim(urldecode((string) $path), '/');
		}

		protected static function descriptor($imageFile)
		{
			$imageFile = '/' . ltrim(str_replace('\\', '/', (string) $imageFile), '/');
			if (strpos($imageFile, "\0") !== false || strpos($imageFile, '/../') !== false || substr($imageFile, -3) === '/..') {
				return null;
			}

			$uploadMarker = '/' . trim(UPLOAD_DIR, '/') . '/';
			if (strpos($imageFile, $uploadMarker) !== 0) {
				return null;
			}

			$relativeThumbDir = trim(substr(dirname($imageFile), strlen($uploadMarker)), '/');
			$thumbDirName = ThumbnailUrl::directoryName();
			if ($thumbDirName !== '' && basename($relativeThumbDir) !== $thumbDirName) {
				return null;
			}

			$thumbPath = BASEPATH . $uploadMarker . $relativeThumbDir;
			$sourcePath = $thumbDirName !== '' ? dirname($thumbPath) : $thumbPath;
			$thumbName = basename($imageFile);
			$parsed = self::parseName($thumbName);
			if (!$parsed || !self::sizeAllowed($parsed['check'])) {
				return null;
			}

			return array(
				'target' => $thumbPath . '/' . $thumbName,
				'target_dir' => $thumbPath,
				'target_name' => $thumbName,
				'source' => $sourcePath . '/' . $parsed['source'],
				'source_dir' => $sourcePath,
				'source_name' => $parsed['source'],
				'options' => $parsed,
			);
		}

		protected static function generate(array $descriptor)
		{
			$generate = function () use ($descriptor) {
				if (self::isFresh($descriptor)) {
					self::sendFile($descriptor['target']);
				}

				$sourcePath = $descriptor['source_dir'];
				$sourceName = $descriptor['source_name'];
				self::hydrateRemoteSource($descriptor['source']);
				$save = true;
				if (!is_file($descriptor['source'])) {
					self::reportMissing();
					$sourceName = 'noimage.png';
					if (!is_file($sourcePath . '/' . $sourceName)) {
						$sourcePath = rtrim(BASEPATH, '/') . '/' . trim(UPLOAD_DIR, '/') . '/images';
					}

					if (!is_file($sourcePath . '/' . $sourceName)) {
						exit;
					}

					$save = false;
				}

				defined('IMAGE_TOOLBOX_DEFAULT_JPEG_QUALITY') || define('IMAGE_TOOLBOX_DEFAULT_JPEG_QUALITY', self::jpegQuality());
				require_once BASEPATH . '/system/vendor/ImageToolbox/ImageToolbox.php';
				$thumb = new \Image_Toolbox($sourcePath . '/' . $sourceName);
				self::resize($thumb, $descriptor['options']);

				if ($save) {
					$target = self::save($thumb, $descriptor['target_dir'], $descriptor['target_name']);
					unset($thumb);
					if ($target !== false) {
						self::sendFile($target);
					}

					self::notFound();
				}

				$thumb->output();
				exit;
			};
			try {
				Lock::run('thumbnail:' . $descriptor['target'], $generate);
			} catch (\RuntimeException $e) {
				$generate();
			}
		}

		protected static function parseName($thumbName)
		{
			$parts = explode('.', (string) $thumbName);
			if (count($parts) < 2 || !in_array(strtolower(end($parts)), self::extensions(), true)) {
				return null;
			}

			$index = count($parts) - 2;
			if (!preg_match('/-(r|c|f|t|s)(\d+)x(\d+)(r)*$/i', $parts[$index], $match)) {
				return null;
			}

			$suffix = $match[0];
			$parts[$index] = substr($parts[$index], 0, -strlen($suffix));
			return array(
				'source' => implode('.', $parts),
				'method' => strtolower($match[1]),
				'width' => (int) $match[2],
				'height' => (int) $match[3],
				'rotate' => !empty($match[4]),
				'check' => ltrim($suffix, '-'),
			);
		}

		protected static function extensions()
		{
			$extensions = array('jpg', 'jpeg', 'png', 'gif');
			if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
				$extensions[] = 'webp';
			}

			return $extensions;
		}

		protected static function sizeAllowed($check)
		{
			return ThumbnailPresetPolicy::allowed((string) $check);
		}

		protected static function hydrateRemoteSource($source)
		{
			if (is_file($source) || !is_file($source . '.tmp')) {
				return;
			}

			$url = trim((string) File::getContent($source . '.tmp'));
			try {
				$response = $url !== '' ? OutboundHttpClient::get($url, array(
					'allowed_schemes' => array('http', 'https'),
					'allowed_ports' => array(80, 443),
					'connect_timeout' => 5,
					'timeout' => 25,
					'max_redirects' => 3,
					'max_bytes' => 26214400,
					'headers' => array('Accept: image/webp,image/png,image/jpeg,image/gif;q=0.9'),
				)) : null;
				$data = is_array($response) ? (string) $response['body'] : '';
				$image = $data !== '' ? @getimagesizefromstring($data) : false;
				$types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF);
				if (defined('IMAGETYPE_WEBP')) { $types[] = IMAGETYPE_WEBP; }
				if (is_array($image) && !empty($image[0]) && !empty($image[1]) && isset($image[2])
					&& (int) $image[0] <= 12000 && (int) $image[1] <= 12000
					&& (int) $image[0] * (int) $image[1] <= 50000000
					&& in_array((int) $image[2], $types, true)) {
					File::putAtomic($source, $data);
				}
			} catch (\Throwable $e) {
				// Invalid or unavailable remote sources are represented by the placeholder.
			}

			File::delete($source . '.tmp');
		}

		protected static function resize($thumb, array $options)
		{
			$width = $options['width'];
			$height = $options['height'];
			$rotate = (bool) $options['rotate'];
			switch ($options['method']) {
				case 'r': $thumb->newOutputSize($width, $height, 0, $rotate); break;
				case 'c': $thumb->newOutputSize($width, $height, 1, $rotate); break;
				case 'f': $thumb->newOutputSize($width, $height, 2, false, '#ffffff'); break;
				case 't': $thumb->newOutputSize($width, $height, 3, false); break;
				case 's': $thumb->newOutputSize($width, $height, 4, $rotate); break;
			}
		}

		protected static function save($thumb, $thumbPath, $thumbName)
		{
			if (!Dir::create($thumbPath)) {
				return false;
			}

			$target = $thumbPath . '/' . $thumbName;
			$temporary = tempnam($thumbPath, '.thumb-');
			if ($temporary === false || !$thumb->save($temporary)) {
				if ($temporary !== false) {
					File::delete($temporary);
				}

				return false;
			}

			if (!@rename($temporary, $target)) {
				File::delete($temporary);
				return false;
			}

			@chmod($target, 0664);
			if (empty($thumb->_img['main']['type']) || (int) $thumb->_img['main']['type'] !== 2 || !THUMBNAIL_IPTC) {
				return $target;
			}

			self::writeIptc($target);
			return $target;
		}

		protected static function writeIptc($target)
		{
			$info = array();
			@getimagesize($target, $info);
			if (isset($info['APP13']) || !function_exists('iptcembed')) {
				return;
			}

			try {
				$siteName = (string) \App\Frontend\PublicSettings::get('site_name', '');
				$encodedName = iconv('UTF-8', 'WINDOWS-1251//TRANSLIT', $siteName);
				$tags = array(
					'120' => $encodedName !== false ? $encodedName : $siteName,
					'116' => defined('HOST') ? (string) HOST : '',
				);
				$data = '';
				foreach ($tags as $tag => $value) {
					$data .= self::iptcTag(2, $tag, $value);
				}

				$content = @iptcembed($data, $target);
				if (is_string($content) && $content !== '') {
					File::putAtomic($target, $content);
				}
			} catch (\Throwable $e) {
				// IPTC is optional; a valid thumbnail must still be served.
			}
		}

		protected static function iptcTag($record, $dataSet, $value)
		{
			$value = (string) $value;
			$length = strlen($value);
			$tag = chr(0x1C) . chr($record) . chr($dataSet);
			if ($length < 0x8000) {
				return $tag . chr($length >> 8) . chr($length & 0xFF) . $value;
			}

			return $tag . chr(0x80) . chr(0x04)
				. chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF)
				. chr(($length >> 8) & 0xFF) . chr($length & 0xFF) . $value;
		}

		protected static function sendFile($file)
		{
			$info = @getimagesize($file);
			if (function_exists('apache_setenv')) {
				@apache_setenv('THUMBNAIL_RESPONSE', '1');
			}

			$modified = (int) @filemtime($file);
			$etag = '"' . sha1((string) $modified . ':' . (int) @filesize($file)) . '"';
			$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
			$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
			$lifetime = defined('THUMBNAIL_CACHE_LIFETIME') ? max(0, (int) THUMBNAIL_CACHE_LIFETIME) : 1209600;
			header('Cache-Control: public, max-age=' . $lifetime);
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $lifetime) . ' GMT');
			header('ETag: ' . $etag);
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
			if ($ifNoneMatch === $etag || ($ifModifiedSince !== false && $modified <= $ifModifiedSince)) {
				http_response_code(304);
				exit;
			}

			http_response_code(200);
			header('Content-Type: ' . ($info && isset($info['mime']) ? $info['mime'] : 'application/octet-stream'));
			header('Content-Length: ' . filesize($file));
			readfile($file);
			exit;
		}

		protected static function isFresh(array $descriptor)
		{
			if (!is_file($descriptor['target'])) {
				return false;
			}

			$source = $descriptor['source'];
			$marker = $source . '.tmp';
			$sourceChanged = is_file($source) ? (int) filemtime($source) : 0;
			$markerChanged = is_file($marker) ? (int) filemtime($marker) : 0;
			return (int) filemtime($descriptor['target']) >= max($sourceChanged, $markerChanged);
		}

		protected static function jpegQuality()
		{
			$quality = defined('JPG_QUALITY') ? (int) JPG_QUALITY : 90;
			return max(40, min(100, $quality));
		}

		protected static function reportMissing()
		{
			http_response_code(404);
			\App\Common\CsvLogWriter::notFound();
		}

		protected static function notFound($report = true)
		{
			if ($report) {
				self::reportMissing();
			} else {
				http_response_code(404);
			}

			echo 'No image';
			exit;
		}
	}
