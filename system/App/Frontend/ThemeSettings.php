<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ThemeSettings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Settings;
	use App\Common\FileCacheInvalidator;

	/** Reads settings declared by the active public theme without coupling it to Adminx. */
	class ThemeSettings
	{
		public static function group($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : ThemeAssets::currentTheme();
			$schema = ThemeAssets::manifest($theme)['settings'];
			$group = isset($schema['group']) ? $schema['group'] : array();
			$group['code'] = 'theme_' . $theme;
			$group['label'] = !empty($group['label']) ? $group['label'] : 'Публичная тема';
			$group['description'] = !empty($group['description']) ? $group['description'] : 'Параметры активной темы сайта.';
			$group['sort_order'] = 15;
			$group['tile_bg'] = 'var(--cyan-100)';
			$group['tile_fg'] = 'var(--cyan-600)';
			return $group;
		}

		public static function definitions($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : ThemeAssets::currentTheme();
			$schema = ThemeAssets::manifest($theme)['settings'];
			$out = array();
			foreach (isset($schema['fields']) ? $schema['fields'] : array() as $field) {
				$key = self::storageKey($field['key'], $theme);
				$field['group'] = 'theme_' . $theme;
				$field['public_shell'] = false;
				$field['theme_key'] = $field['key'];
				$out[$key] = $field;
			}

			return $out;
		}

		public static function values($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : ThemeAssets::currentTheme();
			$out = array();
			foreach (self::definitions($theme) as $storageKey => $definition) {
				$value = Settings::get($storageKey, $definition['default']);
				if ($definition['type'] === 'section_order') {
					$value = self::sectionOrder($value, $definition['options']);
				}

				$out[$definition['theme_key']] = $value;
			}

			return $out;
		}

		/** Returns manifest fields enriched with values for an admin form. */
		public static function fields($theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : ThemeAssets::currentTheme();
			$fields = array_values(self::definitions($theme));
			foreach ($fields as $index => $field) {
				$value = Settings::get(self::storageKey($field['theme_key'], $theme), $field['default']);
				$fields[$index]['value'] = $value;
				if ($field['type'] === 'section_order') {
					$fields[$index]['order_items'] = self::sectionOrder($value, $field['options']);
				}
			}

			usort($fields, function ($left, $right) {
				$leftOrder = isset($left['sort_order']) ? (int) $left['sort_order'] : 100;
				$rightOrder = isset($right['sort_order']) ? (int) $right['sort_order'] : 100;
				if ($leftOrder === $rightOrder) {
					return strcmp((string) $left['theme_key'], (string) $right['theme_key']);
				}

				return $leftOrder < $rightOrder ? -1 : 1;
			});

			return $fields;
		}

		/** Validates and stores only fields declared by the selected theme. */
		public static function save($theme, array $input)
		{
			$theme = (string) $theme;
			$definitions = self::definitions($theme);
			$errors = array();
			$values = array();

			foreach ($definitions as $storageKey => $definition) {
				$key = $definition['theme_key'];
				if (!array_key_exists($key, $input)) { continue; }
				$result = self::validate($definition, $input[$key]);
				if (!$result['success']) {
					$errors[$key] = $result['message'];
					continue;
				}

				$values[$storageKey] = $result;
			}

			if ($errors) {
				return array('success' => false, 'errors' => $errors, 'saved' => 0);
			}

			foreach ($values as $storageKey => $result) {
				Settings::set($storageKey, $result['value'], $result['store_type']);
			}

			if ($values) { FileCacheInvalidator::publicPresentation(); }
			return array('success' => true, 'errors' => array(), 'saved' => count($values));
		}

		public static function publicValues($theme = '')
		{
			$values = self::values($theme);
			foreach (array('retail_phone', 'wholesale_phone') as $key) {
				$phone = isset($values[$key]) ? (string) $values[$key] : '';
				$values[$key . '_href'] = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';
			}

			$email = isset($values['email']) ? trim((string) $values['email']) : '';
			$values['email_href'] = $email !== '' ? 'mailto:' . $email : '';
			return $values;
		}

		public static function sectionOrder($value, array $options)
		{
			if (is_string($value)) {
				$decoded = json_decode($value, true);
				$value = is_array($decoded) ? $decoded : array();
			}

			$out = array();
			$seen = array();
			foreach (is_array($value) ? $value : array() as $item) {
				$code = is_array($item) && isset($item['code']) ? (string) $item['code'] : (string) $item;
				if (!isset($options[$code]) || isset($seen[$code])) { continue; }
				$out[] = array(
					'code' => $code,
					'label' => $options[$code],
					'visible' => !is_array($item) || !array_key_exists('visible', $item) || !empty($item['visible']),
				);
				$seen[$code] = true;
			}

			foreach ($options as $code => $label) {
				if (!isset($seen[$code])) { $out[] = array('code' => $code, 'label' => $label, 'visible' => false); }
			}

			return $out;
		}

		public static function storageKey($key, $theme = '')
		{
			$theme = $theme !== '' ? (string) $theme : ThemeAssets::currentTheme();
			return 'theme_' . $theme . '_' . (string) $key;
		}

		protected static function validate(array $definition, $raw)
		{
			$type = isset($definition['type']) ? (string) $definition['type'] : 'string';
			if ($type === 'int') {
				$value = (int) $raw;
				if (isset($definition['min']) && $definition['min'] !== null && $value < (int) $definition['min']) {
					return array('success' => false, 'message' => 'Минимум: ' . (int) $definition['min']);
				}

				if (isset($definition['max']) && $definition['max'] !== null && $value > (int) $definition['max']) {
					return array('success' => false, 'message' => 'Максимум: ' . (int) $definition['max']);
				}

				return array('success' => true, 'value' => $value, 'store_type' => 'int');
			}

			if ($type === 'bool') {
				return array(
					'success' => true,
					'value' => in_array((string) $raw, array('1', 'true', 'on'), true),
					'store_type' => 'bool',
				);
			}

			if ($type === 'section_order') {
				$decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
				if (!is_array($decoded)) {
					return array('success' => false, 'message' => 'Не удалось прочитать порядок секций');
				}

				$value = self::sectionOrder($decoded, isset($definition['options']) ? $definition['options'] : array());
				return array('success' => true, 'value' => array_map(function ($item) {
					return array('code' => $item['code'], 'visible' => !empty($item['visible']));
				}, $value), 'store_type' => 'json');
			}

			if ($type === 'ids') {
				$ids = array();
				foreach (preg_split('/[\s,;]+/', trim((string) $raw)) ?: array() as $id) {
					$id = (int) $id;
					if ($id > 0) { $ids[$id] = $id; }
					if (count($ids) >= 100) { break; }
				}

				return array(
					'success' => true,
					'value' => implode(',', array_values($ids)),
					'store_type' => 'string',
				);
			}

			$value = trim((string) $raw);
			if (($type === 'select' || !empty($definition['options'])) && !empty($definition['options'])
				&& !array_key_exists($value, $definition['options'])) {
				return array('success' => false, 'message' => 'Недопустимое значение');
			}

			if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
				return array('success' => false, 'message' => 'Некорректный email');
			}

			if (isset($definition['max']) && $definition['max'] !== null
				&& mb_strlen($value, 'UTF-8') > (int) $definition['max']) {
				return array('success' => false, 'message' => 'Максимум символов: ' . (int) $definition['max']);
			}

			return array('success' => true, 'value' => $value, 'store_type' => 'string');
		}
	}
