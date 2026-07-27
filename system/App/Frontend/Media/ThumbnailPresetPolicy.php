<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/ThumbnailPresetPolicy.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\RuntimeConstantSchema;

	/** Validates and normalizes the finite set of public thumbnail presets. */
	class ThumbnailPresetPolicy
	{
		const MAX_DIMENSION = 4096;
		const MAX_PIXELS = 4000000;

		public static function defaults()
		{
			return RuntimeConstantSchema::thumbnailSizes();
		}

		public static function parse($size)
		{
			$size = strtolower(trim((string) $size));
			if (!preg_match('/^([rcfts])(\d+)x(\d+)(r?)$/', $size, $match)) {
				return null;
			}

			$width = (int) $match[2];
			$height = (int) $match[3];
			if ($width <= 0 || $height <= 0
				|| $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
				|| ($width * $height) > self::MAX_PIXELS) {
				return null;
			}

			return array(
				'size' => $match[1] . $width . 'x' . $height . $match[4],
				'method' => $match[1],
				'width' => $width,
				'height' => $height,
				'rotate' => $match[4] === 'r',
				'pixels' => $width * $height,
			);
		}

		public static function valid($size)
		{
			return self::parse($size) !== null;
		}

		public static function configured()
		{
			$raw = defined('THUMBNAIL_SIZES') ? (string) THUMBNAIL_SIZES : '';
			$sizes = self::normalizeList($raw);
			return $sizes ?: self::defaults();
		}

		public static function allowed($size)
		{
			$parsed = self::parse($size);
			return $parsed !== null && in_array($parsed['size'], self::configured(), true);
		}

		public static function normalizeList($raw)
		{
			$result = self::listResult($raw);
			return $result['valid'];
		}

		public static function invalidList($raw)
		{
			$result = self::listResult($raw);
			return $result['invalid'];
		}

		protected static function listResult($raw)
		{
			$tokens = is_array($raw) ? $raw : preg_split('/[\s,;]+/', trim((string) $raw));
			$valid = array();
			$invalid = array();

			foreach ($tokens ?: array() as $token) {
				$token = strtolower(trim((string) $token));
				if ($token === '') {
					continue;
				}

				$parsed = self::parse($token);
				if ($parsed === null) {
					$invalid[$token] = true;
					continue;
				}

				$valid[$parsed['size']] = true;
			}

			$valid = array_keys($valid);
			$invalid = array_keys($invalid);
			sort($valid, SORT_NATURAL | SORT_FLAG_CASE);
			sort($invalid, SORT_NATURAL | SORT_FLAG_CASE);
			return array('valid' => $valid, 'invalid' => $invalid);
		}
	}
