<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/SerialFieldType.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * База для полей с сериализованным значением (legacy PHP serialize или JSON):
	 * image_multi/mega, multi_list*, multi_checkbox, multi_links, teasers.
	 * Декодирование безопасное (allowed_classes=false — без объектов).
	 */
	abstract class SerialFieldType extends AbstractFieldType
	{
		public function storage()
		{
			return 'text';
		}

		public function isMultiple()
		{
			return true;
		}

		/** Декодировать значение в массив (PHP serialize или JSON). */
		protected function decode($value)
		{
			if (is_array($value)) {
				return $value;
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array();
			}

			return FieldValueCodec::decodeStructured($value, array());
		}

		/** Кодировать сложное значение для нового хранения: массивы только JSON. */
		protected function encodeJson(array $data)
		{
			return FieldValueCodec::normalizeForStorage($data);
		}

		/** Список строк из textarea/array/legacy serialize/json. */
		protected function linesFromValue($value)
		{
			if (is_array($value)) {
				return $this->flatten($value);
			}

			$value = trim((string) $value);
			if ($value === '') {
				return array();
			}

			$decoded = $this->decode($value);
			if (!empty($decoded)) {
				return $this->flatten($decoded);
			}

			return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)), 'strlen'));
		}

		/** Плоский список строковых значений из декодированного (рекурсивно берём скаляры). */
		protected function flatten(array $data)
		{
			$out = array();
			array_walk_recursive($data, function ($v) use (&$out) {
				if (is_scalar($v) && $v !== '') {
					$out[] = (string) $v;
				}
			});
			return $out;
		}
	}
