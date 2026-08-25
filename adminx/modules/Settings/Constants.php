<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Settings/Constants.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Settings;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;
	use App\Frontend\Media\ThumbnailPresetPolicy;
	use App\Helpers\Json;

	class Constants
	{
		public static function table()
		{
			return SystemTables::table('constants');
		}

		public static function groups()
		{
			return array(
				'_CONST_URL' => 'Формирование URL',
				'_CONST_THEMES' => 'Оформление',
				'_CONST_FOLDERS' => 'Папки',
				'_CONST_THUMBS' => 'Генерация миниатюр',
				'_CONST_WATERMARKS' => 'Водяные знаки',
				'_CONST_SESSIONS' => 'Session и Cookie',
				'_CONST_DEV' => 'Разработка',
				'_CONST_CACHE' => 'Кеширование',
				'_CONST_COMPRESSION' => 'Компрессия',
				'_CONST_MEMCACHED' => 'Memcached',
				'_CONST_REQUEST' => 'Запросы',
				'_CONST_OTHER' => 'Прочее',
				'custom' => 'Пользовательские',
			);
		}

		public static function typeLabels()
		{
			return array(
				'string' => 'Строка',
				'bool' => 'Да/нет',
				'int' => 'Целое число',
				'select' => 'Список выбора',
				'path' => 'Путь',
				'tags' => 'Теги',
			);
		}

		public static function all()
		{
			$rows = DB::query('SELECT * FROM ' . self::table() . ' ORDER BY group_code ASC, sort_order ASC, name ASC')->getAll();
			$typeLabels = self::typeLabels();
			foreach ($rows as $i => $row) {
				$rows[$i]['options_array'] = self::decodeOptions($row['options']);
				$rows[$i]['value_display'] = self::displayValue($row['value'], $row['type'], $row['name']);
				$rows[$i]['type_label'] = isset($typeLabels[$row['type']]) ? $typeLabels[$row['type']] : $row['type'];
				$rows[$i] = array_merge($rows[$i], self::auditStatus($row['name']));
			}

			$order = self::groupOrder();
			usort($rows, function ($a, $b) use ($order) {
				$ag = isset($order[$a['group_code']]) ? $order[$a['group_code']] : 999;
				$bg = isset($order[$b['group_code']]) ? $order[$b['group_code']] : 999;
				if ($ag !== $bg) return $ag - $bg;
				if ((int) $a['sort_order'] !== (int) $b['sort_order']) return (int) $a['sort_order'] - (int) $b['sort_order'];
				return strcmp($a['name'], $b['name']);
			});
			return $rows;
		}

		public static function grouped()
		{
			$labels = self::groups();
			$out = array();
			foreach (self::all() as $row) {
				$group = (string) $row['group_code'];
				if (!isset($out[$group])) {
					$out[$group] = array(
						'code' => $group,
						'label' => isset($labels[$group]) ? $labels[$group] : $group,
						'items' => array(),
					);
				}

				$out[$group]['items'][] = $row;
			}

			return $out;
		}

		public static function one($name)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE name = %s LIMIT 1', (string) $name)->getAssoc();
			if (!$row) {
				return null;
			}

			$typeLabels = self::typeLabels();
			$row['options_array'] = self::decodeOptions($row['options']);
			$row['type_label'] = isset($typeLabels[$row['type']]) ? $typeLabels[$row['type']] : $row['type'];
			return $row;
		}

		public static function save(array $input)
		{
			$name = strtoupper(trim((string) (isset($input['name']) ? $input['name'] : '')));
			if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
				throw new \InvalidArgumentException('Некорректное имя константы');
			}

			$type = self::normalizeType(isset($input['type']) ? $input['type'] : 'string');
			$rawValue = isset($input['value']) ? $input['value'] : '';
			if ($name === 'THUMBNAIL_SIZES') {
				$type = 'tags';
				$invalid = ThumbnailPresetPolicy::invalidList($rawValue);
				if ($invalid) {
					throw new \InvalidArgumentException('Некорректные размеры: ' . implode(', ', $invalid));
				}

				$presets = ThumbnailPresetPolicy::normalizeList($rawValue);
				if (!$presets) {
					throw new \InvalidArgumentException('Добавьте хотя бы один разрешённый размер миниатюр');
				}

				$rawValue = implode(',', $presets);
			}

			$value = self::normalizeValue($rawValue, $type);
			$options = self::normalizeOptions(isset($input['options']) ? $input['options'] : '');

			$data = array(
				'name' => $name,
				'value' => self::storeValue($value, $type),
				'type' => $type,
				'group_code' => trim((string) (isset($input['group_code']) ? $input['group_code'] : 'custom')) ?: 'custom',
				'label' => trim((string) (isset($input['label']) ? $input['label'] : $name)),
				'description' => trim((string) (isset($input['description']) ? $input['description'] : '')),
				'options' => Json::encode($options, JSON_UNESCAPED_UNICODE),
				'is_system' => !empty($input['is_system']) ? 1 : 0,
				'sort_order' => isset($input['sort_order']) ? (int) $input['sort_order'] : 100,
				'updated_at' => date('Y-m-d H:i:s'),
			);

			DB::query(
				'INSERT INTO ' . self::table() . ' (name, value, type, group_code, label, description, options, is_system, sort_order, updated_at)
             VALUES (%s, %?, %s, %s, %s, %?, %?, %i, %i, %s)
             ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                type = VALUES(type),
                group_code = VALUES(group_code),
                label = VALUES(label),
                description = VALUES(description),
                options = VALUES(options),
                is_system = VALUES(is_system),
                sort_order = VALUES(sort_order),
                updated_at = VALUES(updated_at)',
				$data['name'],
				$data['value'],
				$data['type'],
				$data['group_code'],
				$data['label'],
				$data['description'],
				$data['options'],
				$data['is_system'],
				$data['sort_order'],
				$data['updated_at']
			);

			return $name;
		}

		public static function delete($name)
		{
			$row = self::one($name);
			if (!$row) {
				return false;
			}

			if ((int) $row['is_system'] === 1) {
				throw new \RuntimeException('Системную константу нельзя удалить');
			}

			DB::Delete(self::table(), 'name = %s', (string) $name);
			return true;
		}

		protected static function normalizeType($type)
		{
			$type = strtolower((string) $type);
			if ($type === 'integer') return 'int';
			if ($type === 'dropdown') return 'select';
			if ($type === 'folder') return 'path';
			if (!in_array($type, array('bool', 'int', 'string', 'select', 'path', 'tags'), true)) {
				return 'string';
			}

			return $type;
		}

		protected static function normalizeValue($value, $type)
		{
			if ($type === 'bool') {
				return in_array((string) $value, array('1', 'true', 'on'), true);
			}

			if ($type === 'int') {
				return (int) $value;
			}

			return (string) $value;
		}

		protected static function storeValue($value, $type)
		{
			if ($type === 'bool') return $value ? '1' : '0';
			return (string) $value;
		}

		protected static function decodeOptions($json)
		{
			return Json::toArray((string) $json);
		}

		protected static function normalizeOptions($raw)
		{
			if (is_array($raw)) {
				return array_values(array_map('strval', $raw));
			}

			$lines = preg_split('/[\\r\\n,]+/', (string) $raw);
			$out = array();
			foreach ($lines as $line) {
				$line = trim($line);
				if ($line !== '') {
					$out[] = $line;
				}
			}

			return $out;
		}

		protected static function groupOrder()
		{
			$order = array();
			$i = 0;
			foreach (array_keys(self::groups()) as $code) {
				$order[$code] = $i++;
			}

			return $order;
		}

		protected static function auditStatus($name)
		{
			$compatibility = array(
				'CACHE_DOC_FILE', 'CACHE_DOC_FULL', 'CACHE_DOC_FULL_ADMIN', 'CACHE_DOC_TPL',
				'MEMORY_LIMIT_PANIC', 'REQUEST_BREAK_WORDS', 'REQUEST_ETC',
				'REQUEST_STRIP_TAGS', 'SEND_SQL_ERROR', 'SQL_QUERY_SANITIZE',
				'USE_ENCODE_SERIALIZE', 'USE_GET_FIELDS', 'USE_STATIC_DATA',
			);
			if (in_array((string) $name, $compatibility, true)) {
				return array('usage_status' => 'compatibility', 'usage_label' => 'Совместимость', 'usage_reason' => 'Используется только переходным публичным или legacy-слоем.');
			}

			return array('usage_status' => 'active', 'usage_label' => 'Используется', 'usage_reason' => 'Есть обращения из текущего фреймворка, панели управления или публичной части.');
		}

		protected static function displayValue($value, $type, $name = '')
		{
			if ((string) $name === 'THUMBNAIL_SIZES') {
				$count = count(ThumbnailPresetPolicy::normalizeList($value));
				return $count . ' ' . self::pluralSizes($count);
			}

			if ($type === 'bool') {
				return ((string) $value === '1') ? 'Да' : 'Нет';
			}

			return (string) $value;
		}

		protected static function pluralSizes($count)
		{
			$mod100 = (int) $count % 100;
			$mod10 = (int) $count % 10;
			if ($mod100 >= 11 && $mod100 <= 14) {
				return 'размеров';
			}

			if ($mod10 === 1) {
				return 'размер';
			}

			if ($mod10 >= 2 && $mod10 <= 4) {
				return 'размера';
			}

			return 'размеров';
		}

	}
