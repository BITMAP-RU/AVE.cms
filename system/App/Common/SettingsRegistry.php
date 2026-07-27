<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/SettingsRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Реестр описаний настроек.
	 *
	 * Settings хранит key/value. SettingsRegistry хранит metadata для UI,
	 * defaults, validation и безопасного сохранения typed values.
	 */
	class SettingsRegistry
	{
		protected static $items = array();

		public static function add($key, array $definition = array(), $module = null)
		{
			$definition['key'] = self::fullKey($key, $module);
			if ($module !== null && !isset($definition['module'])) {
				$definition['module'] = (string) $module;
			}

			$definition = self::normalize($definition);
			self::$items[$definition['key']] = $definition;
		}

		public static function addMany(array $definitions, $module = null)
		{
			foreach ($definitions as $key => $definition) {
				if (!is_array($definition)) {
					continue;
				}

				if (is_int($key)) {
					if (empty($definition['key'])) {
						continue;
					}

					self::add($definition['key'], $definition, isset($definition['module']) ? $definition['module'] : $module);
				} else {
					self::add($key, $definition, isset($definition['module']) ? $definition['module'] : $module);
				}
			}
		}

		public static function has($key)
		{
			return isset(self::$items[(string) $key]);
		}

		public static function get($key)
		{
			$key = (string) $key;
			return isset(self::$items[$key]) ? self::$items[$key] : null;
		}

		public static function all($group = null)
		{
			$items = array_values(self::$items);

			if ($group !== null && $group !== '') {
				$group = (string) $group;
				$items = array_values(array_filter($items, function ($item) use ($group) {
					return isset($item['group']) && $item['group'] === $group;
				}));
			}

			usort($items, function ($a, $b) {
				$sa = isset($a['sort_order']) ? (int) $a['sort_order'] : 100;
				$sb = isset($b['sort_order']) ? (int) $b['sort_order'] : 100;
				if ($sa === $sb) {
					return strcmp((string) $a['key'], (string) $b['key']);
				}

				return $sa < $sb ? -1 : 1;
			});

			return $items;
		}

		public static function groups()
		{
			$groups = array();
			foreach (self::$items as $item) {
				$group = isset($item['group']) ? (string) $item['group'] : 'general';
				if (!isset($groups[$group])) {
					$groups[$group] = array(
						'code' => $group,
						'label' => isset($item['group_label']) ? (string) $item['group_label'] : $group,
						'count' => 0,
					);
				}

				$groups[$group]['count']++;
			}

			return array_values($groups);
		}

		public static function defaultValue($key, $fallback = null)
		{
			$item = self::get($key);
			if (!$item || !array_key_exists('default', $item)) {
				return $fallback;
			}

			return $item['default'];
		}

		public static function value($key, $fallback = null)
		{
			return Settings::get($key, self::defaultValue($key, $fallback));
		}

		public static function save($key, $value, $actorId = null)
		{
			$item = self::get($key);
			if (!$item) {
				throw new \InvalidArgumentException('Unknown setting: ' . (string) $key);
			}

			$result = self::validate($item, $value);
			if (!$result['success']) {
				throw new \InvalidArgumentException($result['message']);
			}

			Settings::set($item['key'], $result['value'], $item['type']);

			AuditLog::record('settings.updated', array(
				'actor_id' => $actorId ? (int) $actorId : null,
				'target_type' => 'setting',
				'meta' => array(
					'key' => $item['key'],
					'module' => $item['module'],
					'type' => $item['type'],
				),
			));

			return $result['value'];
		}

		public static function validate(array $item, $value)
		{
			$type = isset($item['type']) ? (string) $item['type'] : 'string';
			$rules = isset($item['validation']) && is_array($item['validation']) ? $item['validation'] : array();

			if (!empty($rules['required']) && ($value === null || $value === '')) {
				return array('success' => false, 'message' => 'Значение обязательно');
			}

			$value = self::cast($value, $type);

			if (($type === 'int' || $type === 'float') && $value !== null && $value !== '') {
				if (isset($rules['min']) && $value < $rules['min']) {
					return array('success' => false, 'message' => 'Значение меньше минимума');
				}

				if (isset($rules['max']) && $value > $rules['max']) {
					return array('success' => false, 'message' => 'Значение больше максимума');
				}
			}

			if (!empty($item['options']) && is_array($item['options'])) {
				$allowed = array_keys($item['options']);
				if (array_values($item['options']) === $item['options']) {
					$allowed = $item['options'];
				}

				if (!in_array($value, $allowed, true)) {
					return array('success' => false, 'message' => 'Недопустимое значение');
				}
			}

			return array('success' => true, 'value' => $value, 'message' => '');
		}

		protected static function normalize(array $definition)
		{
			$definition = array_merge(array(
				'key' => '',
				'label' => '',
				'type' => 'string',
				'default' => '',
				'group' => 'general',
				'group_label' => '',
				'module' => '',
				'description' => '',
				'options' => array(),
				'validation' => array(),
				'visibility' => 'public',
				'sensitive' => false,
				'sort_order' => 100,
			), $definition);

			$definition['key'] = trim((string) $definition['key']);
			$definition['type'] = self::normalizeType($definition['type']);
			$definition['default'] = self::cast($definition['default'], $definition['type']);
			$definition['sensitive'] = !empty($definition['sensitive']);

			return $definition;
		}

		protected static function fullKey($key, $module = null)
		{
			$key = trim((string) $key);
			$module = trim((string) $module);

			if ($module !== '' && strpos($key, $module . '.') !== 0) {
				return $module . '.' . $key;
			}

			return $key;
		}

		protected static function normalizeType($type)
		{
			$type = strtolower(trim((string) $type));
			$allowed = array('string', 'text', 'bool', 'int', 'float', 'select', 'json', 'code', 'html', 'image', 'file');
			return in_array($type, $allowed, true) ? $type : 'string';
		}

		protected static function cast($value, $type)
		{
			switch ($type) {
				case 'bool':
					return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
				case 'int':
					return (int) $value;
				case 'float':
					return (float) $value;
				case 'json':
					if (is_array($value)) {
						return $value;
					}

					$data = json_decode((string) $value, true);
					return is_array($data) ? $data : array();
				default:
					return (string) $value;
			}
		}
	}
