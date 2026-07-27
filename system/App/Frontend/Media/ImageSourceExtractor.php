<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/ImageSourceExtractor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Extracts the original source path from the first image in stored HTML. */
	class ImageSourceExtractor
	{
		public static function firstOriginal($html)
		{
			$html = (string) $html;
			if (!preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/isu', $html, $source)) {
				return '';
			}

			$path = html_entity_decode((string) $source[2], ENT_QUOTES, 'UTF-8');
			if (preg_match('@/index\.php\?.*?[?&]thumb=([^&]+)@i', $path, $match)) {
				return rawurldecode((string) $match[1]);
			}

			$thumbnailDirectory = defined('THUMBNAIL_DIR') ? preg_quote((string) THUMBNAIL_DIR, '/') : 'thumbnails';
			if (preg_match('/(.+)' . $thumbnailDirectory . '\/(.+)-.[0-9]+x[0-9]+(\..+)/u', $path, $match)) {
				return $match[1] . $match[2] . $match[3];
			}

			return $path;
		}
	}
