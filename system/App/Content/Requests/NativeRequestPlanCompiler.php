<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/NativeRequestPlanCompiler.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Frontend\RequestRepository;
	use App\Frontend\RequestSort;
	use DB;

	/** Compiles legacy request settings into a deterministic data-only execution plan. */
	class NativeRequestPlanCompiler
	{
		const VERSION = 4;

		protected static $operators = array(
			'==', '!=', '%%', '%', '--', '!-', '>', '<', '>=', '<=',
			'N>', 'N<', 'N>=', 'N<=', 'SEGMENT', 'INTERVAL', 'IN=', 'NOTIN=',
		);

		public static function compile($requestId, array $overrides = array())
		{
			$requestId = (int) $requestId;
			$request = DB::query(
				'SELECT * FROM %b WHERE Id=%i LIMIT 1',
				ContentTables::table('request'),
				$requestId
			)->getAssoc();
			if (!$request) {
				return self::missingPlan($requestId);
			}

			foreach ($overrides as $key => $value) {
				if (array_key_exists($key, $request)) {
					$request[$key] = $value;
				}
			}

			$conditions = DB::query(
				'SELECT * FROM %b WHERE request_id=%i AND condition_status=%s ORDER BY condition_position ASC,Id ASC',
				ContentTables::table('request_conditions'),
				$requestId,
				'1'
			)->getAll();
			$groups = DB::query(
				'SELECT * FROM %b WHERE request_id=%i ORDER BY parent_id ASC,group_position ASC,Id ASC',
				ContentTables::table('request_condition_groups'),
				$requestId
			)->getAll();
			$fields = DB::query(
				'SELECT Id,rubric_id,rubric_field_numeric FROM %b WHERE rubric_id=%i ORDER BY Id ASC',
				ContentTables::table('rubric_fields'),
				(int) $request['rubric_id']
			)->getAll();

			$source = array(
				'compiler' => self::VERSION,
				'request' => self::requestSource($request),
				'groups' => array_values($groups ?: array()),
				'conditions' => array_values($conditions ?: array()),
				'fields' => array_values($fields ?: array()),
			);
			$plan = array(
				'version' => self::VERSION,
				'request_id' => $requestId,
				'source_hash' => hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
				'eligible' => true,
				'reasons' => array(),
				'rubric_id' => (int) $request['rubric_id'],
				'options' => self::requestOptions($request),
				'sort' => array(),
				'deterministic_order' => false,
				'runtime_sources' => array(),
				'root_id' => 0,
				'groups' => array(),
			);

			$fieldMap = array();
			foreach ($fields ?: array() as $field) {
				$field = (array) $field;
				$fieldMap[(int) $field['Id']] = !empty($field['rubric_field_numeric']);
			}

			self::compileSort($plan, $request, $fieldMap);
			$plan['deterministic_order'] = self::hasDeterministicOrder($plan['sort']);
			self::compileGroups($plan, $groups ?: array(), $conditions ?: array(), $fieldMap);
			$plan['eligible'] = !$plan['reasons'];
			return $plan;
		}

		public static function persist($requestId, array $overrides = array())
		{
			$plan = self::compile($requestId, $overrides);
			$table = ContentTables::table('request');
			$currentHash = (string) DB::query(
				'SELECT request_native_verified_hash FROM %b WHERE Id=%i LIMIT 1',
				$table,
				(int) $requestId
			)->getValue();
			$fields = array(
				'request_native_plan' => self::encode($plan),
				'request_native_audit_status' => null,
				'request_native_audit_details' => null,
			);
			if ($currentHash !== '' && !hash_equals($currentHash, (string) $plan['source_hash'])) {
				$fields['request_native_verified_hash'] = null;
			}

			DB::Update($table, $fields, 'Id=%i', (int) $requestId);
			RequestRepository::reset();
			return $plan;
		}

		public static function encode(array $plan)
		{
			return json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public static function decode($value)
		{
			$plan = is_string($value) && $value !== '' ? json_decode($value, true) : null;
			return is_array($plan)
				&& isset($plan['version'], $plan['source_hash'])
				&& (int) $plan['version'] === self::VERSION
				? $plan
				: null;
		}

		protected static function requestSource(array $request)
		{
			$keys = array(
				'rubric_id', 'request_order_by', 'request_order_by_nat', 'request_asc_desc',
				'request_order_tiebreaker', 'request_sort_rules',
				'request_hide_current', 'request_only_owner', 'request_items_per_page',
			);
			$result = array();
			foreach ($keys as $key) {
				$result[$key] = isset($request[$key]) ? $request[$key] : null;
			}

			return $result;
		}

		protected static function requestOptions(array $request)
		{
			return array(
				'hide_current' => !empty($request['request_hide_current']),
				'only_owner' => !empty($request['request_only_owner']),
				'items_per_page' => max(0, (int) $request['request_items_per_page']),
			);
		}

		protected static function compileSort(array &$plan, array $request, array $fieldMap)
		{
			foreach (RequestSort::rulesForRequest($request) as $rule) {
				$direction = RequestSort::direction(isset($rule['direction']) ? $rule['direction'] : 'ASC');
				if ($rule['source'] === 'random') {
					$seed = RequestSort::randomSeed(isset($rule['seed']) ? $rule['seed'] : null);
					if ($seed === null) {
						self::reason($plan, 'Случайная сортировка без постоянного ключа выполняется через Legacy.');
						return;
					}

					$plan['sort'][] = array('type' => 'random', 'seed' => $seed, 'direction' => 'ASC');
					return;
				}

				if ($rule['source'] === 'field') {
					$fieldId = (int) $rule['key'];
					if (!array_key_exists($fieldId, $fieldMap)) {
						self::reason($plan, 'Поле сортировки #' . $fieldId . ' не принадлежит выбранной рубрике.');
						return;
					}

					$plan['sort'][] = array(
						'type' => 'field',
						'field_id' => $fieldId,
						'numeric' => (bool) $fieldMap[$fieldId],
						'direction' => $direction,
					);
					continue;
				}

				$column = RequestSort::documentColumn(isset($rule['key']) ? $rule['key'] : '');
				if ($column === null) { continue; }
				$plan['sort'][] = array(
					'type' => 'document',
					'column' => $column,
					'direction' => $direction,
				);
			}
		}

		/** Exact order needs a unique final ID or a seeded random rule with an implicit ID tie-breaker. */
		protected static function hasDeterministicOrder(array $sort)
		{
			if (!$sort) {
				return false;
			}

			$last = end($sort);
			if (is_array($last) && isset($last['type']) && $last['type'] === 'random' && !empty($last['seed'])) {
				return true;
			}

			return is_array($last)
				&& isset($last['type'], $last['column'])
				&& $last['type'] === 'document'
				&& $last['column'] === 'Id';
		}

		protected static function compileGroups(array &$plan, array $groups, array $conditions, array $fieldMap)
		{
			if (!$groups) {
				$operator = $conditions && isset($conditions[0]['condition_join']) && $conditions[0]['condition_join'] === 'OR'
					? 'OR'
					: 'AND';
				$groups = array(array(
					'Id' => -1,
					'parent_id' => 0,
					'group_operator' => $operator,
					'group_title' => '',
				));
			}

			$roots = array();
			foreach ($groups as $group) {
				$group = (array) $group;
				$id = (int) $group['Id'];
				$parentId = isset($group['parent_id']) ? (int) $group['parent_id'] : 0;
				$plan['groups'][(string) $id] = array(
					'id' => $id,
					'parent_id' => $parentId,
					'title' => isset($group['group_title']) ? (string) $group['group_title'] : '',
					'operator' => isset($group['group_operator']) && $group['group_operator'] === 'OR' ? 'OR' : 'AND',
					'conditions' => array(),
					'children' => array(),
				);
				if ($parentId === 0) {
					$roots[] = $id;
				}
			}

			if (count($roots) !== 1) {
				self::reason($plan, 'Дерево условий должно иметь одну корневую группу.');
			}

			$plan['root_id'] = $roots ? (int) $roots[0] : 0;

			foreach ($plan['groups'] as $key => $group) {
				$parentKey = (string) $group['parent_id'];
				if ($group['parent_id'] > 0 && isset($plan['groups'][$parentKey])) {
					$plan['groups'][$parentKey]['children'][] = (int) $group['id'];
				} elseif ($group['parent_id'] > 0) {
					self::reason($plan, 'Группа #' . $group['id'] . ' ссылается на отсутствующего родителя.');
				}
			}

			foreach ($conditions as $condition) {
				$condition = (array) $condition;
				$value = trim(isset($condition['condition_value']) ? (string) $condition['condition_value'] : '');
				if ($value === '') {
					continue;
				}

				$groupId = isset($condition['condition_group_id']) ? (int) $condition['condition_group_id'] : $plan['root_id'];
				if (!isset($plan['groups'][(string) $groupId])) {
					self::reason($plan, 'Условие #' . (int) $condition['Id'] . ' ссылается на отсутствующую группу.');
					continue;
				}

				$compiled = self::compileCondition($plan, $condition, $value, $fieldMap);
				if ($compiled !== null) {
					$plan['groups'][(string) $groupId]['conditions'][] = $compiled;
				}
			}
		}

		protected static function compileCondition(array &$plan, array $condition, $value, array $fieldMap)
		{
			$id = isset($condition['Id']) ? (int) $condition['Id'] : 0;
			$fieldId = isset($condition['condition_field_id']) ? (int) $condition['condition_field_id'] : 0;
			$operator = isset($condition['condition_compare']) ? (string) $condition['condition_compare'] : '==';
			$descriptor = RequestConditionValue::descriptor($condition);
			if (!array_key_exists($fieldId, $fieldMap)) {
				self::reason($plan, 'Условие #' . $id . ': поле #' . $fieldId . ' не принадлежит выбранной рубрике.');
				return null;
			}

			if (($operator === 'FRE' && $descriptor === null) || $operator === 'ANY') {
				self::reason($plan, 'Условие #' . $id . ': оператор ' . $operator . ' выполняется только через Legacy.');
				return null;
			}

			if ($operator !== 'FRE' && !in_array($operator, self::$operators, true)) {
				self::reason($plan, 'Условие #' . $id . ': неизвестный оператор «' . $operator . '».');
				return null;
			}

			$result = array(
				'id' => $id,
				'field_id' => $fieldId,
				'numeric' => (bool) $fieldMap[$fieldId],
				'operator' => $operator,
				'value' => $value,
			);
			if ($descriptor !== null) {
				$result['value'] = null;
				$result['value_source'] = $descriptor;
				$plan['runtime_sources'][$descriptor['key']] = $descriptor;
				return $result;
			}

			if (self::isDynamicValue($value)) {
				$source = RequestRuntimeValue::parse($value);
				if ($source === null) {
					self::reason($plan, 'Условие #' . $id . ': значение содержит произвольный PHP или SQL-подстановку.');
					return null;
				}

				$result['value'] = null;
				$result['value_source'] = $source;
				$plan['runtime_sources'][$source['key']] = $source;
			}

			if ($operator === 'SEGMENT' || $operator === 'INTERVAL') {
				$range = array_map('trim', explode(',', $value, 2));
				if (count($range) !== 2 || $range[0] === '' || $range[1] === '' || !is_numeric($range[0]) || !is_numeric($range[1])) {
					self::reason($plan, 'Условие #' . $id . ': диапазон должен содержать два числа через запятую.');
					return null;
				}

				$result['values'] = array((float) $range[0], (float) $range[1]);
			}

			if ($operator === 'IN=' || $operator === 'NOTIN=') {
				$values = self::listValues($value);
				if (!$values) {
					self::reason($plan, 'Условие #' . $id . ': список значений пуст или имеет небезопасный формат.');
					return null;
				}

				$result['values'] = $values;
			}

			return $result;
		}

		protected static function listValues($value)
		{
			if (preg_match('/[();`]|\b(?:SELECT|UNION|INSERT|UPDATE|DELETE|DROP)\b/i', $value)) {
				return array();
			}

			$values = str_getcsv($value, ',', "'", '\\');
			$result = array();
			foreach ($values as $item) {
				$item = trim((string) $item);
				$item = trim($item, " \t\n\r\0\x0B\"");
				if ($item !== '') {
					$result[] = $item;
				}
			}

			return array_values(array_unique($result));
		}

		protected static function isDynamicValue($value)
		{
			return preg_match('/<\?(?:php|=)?|\?>|\$[a-z_]|(?:DB|AVE_DB)::|->|\[(?:numeric_)?field\]/i', (string) $value) === 1;
		}

		protected static function reason(array &$plan, $message)
		{
			if (!in_array($message, $plan['reasons'], true)) {
				$plan['reasons'][] = $message;
			}
		}

		protected static function missingPlan($requestId)
		{
			return array(
				'version' => self::VERSION,
				'request_id' => (int) $requestId,
				'source_hash' => '',
				'eligible' => false,
				'reasons' => array('Запрос не найден.'),
				'rubric_id' => 0,
				'options' => array(),
				'sort' => array(),
				'deterministic_order' => false,
				'runtime_sources' => array(),
				'root_id' => 0,
				'groups' => array(),
			);
		}
	}
