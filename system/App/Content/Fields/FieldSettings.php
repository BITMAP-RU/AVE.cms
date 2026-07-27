<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldSettings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	/**
	 * Canonical JSON settings for rubric fields.
	 *
	 * Older AVE fields stored type configuration in rubric_field_default. During
	 * migration we derive the equivalent settings without changing the default
	 * column, so existing document values keep their original meaning.
	 */
	class FieldSettings
	{
		public static function effective(array $definition)
		{
			$type = isset($definition['rubric_field_type']) ? (string) $definition['rubric_field_type'] : '';
			$default = isset($definition['rubric_field_default']) ? (string) $definition['rubric_field_default'] : '';
			$raw = isset($definition['rubric_field_settings']) ? $definition['rubric_field_settings'] : '';
			$stored = is_array($raw) ? $raw : Json::toArray((string) $raw, array());

			$effective = array_replace(self::fromLegacy($type, $default), $stored);

			// Earlier adminx builds used maxlength for single-line fields. Expose
			// it through the canonical validation key so editing preserves it.
			if ($type === 'single_line' && !array_key_exists('maxLength', $effective) && array_key_exists('maxlength', $effective)) {
				$effective['maxLength'] = $effective['maxlength'];
			}

			if ($type === 'single_line') {
				unset($effective['maxlength']);
			}

			return $effective;
		}

		public static function fromLegacy($type, $default)
		{
			$type = (string) $type;
			$default = (string) $default;

			if (in_array($type, array('dropdown', 'drop_down', 'multi_select'), true)) {
				$options = self::optionList($default);
				return empty($options) ? array() : array('options' => $options);
			}

			if ($type === 'drop_down_key') {
				$options = self::indexedOptions($default, 0);
				return empty($options) ? array() : array('options' => $options);
			}

			if (in_array($type, array('checkbox_multi', 'multi_checkbox'), true)) {
				$options = self::indexedOptions($default, 1);
				return empty($options) ? array() : array('options' => $options);
			}

			if (in_array($type, array(
				'doc_from_rub', 'doc_from_rub_all', 'doc_from_rub_check', 'doc_from_rub_search'
			), true)) {
				$rubrics = self::positiveIds($default);
				return empty($rubrics) ? array() : array('rubric' => $rubrics);
			}

			if (in_array($type, array('image_multi', 'image_mega', 'doc_files'), true)) {
				$parts = explode('|', $default);
				$uploadDir = isset($parts[0]) ? trim((string) $parts[0]) : '';
				return $uploadDir === '' ? array() : array('upload_dir' => $uploadDir);
			}

			return array();
		}

		public static function legacyDefaultIsConfiguration($type)
		{
			return in_array((string) $type, array(
				'dropdown', 'drop_down', 'drop_down_key', 'multi_select',
				'checkbox_multi', 'multi_checkbox',
				'doc_from_rub', 'doc_from_rub_all', 'doc_from_rub_check', 'doc_from_rub_search',
				'image_multi', 'image_mega', 'doc_files'
			), true);
		}

		public static function encode(array $settings)
		{
			return empty($settings)
				? ''
				: Json::encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function optionList($raw)
		{
			$out = array();
			foreach (self::splitOptions($raw) as $option) {
				$option = trim((string) $option);
				if ($option !== '') {
					$out[] = $option;
				}
			}

			return array_values(array_unique($out));
		}

		/** Preserve legacy numeric values by storing explicit value/label pairs. */
		protected static function indexedOptions($raw, $offset)
		{
			$out = array();
			foreach (self::splitOptions($raw) as $index => $label) {
				$label = trim((string) $label);
				if ($label === '') {
					continue;
				}

				$out[] = array(
					'value' => (string) ((int) $index + (int) $offset),
					'label' => $label,
				);
			}

			return $out;
		}

		protected static function splitOptions($raw)
		{
			$raw = str_replace(array("\r\n", "\r"), "\n", (string) $raw);
			return strpos($raw, "\n") !== false ? explode("\n", $raw) : explode(',', $raw);
		}

		protected static function positiveIds($raw)
		{
			$ids = array();
			foreach (preg_split('/[^0-9]+/', (string) $raw) ?: array() as $id) {
				$id = (int) $id;
				if ($id > 0) {
					$ids[] = $id;
				}
			}

			return array_values(array_unique($ids));
		}
	}
