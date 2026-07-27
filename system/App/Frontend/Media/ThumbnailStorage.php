<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/ThumbnailStorage.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Lock;
	use App\Helpers\File;

	/** Owns generated-thumbnail cleanup and lazy format-version invalidation. */
	class ThumbnailStorage
	{
		const FORMAT_VERSION = 2;
		const MARKER = '.ave-thumbnails';

		protected static $currentDirectories = array();

		public static function ensureCurrent($source)
		{
			$directory = self::directoryForSource($source);
			if ($directory === null || !is_dir($directory)) {
				return;
			}

			$signature = self::signature();
			$cacheKey = $directory . '|' . $signature;
			if (isset(self::$currentDirectories[$cacheKey])) {
				return;
			}

			$marker = $directory . DIRECTORY_SEPARATOR . self::MARKER;
			if (is_file($marker) && trim((string) File::getContent($marker)) === $signature) {
				self::$currentDirectories[$cacheKey] = true;
				return;
			}

			$refresh = function () use ($directory, $marker, $signature) {
				if (is_file($marker) && trim((string) File::getContent($marker)) === $signature) {
					return;
				}

				self::clearGenerated($directory);
				File::putAtomic($marker, $signature . "\n");
			};
			try {
				Lock::run('thumbnail-directory:' . $directory, $refresh);
			} catch (\RuntimeException $e) {
				$refresh();
			}

			self::$currentDirectories[$cacheKey] = true;
		}

		public static function removeForSource($source)
		{
			$directory = self::directoryForSource($source);
			if ($directory === null || !is_dir($directory)) {
				return 0;
			}

			$base = preg_quote(pathinfo($source, PATHINFO_FILENAME), '/');
			$ext = preg_quote(strtolower(pathinfo($source, PATHINFO_EXTENSION)), '/');
			return self::clearGenerated($directory, '/^' . $base . '-[rcfts]\d+x\d+r?\.' . $ext . '$/i');
		}

		protected static function clearGenerated($directory, $pattern = '/-[rcfts]\d+x\d+r?\.(?:jpe?g|png|gif|webp)$/i')
		{
			$deleted = 0;
			foreach (scandir($directory) ?: array() as $entry) {
				if (!preg_match($pattern, $entry)) {
					continue;
				}

				$file = $directory . DIRECTORY_SEPARATOR . $entry;
				if (is_file($file) && File::delete($file)) {
					$deleted++;
				}
			}

			return $deleted;
		}

		protected static function directoryForSource($source)
		{
			$source = str_replace('\\', '/', (string) $source);
			$root = rtrim(str_replace('\\', '/', BASEPATH), '/') . '/' . trim((string) UPLOAD_DIR, '/');
			$absolute = strpos($source, rtrim(str_replace('\\', '/', BASEPATH), '/') . '/') === 0
				? $source
				: rtrim(str_replace('\\', '/', BASEPATH), '/') . '/' . ltrim($source, '/');
			if (strpos($absolute, $root . '/') !== 0 || strpos($absolute, '/../') !== false) {
				return null;
			}

			return dirname($absolute) . DIRECTORY_SEPARATOR . ThumbnailUrl::directoryName();
		}

		protected static function signature()
		{
			$quality = defined('JPG_QUALITY') ? max(40, min(100, (int) JPG_QUALITY)) : 90;
			$progressive = defined('JPG_PROGRESSIVE') && JPG_PROGRESSIVE ? 1 : 0;
			return 'v' . self::FORMAT_VERSION . ':jpeg-' . $quality . ':progressive-' . $progressive;
		}
	}
