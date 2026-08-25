<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/MediaFieldValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	/**
	 * Парсер legacy media-значений AVE.
	 *
	 * Форматы:
	 * - image_single/download: /uploads/file.jpg|description
	 * - image_multi: serialize(array('/uploads/a.jpg|description', ...))
	 * - image_mega: serialize(array('/uploads/a.jpg|title|description|link', ...))
	 */
	class MediaFieldValue
	{
		/** Returns the first media URL from native JSON or any supported legacy value. */
		public static function firstImageUrl($value)
		{
			$images = self::imageMega($value);
			if (!empty($images[0]['url'])) {
				return (string) $images[0]['url'];
			}

			$image = self::imageSingle($value);
			return isset($image['url']) ? (string) $image['url'] : '';
		}

		public static function imageSingle($value)
		{
			if (is_array($value)) {
				return array(
					'url' => self::mediaPath(self::firstNonEmpty($value, array('url', 'img', 'path', 'src', 0))),
					'description' => (string) self::firstNonEmpty($value, array('description', 'descr', 'title', 1)),
				);
			}

			$json = self::jsonValue($value);
			if ($json !== null) {
				return self::imageSingle($json);
			}

			$parts = self::splitPipe($value);
			return array(
				'url' => self::mediaPath(isset($parts[0]) ? $parts[0] : ''),
				'description' => self::joinTail($parts, 1),
			);
		}

		public static function download($value)
		{
			if (is_array($value)) {
				return array(
					'url' => self::mediaPath(self::firstNonEmpty($value, array('url', 'file', 'path', 'src', 0))),
					'title' => (string) self::firstNonEmpty($value, array('title', 'description', 'descr', 1)),
				);
			}

			$json = self::jsonValue($value);
			if ($json !== null) {
				return self::download($json);
			}

			$parts = self::splitPipe($value);
			return array(
				'url' => self::mediaPath(isset($parts[0]) ? $parts[0] : ''),
				'title' => self::joinTail($parts, 1),
			);
		}

		public static function imageMulti($value)
		{
			$items = array();
			foreach (self::listItems($value) as $item) {
				if (is_array($item)) {
					$url = self::firstNonEmpty($item, array('url', 'img', 'path', 'src', 0));
					$description = self::firstNonEmpty($item, array('descr', 'description', 'title', 1));
				} else {
					$parts = self::splitPipe($item);
					$url = isset($parts[0]) ? $parts[0] : '';
					$description = self::joinTail($parts, 1);
				}

				$url = self::mediaPath($url);
				if ($url !== '') {
					$items[] = array('url' => $url, 'description' => (string) $description);
				}
			}

			return $items;
		}

		public static function imageMega($value)
		{
			$items = array();
			foreach (self::listItems($value) as $item) {
				if (is_array($item)) {
					$url = self::firstNonEmpty($item, array('url', 'img', 'path', 'src', 0));
					$title = self::firstNonEmpty($item, array('title', 1));
					$description = self::firstNonEmpty($item, array('description', 'descr', 2));
					$link = self::firstNonEmpty($item, array('link', 'href', 3));
				} else {
					$parts = self::splitPipe($item);
					$url = isset($parts[0]) ? $parts[0] : '';
					$title = isset($parts[1]) ? $parts[1] : '';
					$description = isset($parts[2]) ? $parts[2] : '';
					$link = isset($parts[3]) ? $parts[3] : '';
				}

				$url = self::mediaPath($url);
				if ($url !== '') {
					$items[] = array(
						'url' => $url,
						'title' => (string) $title,
						'description' => (string) $description,
						'link' => (string) $link,
					);
				}
			}

			return $items;
		}

		protected static function listItems($value)
		{
			if (is_array($value)) {
				return $value;
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array();
			}

			$data = self::jsonValue($value);
			if (is_array($data)) {
				return $data;
			}

			$data = @unserialize($value, array('allowed_classes' => false));
			if (is_array($data)) {
				return $data;
			}

			$items = self::extractMediaFragments($value);
			if (!empty($items)) {
				return $items;
			}

			return preg_split('/\r\n|\r|\n/', $value, -1, PREG_SPLIT_NO_EMPTY);
		}

		protected static function jsonValue($value)
		{
			if (!is_string($value)) {
				return null;
			}

			$value = trim($value);
			if ($value === '' || ($value[0] !== '{' && $value[0] !== '[')) {
				return null;
			}

			try {
				$data = Json::decode($value);
				return is_array($data) ? $data : null;
			} catch (\RuntimeException $e) {
				return null;
			}
		}

		protected static function splitPipe($value)
		{
			$value = html_entity_decode(trim((string) $value), ENT_QUOTES, 'UTF-8');
			if ($value === '') {
				return array();
			}

			return explode('|', $value);
		}

		protected static function joinTail(array $parts, $offset)
		{
			if (count($parts) <= $offset) {
				return '';
			}

			return implode('|', array_slice($parts, $offset));
		}

		protected static function mediaPath($value)
		{
			$value = trim((string) $value);
			$value = preg_replace('/\|\|\|.*$/', '', $value);
			if ($value === '') {
				return '';
			}

			if (strpos($value, 'uploads/') === 0) {
				$value = '/' . $value;
			} elseif (preg_match('#^(articles|category|slider|exhibition|pdf-cat)/#', $value)) {
				$value = '/uploads/' . $value;
			}

			return $value;
		}

		protected static function extractMediaFragments($value)
		{
			$items = array();
			preg_match_all(
				'#((?:/uploads/|uploads/|articles/|category/|slider/|exhibition/|pdf-cat/)[^";\r\n]+?\.(?:jpe?g|png|gif|webp|svg|pdf|docx?|xlsx?|zip)(?:\|[^";\r\n]*)?)#iu',
				(string) $value,
				$matches
			);

			foreach ($matches[1] as $match) {
				$match = trim($match);
				if ($match !== '') {
					$items[] = $match;
				}
			}

			return array_values(array_unique($items));
		}

		protected static function firstNonEmpty(array $item, array $keys)
		{
			foreach ($keys as $key) {
				if (isset($item[$key]) && $item[$key] !== '') {
					return $item[$key];
				}
			}

			return '';
		}
	}
