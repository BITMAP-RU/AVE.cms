<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Rubrics/FieldSetRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Declarative reusable field sets supplied by the core and installed modules. */
	class FieldSetRegistry
	{
		const DESCRIPTOR_FORMAT = 'ave.cms/field-set';
		const DESCRIPTOR_VERSION = 1;
		const DESCRIPTOR_MAX_BYTES = 262144;

		protected static $sets = array();

		public static function register($code, array $definition, $source = 'core', $available = true)
		{
			$code = self::normalizeCode($code);

			if (isset(self::$sets[$code]) && (string) self::$sets[$code]['source'] !== (string) $source) {
				throw new \RuntimeException('Код набора полей уже зарегистрирован: ' . $code);
			}

			$normalized = self::normalizeDefinition($code, $definition);
			$normalized['source'] = (string) $source;
			$normalized['available'] = (bool) $available;
			self::$sets[$code] = $normalized;
			return $normalized;
		}

		public static function registerMany($source, array $definitions, $available = true)
		{
			$registered = array();
			foreach ($definitions as $key => $definition) {
				if (!is_array($definition)) { continue; }
				$code = is_string($key) ? $key : (isset($definition['code']) ? $definition['code'] : '');
				$registered[] = self::register($code, $definition, $source, $available);
			}

			return $registered;
		}

		public static function get($code)
		{
			$code = strtolower(trim((string) $code));
			return isset(self::$sets[$code]) ? self::$sets[$code] : null;
		}

		public static function all($includeUnavailable = false)
		{
			$sets = array();
			foreach (self::$sets as $set) {
				if ($includeUnavailable || !empty($set['available'])) { $sets[] = $set; }
			}

			usort($sets, function ($left, $right) {
				$priority = (int) $left['priority'] <=> (int) $right['priority'];
				return $priority !== 0 ? $priority : strcasecmp((string) $left['title'], (string) $right['title']);
			});
			return $sets;
		}

		public static function reset()
		{
			self::$sets = array();
		}

		/** Построить переносимый JSON-дескриптор из определения набора. */
		public static function descriptor($code, array $definition, array $source = array())
		{
			$normalized = self::normalizeDefinition($code, $definition);
			$cleanSource = array();
			foreach (array('rubric_id', 'title', 'alias') as $key) {
				if (!array_key_exists($key, $source)) { continue; }
				$cleanSource[$key] = $key === 'rubric_id' ? (int) $source[$key] : mb_substr(trim((string) $source[$key]), 0, 255);
			}

			return array(
				'format' => self::DESCRIPTOR_FORMAT,
				'version' => self::DESCRIPTOR_VERSION,
				'exported_at' => gmdate('c'),
				'source' => $cleanSource,
				'field_set' => $normalized,
			);
		}

		/** Разобрать и нормализовать загруженный дескриптор. */
		public static function decodeDescriptor($json)
		{
			$json = (string) $json;
			if ($json === '') { throw new \InvalidArgumentException('Выберите непустой JSON-файл набора полей'); }
			if (strlen($json) > self::DESCRIPTOR_MAX_BYTES) { throw new \InvalidArgumentException('JSON набора полей превышает 256 КБ'); }

			$data = json_decode($json, true, 64);
			if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
				throw new \InvalidArgumentException('Некорректный JSON набора полей');
			}

			if (!isset($data['format']) || (string) $data['format'] !== self::DESCRIPTOR_FORMAT) {
				throw new \InvalidArgumentException('Файл не является набором полей AVE.cms');
			}

			if (!isset($data['version']) || (int) $data['version'] !== self::DESCRIPTOR_VERSION) {
				throw new \InvalidArgumentException('Версия набора полей не поддерживается');
			}

			if (!isset($data['field_set']) || !is_array($data['field_set'])) {
				throw new \InvalidArgumentException('В файле отсутствует описание набора полей');
			}

			$code = isset($data['field_set']['code']) ? $data['field_set']['code'] : '';
			$data['field_set'] = self::normalizeDefinition($code, $data['field_set']);
			$data['source'] = isset($data['source']) && is_array($data['source']) ? $data['source'] : array();
			return $data;
		}

		/** Стабильный отпечаток только содержимого набора, без даты экспорта. */
		public static function fingerprint(array $definition)
		{
			$code = isset($definition['code']) ? $definition['code'] : '';
			$normalized = self::normalizeDefinition($code, $definition);
			$json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($json === false) { throw new \RuntimeException('Не удалось вычислить fingerprint набора полей'); }
			return hash('sha256', $json);
		}

		public static function normalizeDefinition($code, array $definition)
		{
			$code = self::normalizeCode($code);
			$title = trim(isset($definition['title']) ? (string) $definition['title'] : '');
			if ($title === '') { throw new \InvalidArgumentException('У набора полей отсутствует название: ' . $code); }
			$groups = array();
			$aliases = array();
			$fieldCount = 0;
			foreach (isset($definition['groups']) && is_array($definition['groups']) ? $definition['groups'] : array() as $group) {
				if (!is_array($group)) { continue; }
				if (count($groups) >= 50) { throw new \InvalidArgumentException('В наборе полей допускается не более 50 групп'); }
				$groupTitle = trim(isset($group['title']) ? (string) $group['title'] : '');
				if ($groupTitle === '') { throw new \InvalidArgumentException('У группы набора отсутствует название: ' . $code); }
				$fields = array();
				foreach (isset($group['fields']) && is_array($group['fields']) ? $group['fields'] : array() as $field) {
					if (!is_array($field)) { continue; }
					$fieldCount++;
					if ($fieldCount > 500) { throw new \InvalidArgumentException('В наборе полей допускается не более 500 полей'); }
					$alias = trim(isset($field['alias']) ? (string) $field['alias'] : '');
					$type = trim(isset($field['type']) ? (string) $field['type'] : '');
					$fieldTitle = trim(isset($field['title']) ? (string) $field['title'] : '');
					if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,19}$/', $alias) || $type === '' || $fieldTitle === '') {
						throw new \InvalidArgumentException('Некорректное поле ' . ($alias !== '' ? $alias : '(без alias)') . ' в наборе ' . $code);
					}

					if (isset($aliases[strtolower($alias)])) { throw new \InvalidArgumentException('Alias поля повторяется в наборе ' . $code . ': ' . $alias); }
					$aliases[strtolower($alias)] = true;
					$width = isset($field['width']) ? (string) $field['width'] : 'full';
					if (!in_array($width, array('full', 'half', 'third', 'quarter'), true)) { $width = 'full'; }
					$settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();
					$settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					if ($settingsJson === false || strlen($settingsJson) > 65535) {
						throw new \InvalidArgumentException('Настройки поля ' . $alias . ' повреждены или слишком велики');
					}

					$fields[] = array(
						'title' => mb_substr($fieldTitle, 0, 255),
						'alias' => $alias,
						'type' => mb_substr($type, 0, 64),
						'source_field_id' => isset($field['source_field_id']) ? max(0, (int) $field['source_field_id']) : 0,
						'generated_alias' => !empty($field['generated_alias']),
						'search' => !empty($field['search']),
						'numeric' => !empty($field['numeric']),
						'default' => isset($field['default']) ? (string) $field['default'] : '',
						'width' => $width,
						'description' => isset($field['description']) ? mb_substr((string) $field['description'], 0, 4000) : '',
						'settings' => $settings,
					);
				}

				if ($fields) {
					$groups[] = array(
						'title' => mb_substr($groupTitle, 0, 255),
						'description' => isset($group['description']) ? mb_substr((string) $group['description'], 0, 2000) : '',
						'ungrouped' => !empty($group['ungrouped']),
						'fields' => $fields,
					);
				}
			}

			if (!$groups) { throw new \InvalidArgumentException('Набор полей пуст: ' . $code); }
			return array(
				'code' => $code,
				'title' => mb_substr($title, 0, 255),
				'description' => isset($definition['description']) ? mb_substr((string) $definition['description'], 0, 4000) : '',
				'icon' => isset($definition['icon']) && preg_match('/^ti-[a-z0-9-]+$/', (string) $definition['icon']) ? (string) $definition['icon'] : 'ti-library',
				'priority' => isset($definition['priority']) ? (int) $definition['priority'] : 100,
				'source_schema_fingerprint' => isset($definition['source_schema_fingerprint']) && preg_match('/^[a-f0-9]{64}$/', (string) $definition['source_schema_fingerprint'])
					? (string) $definition['source_schema_fingerprint']
					: '',
				'groups' => $groups,
			);
		}

		protected static function normalizeCode($code)
		{
			$code = strtolower(trim((string) $code));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $code)) {
				throw new \InvalidArgumentException('Некорректный код набора полей: ' . $code);
			}

			return $code;
		}
	}
