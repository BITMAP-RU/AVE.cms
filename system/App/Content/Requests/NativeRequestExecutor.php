<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/NativeRequestExecutor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Session;
	use App\Content\ContentTables;
	use App\Frontend\PublicPageContext;
	use App\Frontend\PublicSettings;
	use App\Frontend\RequestSort;
	use DB;

	/** Executes a compiled request plan without stored PHP or raw SQL fragments. */
	class NativeRequestExecutor
	{
		public static function unsupportedParameter(array $params)
		{
			foreach (array('SQL_QUERY', 'USER_WHERE', 'USER_FROM', 'USER_JOIN', 'ORDER', 'SORT', 'RANDOM', 'ROWS') as $key) {
				if (isset($params[$key]) && $params[$key] !== '' && $params[$key] !== array()) {
					return $key;
				}
			}

			return null;
		}

		public static function hasRuntimeSelections($requestId)
		{
			if (defined('ACP')) {
				return false;
			}

			$postKey = 'req_' . (int) $requestId;
			if (isset($_POST[$postKey]) && is_array($_POST[$postKey]) && $_POST[$postKey]) {
				return true;
			}

			$sessionKey = 'doc_' . (int) PublicPageContext::documentId();
			return isset($_SESSION[$sessionKey][$postKey])
				&& is_array($_SESSION[$sessionKey][$postKey])
				&& $_SESSION[$sessionKey][$postKey];
		}

		public function execute($requestId, array $params = array(), array $plan = null)
		{
			$requestId = (int) $requestId;
			$plan = $plan ?: NativeRequestPlanCompiler::compile($requestId);
			if (empty($plan['eligible'])) {
				throw new \RuntimeException(implode(' ', isset($plan['reasons']) ? $plan['reasons'] : array('Запрос несовместим с Native executor.')));
			}

			if (!empty($params['REQUIRE_VERIFIED'])) {
				$verified = (string) DB::query(
					'SELECT request_native_verified_hash FROM %b WHERE Id=%i LIMIT 1',
					ContentTables::table('request'),
					$requestId
				)->getValue();
				if ($verified === '' || !hash_equals($verified, (string) $plan['source_hash'])) {
					throw new \RuntimeException('Текущая версия Native-плана не подтверждена теневым сравнением.');
				}
			}

			$unsupported = self::unsupportedParameter($params);
			if ($unsupported !== null) {
				throw new \RuntimeException('Параметр ' . $unsupported . ' требует Legacy executor.');
			}

			if (self::hasRuntimeSelections($requestId)) {
				throw new \RuntimeException('Динамический выбор посетителя требует Legacy executor.');
			}

			$where = array();
			$args = array();
			$where[] = 'a.Id!=%i';
			$args[] = 1;
			$where[] = 'a.Id!=%i';
			$args[] = defined('PAGE_NOT_FOUND_ID') ? (int) PAGE_NOT_FOUND_ID : 2;
			$where[] = 'a.rubric_id=%i';
			$args[] = (int) $plan['rubric_id'];
			$where[] = 'a.document_deleted=%s';
			$args[] = '0';
			$where[] = 'a.document_status=%s';
			$args[] = isset($params['STATUS']) ? (string) (int) $params['STATUS'] : '1';

			if (!empty($plan['options']['hide_current'])) {
				$where[] = 'a.Id!=%i';
				$args[] = (int) PublicPageContext::documentId();
			}

			if (PublicSettings::get('use_doctime')) {
				$where[] = 'a.document_published<=UNIX_TIMESTAMP()';
				$where[] = '(a.document_expire=0 OR a.document_expire>=UNIX_TIMESTAMP())';
			}

			if (isset($params['PARENT']) && (int) $params['PARENT'] > 0) {
				$where[] = 'a.document_parent=%i';
				$args[] = (int) $params['PARENT'];
			}

			$userId = null;
			if (isset($params['USER_ID'])) {
				$userId = (int) $params['USER_ID'];
			} elseif (!empty($plan['options']['only_owner'])) {
				$userId = (int) Session::get('user_id');
			}

			if ($userId !== null) {
				$where[] = 'a.document_author_id=%i';
				$args[] = $userId;
			}

			$conditionArgs = array();
			$runtimeInput = isset($params['RUNTIME_INPUT']) && is_array($params['RUNTIME_INPUT'])
				? $params['RUNTIME_INPUT']
				: $_REQUEST;
			$conditionSql = $this->groupSql($plan, (int) $plan['root_id'], $conditionArgs, array(), $runtimeInput);
			if ($conditionSql !== '') {
				$where[] = $conditionSql;
				$args = array_merge($args, $conditionArgs);
			}

			$documents = ContentTables::table('documents');
			$baseSql = ' FROM ' . $documents . ' a WHERE (' . implode(') AND (', $where) . ')';
			$countStarted = microtime(true);
			$total = (int) $this->query('SELECT COUNT(*)' . $baseSql, $args)->getValue();
			$orderSql = $this->orderSql($plan);
			$limit = isset($params['LIMIT']) && is_numeric($params['LIMIT'])
				? max(0, (int) $params['LIMIT'])
				: max(0, (int) $plan['options']['items_per_page']);
			$start = isset($params['START']) ? max(0, (int) $params['START']) : 0;
			$limitSql = $limit > 0 ? ' LIMIT ' . $start . ',' . $limit : '';
			$sql = 'SELECT STRAIGHT_JOIN a.*' . $baseSql . ' GROUP BY a.Id' . $orderSql . $limitSql;
			$rows = $this->query($sql, $args)->getAll();
			$elapsed = microtime(true) - $countStarted;

			return array(
				'rows' => is_array($rows) ? $rows : array(),
				'total' => $total,
				'query_time' => $elapsed,
				'sql' => $this->diagnosticSql($sql, $args),
				'plan' => $plan,
				'executor' => 'native',
			);
		}

		protected function groupSql(array $plan, $groupId, array &$args, array $trail, array $runtimeInput = array())
		{
			$key = (string) $groupId;
			if ($groupId === 0 || !isset($plan['groups'][$key]) || isset($trail[$groupId])) {
				return '';
			}

			$trail[$groupId] = true;
			$group = $plan['groups'][$key];
			$parts = array();
			foreach (isset($group['conditions']) ? $group['conditions'] : array() as $condition) {
				$part = $this->conditionSql($condition, $args, $runtimeInput);
				if ($part !== '') {
					$parts[] = $part;
				}
			}

			foreach (isset($group['children']) ? $group['children'] : array() as $childId) {
				$child = $this->groupSql($plan, (int) $childId, $args, $trail, $runtimeInput);
				if ($child !== '') {
					$parts[] = $child;
				}
			}

			if (!$parts) {
				return '';
			}

			$join = isset($group['operator']) && $group['operator'] === 'OR' ? ' OR ' : ' AND ';
			return '(' . implode($join, $parts) . ')';
		}

		protected function conditionSql(array $condition, array &$args, array $runtimeInput = array())
		{
			$fieldTable = ContentTables::table('document_fields');
			$field = !empty($condition['numeric']) ? 'rf.field_number_value' : 'rf.field_value';
			$operator = (string) $condition['operator'];
			$comparison = '';
			$value = isset($condition['value']) ? $condition['value'] : '';
			$resolved = null;
			if (isset($condition['value_source']) && is_array($condition['value_source'])) {
				if (isset($condition['value_source']['source'])) {
					$resolved = RequestConditionValue::resolve($condition['value_source'], $runtimeInput);
					$value = $resolved !== null && isset($resolved['value']) ? $resolved['value'] : null;
				} else {
					$value = RequestRuntimeValue::resolve($condition['value_source'], $runtimeInput);
				}

				if ($value === null && $resolved === null) {
					return '';
				}
			}

			if ($resolved !== null && isset($resolved['kind']) && $resolved['kind'] === 'range') {
				$parts = array();
				$values = array();
				if ($resolved['min'] !== null) { $parts[] = $field . '>=%d'; $values[] = (float) $resolved['min']; }
				if ($resolved['max'] !== null) { $parts[] = $field . '<=%d'; $values[] = (float) $resolved['max']; }
				if (!$parts) { return ''; }
				$comparison = '(' . implode(' AND ', $parts) . ')';
				$args[] = (int) $condition['field_id'];
				foreach ($values as $item) { $args[] = $item; }
				return 'EXISTS (SELECT 1 FROM ' . $fieldTable . ' rf WHERE rf.document_id=a.Id AND rf.rubric_field_id=%i AND ' . $comparison . ')';
			}

			if ($resolved !== null && isset($resolved['kind']) && $resolved['kind'] === 'list') {
				$values = isset($resolved['values']) ? array_values($resolved['values']) : array();
				if (!$values) { return ''; }
				$args[] = (int) $condition['field_id'];
				if (isset($resolved['transform']) && $resolved['transform'] === 'pipe_member') {
					$parts = array();
					foreach ($values as $item) {
						$parts[] = $field . ' LIKE %s';
						$args[] = '%|' . (string) $item . '|%';
					}

					$comparison = '(' . implode(' OR ', $parts) . ')';
				} else {
					$comparison = $field . ' IN %ls';
					$args[] = $values;
				}

				return 'EXISTS (SELECT 1 FROM ' . $fieldTable . ' rf WHERE rf.document_id=a.Id AND rf.rubric_field_id=%i AND ' . $comparison . ')';
			}

			if ($operator === 'SEGMENT' || $operator === 'INTERVAL') {
				$low = $operator === 'INTERVAL' ? '>' : '>=';
				$high = $operator === 'INTERVAL' ? '<' : '<=';
				$comparison = $field . $low . '%d AND ' . $field . $high . '%d';
				$args[] = (float) $condition['values'][0];
				$args[] = (float) $condition['values'][1];
			} elseif ($operator === 'IN=' || $operator === 'NOTIN=') {
				$comparison = $field . ($operator === 'IN=' ? ' IN %ls' : ' NOT IN %ls');
				$args[] = array_values($condition['values']);
			} else {
				$map = array(
					'==' => '=', '!=' => '!=', '>' => '>', '<' => '<', '>=' => '>=', '<=' => '<=',
					'N>' => '>', 'N<' => '<', 'N>=' => '>=', 'N<=' => '<=',
					'%%' => 'LIKE', '%' => 'LIKE', '--' => 'NOT LIKE', '!-' => 'NOT LIKE',
				);
				if ($operator === '%%' || $operator === '--') {
					$value = '%' . $value . '%';
				} elseif ($operator === '%' || $operator === '!-') {
					$value .= '%';
				}

				$comparison = $field . ' ' . $map[$operator] . ' %s';
				$args[] = (string) $value;
			}

			array_splice($args, count($args) - ($operator === 'SEGMENT' || $operator === 'INTERVAL' ? 2 : 1), 0, array((int) $condition['field_id']));
			return 'EXISTS (SELECT 1 FROM ' . $fieldTable . ' rf WHERE rf.document_id=a.Id AND rf.rubric_field_id=%i AND ' . $comparison . ')';
		}

		protected function orderSql(array $plan)
		{
			$parts = array();
			$fieldTable = ContentTables::table('document_fields');
			foreach (isset($plan['sort']) ? $plan['sort'] : array() as $sort) {
				$direction = isset($sort['direction']) && $sort['direction'] === 'DESC' ? 'DESC' : 'ASC';
				if ($sort['type'] === 'field') {
					$column = !empty($sort['numeric']) ? 'field_number_value' : 'field_value';
					$parts[] = '(SELECT sf.' . $column . ' FROM ' . $fieldTable . ' sf'
						. ' WHERE sf.document_id=a.Id AND sf.rubric_field_id=' . (int) $sort['field_id'] . ' LIMIT 1) ' . $direction;
				} elseif ($sort['type'] === 'document') {
					$parts[] = 'a.`' . str_replace('`', '', (string) $sort['column']) . '` ' . $direction;
				} elseif ($sort['type'] === 'random') {
					$parts[] = RequestSort::randomExpression($sort) . ' ASC';
					$parts[] = 'a.Id ASC';
				}
			}

			return $parts ? ' ORDER BY ' . implode(', ', $parts) : '';
		}

		protected function query($sql, array $args)
		{
			return call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args));
		}

		protected function diagnosticSql($sql, array $args)
		{
			return "-- Native executor; значения передаются параметрами\n" . $sql
				. "\n-- params: " . json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}
