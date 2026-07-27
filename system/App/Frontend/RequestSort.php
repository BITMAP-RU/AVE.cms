<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestSort.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Normalizes public and stored request sorting without accepting SQL expressions. */
	class RequestSort
	{
		protected static $documentColumns = array(
			'id' => 'Id',
			'Id' => 'Id',
			'rubric_id' => 'rubric_id',
			'document_parent' => 'document_parent',
			'document_alias' => 'document_alias',
			'document_short_alias' => 'document_short_alias',
			'document_title' => 'document_title',
			'document_published' => 'document_published',
			'document_expire' => 'document_expire',
			'document_changed' => 'document_changed',
			'document_author_id' => 'document_author_id',
			'document_count_print' => 'document_count_print',
			'document_count_view' => 'document_count_view',
			'document_position' => 'document_position',
		);

		public static function storedOptions()
		{
			return array(
				'' => 'Без системной сортировки',
				'document_position' => 'Позиция документа',
				'document_published' => 'Дата публикации',
				'document_changed' => 'Дата последнего изменения',
				'document_title' => 'Название документа',
				'document_count_view' => 'Количество просмотров',
				'document_count_print' => 'Количество печатей',
				'document_parent' => 'Родительский документ',
				'document_expire' => 'Дата окончания публикации',
				'document_author_id' => 'Автор',
				'document_alias' => 'Полный URL',
				'document_short_alias' => 'Короткий URL',
				'rubric_id' => 'Рубрика',
				'Id' => 'ID документа',
				'RAND()' => 'Случайный порядок',
			);
		}

		public static function fromInput($value)
		{
			$result = array();
			if (!is_string($value) || trim($value) === '') {
				return $result;
			}

			foreach (explode(';', $value) as $part) {
				$pair = explode('=', $part, 2);
				$key = trim((string) $pair[0]);
				if ($key === '' || (!ctype_digit($key) && self::documentColumn($key) === null)) {
					continue;
				}

				$result[$key] = isset($pair[1]) && trim((string) $pair[1]) === '0'
					? 'DESC'
					: 'ASC';
			}

			return $result;
		}

		public static function documentColumn($value)
		{
			$value = trim((string) $value);
			return isset(self::$documentColumns[$value])
				? self::$documentColumns[$value]
				: null;
		}

		public static function documentExpression($value)
		{
			$column = self::documentColumn($value);
			return $column === null ? null : 'a.' . $column;
		}

		public static function direction($value)
		{
			$value = strtoupper(trim((string) $value));
			return $value === 'DESC' || $value === '0' ? 'DESC' : 'ASC';
		}

		/** Optional unique final key used after the configured request sorting. */
		public static function tieBreaker($value)
		{
			$value = strtoupper(trim((string) $value));
			return in_array($value, array('ASC', 'DESC'), true) ? $value : '';
		}

		/** Validate and normalize the ordered JSON rules used by the visual editor. */
		public static function inspectRules($value, array $allowedFieldIds = array())
		{
			$errors = array();
			if (is_string($value)) {
				$value = trim($value);
				if ($value === '') { $value = array(); }
				else {
					$value = json_decode($value, true);
					if (!is_array($value)) {
						return array('rules' => array(), 'errors' => array('Не удалось прочитать порядок сортировки.'));
					}
				}
			}

			if (!is_array($value)) {
				return array('rules' => array(), 'errors' => array('Порядок сортировки должен быть списком.'));
			}

			$allowed = array();
			foreach ($allowedFieldIds as $fieldId) { $allowed[(int) $fieldId] = true; }
			$rules = array();
			$seen = array();
			foreach (array_slice(array_values($value), 0, 9) as $index => $rule) {
				if ($index >= 8) {
					$errors[] = 'Можно задать не больше восьми уровней сортировки.';
					break;
				}

				if (!is_array($rule)) {
					$errors[] = 'Правило сортировки №' . ($index + 1) . ' имеет неверный формат.';
					continue;
				}

				$source = isset($rule['source']) ? strtolower(trim((string) $rule['source'])) : '';
				$direction = isset($rule['direction']) ? strtoupper(trim((string) $rule['direction'])) : 'ASC';
				if (!in_array($direction, array('ASC', 'DESC'), true)) {
					$errors[] = 'У правила №' . ($index + 1) . ' выбрано неверное направление.';
					continue;
				}

				if ($source === 'field') {
					$fieldId = isset($rule['key']) ? (int) $rule['key'] : 0;
					if ($fieldId <= 0 || ($allowed && !isset($allowed[$fieldId]))) {
						$errors[] = 'Поле в правиле №' . ($index + 1) . ' не принадлежит выбранной рубрике.';
						continue;
					}

					$key = 'field:' . $fieldId;
					$normalized = array('source' => 'field', 'key' => $fieldId, 'direction' => $direction);
				} elseif ($source === 'document') {
					$column = self::documentColumn(isset($rule['key']) ? $rule['key'] : '');
					if ($column === null) {
						$errors[] = 'Системное поле в правиле №' . ($index + 1) . ' недоступно.';
						continue;
					}

					$key = 'document:' . $column;
					$normalized = array('source' => 'document', 'key' => $column, 'direction' => $direction);
				} elseif ($source === 'random') {
					$seed = self::randomSeed(isset($rule['seed']) ? $rule['seed'] : null);
					if (isset($rule['seed']) && trim((string) $rule['seed']) !== '' && $seed === null) {
						$errors[] = 'Ключ случайного порядка в правиле №' . ($index + 1) . ' должен быть целым числом от 1 до 2147483647.';
						continue;
					}

					$key = 'random';
					$normalized = array('source' => 'random', 'key' => 'RAND()', 'direction' => 'ASC');
					if ($seed !== null) { $normalized['seed'] = $seed; }
				} else {
					$errors[] = 'Источник правила №' . ($index + 1) . ' недоступен.';
					continue;
				}

				if (isset($seen[$key])) {
					$errors[] = 'Одно поле нельзя добавлять в сортировку дважды.';
					continue;
				}

				$seen[$key] = true;
				$rules[] = $normalized;
			}

			$hasRandom = isset($seen['random']);
			if ($hasRandom && count($rules) > 1) {
				$errors[] = 'Случайный порядок нельзя сочетать с другими уровнями.';
			}

			return array('rules' => $hasRandom && count($rules) > 1 ? array() : $rules, 'errors' => array_values(array_unique($errors)));
		}

		public static function encodeRules(array $rules)
		{
			$normalized = self::inspectRules($rules);
			return json_encode($normalized['rules'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		/** Read new rules, or reproduce the exact historical field/system/ID sequence. */
		public static function rulesForRequest($request)
		{
			$stored = self::requestValue($request, 'request_sort_rules', null);
			if (is_string($stored) && trim($stored) !== '') {
				return self::inspectRules($stored)['rules'];
			}

			$rules = array();
			$direction = self::direction(self::requestValue($request, 'request_asc_desc', 'ASC'));
			$fieldId = (int) self::requestValue($request, 'request_order_by_nat', 0);
			if ($fieldId > 0) {
				$rules[] = array('source' => 'field', 'key' => $fieldId, 'direction' => $direction);
			}

			$order = trim((string) self::requestValue($request, 'request_order_by', ''));
			if (strtoupper($order) === 'RAND()') {
				return array(array('source' => 'random', 'key' => 'RAND()', 'direction' => 'ASC'));
			}

			$column = self::documentColumn($order);
			if ($column !== null) {
				$rules[] = array('source' => 'document', 'key' => $column, 'direction' => $direction);
			}

			$tieBreaker = self::tieBreaker(self::requestValue($request, 'request_order_tiebreaker', ''));
			if ($tieBreaker !== '' && !self::containsDocumentId($rules)) {
				$rules[] = array('source' => 'document', 'key' => 'Id', 'direction' => $tieBreaker);
			}

			return $rules;
		}

		/** Keep legacy columns meaningful for old integrations while JSON remains authoritative. */
		public static function legacyStorage(array $rules)
		{
			$result = array(
				'request_order_by' => '',
				'request_order_by_nat' => 0,
				'request_asc_desc' => 'ASC',
				'request_order_tiebreaker' => '',
			);
			if (!$rules) { return $result; }

			$result['request_asc_desc'] = self::direction(isset($rules[0]['direction']) ? $rules[0]['direction'] : 'ASC');
			foreach ($rules as $index => $rule) {
				$source = isset($rule['source']) ? (string) $rule['source'] : '';
				if ($source === 'random') {
					$result['request_order_by'] = 'RAND()';
					return $result;
				}

				if ($source === 'field' && $result['request_order_by_nat'] === 0) {
					$result['request_order_by_nat'] = (int) $rule['key'];
				}

				if ($source === 'document' && (string) $rule['key'] !== 'Id' && $result['request_order_by'] === '') {
					$result['request_order_by'] = (string) $rule['key'];
				}

				if ($source === 'document' && (string) $rule['key'] === 'Id') {
					if ($index === 0) { $result['request_order_by'] = 'Id'; }
					else { $result['request_order_tiebreaker'] = self::direction($rule['direction']); }
				}
			}

			return $result;
		}

		protected static function containsDocumentId(array $rules)
		{
			foreach ($rules as $rule) {
				if (isset($rule['source'], $rule['key']) && $rule['source'] === 'document' && (string) $rule['key'] === 'Id') {
					return true;
				}
			}

			return false;
		}

		protected static function requestValue($request, $key, $default = null)
		{
			if (is_array($request) && array_key_exists($key, $request)) { return $request[$key]; }
			if (is_object($request) && isset($request->{$key})) { return $request->{$key}; }
			return $default;
		}

		public static function isStoredValueAllowed($value)
		{
			$value = trim((string) $value);
			return $value === '' || strtoupper($value) === 'RAND()' || self::documentColumn($value) !== null;
		}

		/** Optional stable key for random ordering. Empty keeps historical RAND(). */
		public static function randomSeed($value)
		{
			$value = trim((string) $value);
			if ($value === '' || !preg_match('/^[0-9]+$/', $value)) { return null; }

			$seed = (int) $value;
			return $seed >= 1 && $seed <= 2147483647 ? $seed : null;
		}

		/** Safe SQL expression shared by Legacy and Native request executors. */
		public static function randomExpression(array $rule, $alias = 'a')
		{
			$seed = self::randomSeed(isset($rule['seed']) ? $rule['seed'] : null);
			if ($seed === null) { return 'RAND()'; }

			$alias = preg_match('/^[a-z][a-z0-9_]*$/i', (string) $alias) ? (string) $alias : 'a';
			return "CRC32(CONCAT(" . $seed . ", ':', " . $alias . '.Id))';
		}
	}
