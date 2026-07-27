<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/ThumbnailUrl.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Dir;
	use App\Helpers\File;
	use App\Common\OutboundHttpClient;

	/** Builds deterministic URLs for lazily generated thumbnails. */
	class ThumbnailUrl
	{
		protected static $sourceLinks = array();
		protected static $urls = array();

		public static function make(array $params)
		{
			if (empty($params['link'])) {
				return false;
			}

			$link = self::sourceLink((string) $params['link']);
			if ($link === false) {
				return false;
			}

			$size = isset($params['size']) ? (string) $params['size'] : 't128x128';
			if (!ThumbnailPresetPolicy::allowed($size)) {
				return false;
			}

			$cacheKey = $link . '|' . $size;
			if (array_key_exists($cacheKey, self::$urls)) {
				return self::$urls[$cacheKey];
			}

			$parts = explode('.', basename($link));
			$count = count($parts);
			if ($count < 2) {
				return false;
			}

			$parts[$count - 2] .= '-' . $size;
			$thumbnail = rtrim(str_replace('\\', '/', dirname($link)), '/') . '/' . self::directoryName() . '/' . implode('.', $parts);
			ThumbnailStorage::ensureCurrent($link);
			self::discardStaleThumbnail($link, $thumbnail);
			self::$urls[$cacheKey] = $thumbnail;
			return self::$urls[$cacheKey];
		}

		public static function fromTag(array $match)
		{
			if (!isset($match[1])) {
				return '';
			}

			if (!isset($match[2]) || trim((string) $match[2]) === '') {
				return self::placeholder($match[1]);
			}

			$url = self::make(array('size' => $match[1], 'link' => $match[2]));
			return $url === false ? self::placeholder($match[1]) : (string) $url;
		}

		protected static function placeholder($size)
		{
			$url = self::make(array('size' => (string) $size, 'link' => '/uploads/images/noimage.png'));
			return $url === false ? '' : (string) $url;
		}

		public static function validSize($size)
		{
			return ThumbnailPresetPolicy::valid($size);
		}

		public static function directoryName()
		{
			$directory = defined('THUMBNAIL_DIR') ? trim((string) THUMBNAIL_DIR, '/\\') : 'th';
			return $directory === '' ? 'th' : basename($directory);
		}

		protected static function sourceLink($source)
		{
			$source = html_entity_decode(trim((string) $source), ENT_QUOTES, 'UTF-8');
			if (array_key_exists($source, self::$sourceLinks)) {
				return self::$sourceLinks[$source];
			}

			if ($source === '' || strpos($source, "\0") !== false) {
				self::$sourceLinks[$source] = false;
				return false;
			}

			$remote = ltrim($source, '/');
			if (preg_match('#^https?://#i', $remote)) {
				self::$sourceLinks[$source] = self::remoteSource($remote);
				return self::$sourceLinks[$source];
			}

			$path = parse_url($source, PHP_URL_PATH);
			$path = '/' . ltrim(str_replace('\\', '/', rawurldecode((string) $path)), '/');
			if (strpos($path, '/../') !== false || substr($path, -3) === '/..') {
				self::$sourceLinks[$source] = false;
				return false;
			}

			$uploadRoot = '/' . trim((string) UPLOAD_DIR, '/') . '/';
			if (strpos($path, $uploadRoot) !== 0 || !is_file(rtrim(BASEPATH, '/') . $path)) {
				self::$sourceLinks[$source] = false;
				return false;
			}

			self::$sourceLinks[$source] = $path;
			return self::$sourceLinks[$source];
		}

		protected static function remoteSource($url)
		{
			try {
				$target = OutboundHttpClient::inspectUrl($url, array(
					'allowed_schemes' => array('http', 'https'),
					'allowed_ports' => array(80, 443),
				));
				$url = $target['url'];
			} catch (\Throwable $e) {
				return false;
			}

			$hash = md5($url);
			$relativeDir = '/' . trim((string) UPLOAD_DIR, '/') . '/ext/' . substr($hash, 0, 4);
			$absoluteDir = rtrim(BASEPATH, '/') . $relativeDir;
			if (!Dir::create($absoluteDir)) {
				return false;
			}

			$link = $relativeDir . '/' . $hash . '.jpg';
			$absolute = rtrim(BASEPATH, '/') . $link;
			if (!is_file($absolute) && !is_file($absolute . '.tmp') && !File::putAtomic($absolute . '.tmp', $url)) {
				return false;
			}

			return $link;
		}

		protected static function discardStaleThumbnail($source, $thumbnail)
		{
			$sourceFile = rtrim(BASEPATH, '/') . '/' . ltrim((string) $source, '/');
			$thumbnailFile = rtrim(BASEPATH, '/') . '/' . ltrim((string) $thumbnail, '/');
			if (is_file($sourceFile) && is_file($thumbnailFile)
				&& (int) filemtime($sourceFile) > (int) filemtime($thumbnailFile)) {
				File::delete($thumbnailFile);
			}
		}
	}
