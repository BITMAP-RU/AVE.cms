<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/DocumentMediaFieldTrait.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Shared upload settings and structural URL traversal for media field types. */
	trait DocumentMediaFieldTrait
	{
		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'upload_dir',
					'type' => 'text',
					'label' => 'Папка файлов документа',
					'hint' => 'Например: articles/doc_%id. Доступны %id, %rubric_id, %rubric_alias, %field_id, %field_alias, %Y, %m и %d. Пусто: documents/%id/%field_alias.',
				),
			);
		}

		public function mediaPaths($value)
		{
			$paths = array();
			$this->walkMediaValue($value, function ($path) use (&$paths) {
				$path = trim((string) $path);
				if ($path !== '') {
					$paths[] = $path;
				}

				return $path;
			});
			return array_values(array_unique($paths));
		}

		public function replaceMediaPaths($value, array $replacements)
		{
			return $this->walkMediaValue($value, function ($path) use ($replacements) {
				$path = trim((string) $path);
				return array_key_exists($path, $replacements) ? (string) $replacements[$path] : $path;
			});
		}

		protected function walkMediaValue($value, $mapper, $key = '')
		{
			if (is_array($value)) {
				foreach ($value as $childKey => $child) {
					$value[$childKey] = $this->walkMediaValue($child, $mapper, (string) $childKey);
				}

				return $value;
			}

			if (in_array((string) $key, array('url', 'img', 'path', 'src'), true) && is_scalar($value)) {
				return call_user_func($mapper, (string) $value);
			}

			return $value;
		}
	}
