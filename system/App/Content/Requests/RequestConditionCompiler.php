<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestConditionCompiler.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\FileCacheInvalidator;
	use App\Content\ContentTables;
	use App\Frontend\PublicPageContext;
	use App\Frontend\RequestRepository;
	use DB;

	/** Compiles request condition trees into the transitional runtime SQL template. */
	class RequestConditionCompiler
	{
		public static function compile($requestId, $persist = false)
		{
			$requestId = (int) $requestId;
			$request = DB::query(
				'SELECT * FROM %b WHERE Id = %i LIMIT 1',
				ContentTables::table('request'), $requestId
			)->getAssoc();
			if (!$request) {
				return array();
			}

			$conditions = self::objects(DB::query(
				'SELECT * FROM %b WHERE request_id = %i AND condition_status = %s ORDER BY condition_position ASC, Id ASC',
				ContentTables::table('request_conditions'), $requestId, '1'
			)->getAll());
			$groups = self::groups($requestId, $conditions);
			$numeric = self::numericFields((int) $request['rubric_id']);
			$snippets = array();
			$index = 0;

			foreach ($conditions as $condition) {
				$fieldId = (int) $condition->condition_field_id;
				$value = trim((string) $condition->condition_value);
				if ($value === '') {
					continue;
				}

				if (!array_key_exists($fieldId, $numeric)) {
					$numeric[$fieldId] = (bool) DB::query(
						'SELECT rubric_field_numeric FROM %b WHERE Id = %i LIMIT 1',
						ContentTables::table('rubric_fields'), $fieldId
					)->getValue();
				}

				$fieldSql = $numeric[$fieldId] ? 'rf.field_number_value' : 'rf.field_value';
				$groupId = isset($condition->condition_group_id)
					? (int) $condition->condition_group_id
					: (int) $groups[0]->Id;
				$descriptor = RequestConditionValue::descriptor($condition);
				if ($descriptor !== null && RequestConditionValue::tagKey($value) !== '') {
					$snippets[] = self::declarativeConditionSnippet(
						$requestId,
						$groupId,
						$fieldId,
						(string) $condition->condition_compare,
						$fieldSql,
						$descriptor
					);
					$index++;
					continue;
				}

				$value = str_ireplace(array('[field]', '[numeric_field]'), $fieldSql, $value);
				$snippets[] = self::conditionSnippet(
					$requestId,
					$index++,
					$groupId,
					$fieldId,
					(string) $condition->condition_compare,
					$fieldSql,
					$value
				);
			}

			$result = array();
			if ($snippets) {
				$result = array(
					'from' => '',
					'where' => '<?php $rqParts = []; $rqGroupExpr = []; ?>'
						. implode('', $snippets)
						. self::groupSnippet($groups),
				);
			}

			if ($persist) {
				DB::Update(
					ContentTables::table('request'),
					array('request_where_cond' => serialize($result)),
					'Id = %i',
					$requestId
				);
				if (class_exists('App\\Frontend\\RequestRepository', false)) {
					RequestRepository::reset();
				}

				FileCacheInvalidator::request(
					$requestId,
					isset($request['request_alias']) ? $request['request_alias'] : ''
				);
			}

			return $result;
		}

		/** Returns null for corrupted or foreign serialized payloads. */
		public static function decode($value)
		{
			if (!is_string($value) || $value === '') {
				return null;
			}

			$decoded = @unserialize($value, array('allowed_classes' => false));
			if (!is_array($decoded)) {
				return null;
			}

			$from = isset($decoded['from']) && is_string($decoded['from']) ? $decoded['from'] : '';
			$where = isset($decoded['where']) && is_string($decoded['where']) ? $decoded['where'] : '';
			return ($from !== '' || $where !== '') ? array('from' => $from, 'where' => $where) : array();
		}

		/** Applies old req_ID[field] selectors only to the current visitor. */
		public static function applyRuntimeSelections($requestId, array $compiled)
		{
			if (defined('ACP')) {
				return $compiled;
			}

			$requestId = (int) $requestId;
			$document = PublicPageContext::document();
			$sessionKey = 'doc_' . ($document ? (int) $document->Id : 0);
			$postKey = 'req_' . $requestId;

			if (isset($_POST[$postKey]) && is_array($_POST[$postKey])) {
				$_SESSION[$sessionKey][$postKey] = $_POST[$postKey];
			} elseif (isset($_SESSION[$sessionKey][$postKey]) && is_array($_SESSION[$sessionKey][$postKey])) {
				$_POST[$postKey] = $_SESSION[$sessionKey][$postKey];
			}

			if (empty($_POST[$postKey]) || !is_array($_POST[$postKey])) {
				return $compiled;
			}

			$parts = array();
			foreach ($_POST[$postKey] as $fieldId => $value) {
				$fieldId = (int) $fieldId;
				if ($fieldId <= 0 || $value === '' || !isset($_SESSION['val_' . $fieldId])) {
					continue;
				}

				$allowed = (array) $_SESSION['val_' . $fieldId];
				if (!in_array($value, $allowed)) {
					continue;
				}

				$parts[] = "EXISTS (SELECT 1 FROM %%CONTENT_PREFIX%%_document_fields rq_select
                        WHERE rq_select.document_id = a.Id
                          AND rq_select.rubric_field_id = '" . $fieldId . "'
                          AND rq_select.field_value = '" . DB::safe((string) $value) . "')";
			}

			if (!$parts) {
				return $compiled;
			}

			$selectionSql = '(' . implode(' AND ', $parts) . ')';
			$compiled['from'] = isset($compiled['from']) ? (string) $compiled['from'] : '';
			$compiled['where'] = !empty($compiled['where'])
				? '(' . $compiled['where'] . ') AND ' . $selectionSql
				: $selectionSql;
			return $compiled;
		}

		protected static function groups($requestId, array $conditions)
		{
			try {
				$groups = self::objects(DB::query(
					'SELECT * FROM %b WHERE request_id = %i ORDER BY parent_id ASC, group_position ASC, Id ASC',
					ContentTables::table('request_condition_groups'), (int) $requestId
				)->getAll());
			} catch (\Throwable $e) {
				$groups = array();
			}

			if ($groups) {
				return $groups;
			}

			$operator = $conditions && isset($conditions[0]->condition_join) && $conditions[0]->condition_join === 'OR'
				? 'OR'
				: 'AND';
			return array((object) array('Id' => -1, 'parent_id' => 0, 'group_operator' => $operator));
		}

		protected static function numericFields($rubricId)
		{
			$result = array();
			foreach (DB::query(
				'SELECT Id, rubric_field_numeric FROM %b WHERE rubric_id = %i',
				ContentTables::table('rubric_fields'), (int) $rubricId
			)->getAll() as $row) {
				$row = (array) $row;
				$result[(int) $row['Id']] = !empty($row['rubric_field_numeric']);
			}

			return $result;
		}

		protected static function objects(array $rows)
		{
			foreach ($rows as $key => $row) {
				$rows[$key] = is_object($row) ? $row : (object) $row;
			}

			return $rows;
		}

		protected static function conditionSnippet($requestId, $index, $groupId, $fieldId, $type, $fieldSql, $valueTemplate)
		{
			$valueCode = var_export(' ?>' . $valueTemplate . '<?php ', true);
			$prefix = "EXISTS (SELECT 1 FROM %%CONTENT_PREFIX%%_document_fields rf WHERE rf.document_id = a.Id AND rf.rubric_field_id = '$fieldId' AND ";
			$left = $fieldSql;
			switch ($type) {
				case 'N<': case '<': $comparison = $left . " < '" . '" . $rqValue . "' . "'"; break;
				case 'N>': case '>': $comparison = $left . " > '" . '" . $rqValue . "' . "'"; break;
				case 'N<=': case '<=': $comparison = $left . " <= '" . '" . $rqValue . "' . "'"; break;
				case 'N>=': case '>=': $comparison = $left . " >= '" . '" . $rqValue . "' . "'"; break;
				case '!=': $comparison = $left . " != '" . '" . $rqValue . "' . "'"; break;
				case '%%': $comparison = $left . " LIKE '%" . '" . $rqValue . "' . "%'"; break;
				case '%': $comparison = $left . " LIKE '" . '" . $rqValue . "' . "%'"; break;
				case '--': $comparison = $left . " NOT LIKE '%" . '" . $rqValue . "' . "%'"; break;
				case '!-': $comparison = $left . " NOT LIKE '" . '" . $rqValue . "' . "%'"; break;
				case 'IN=': $comparison = $left . ' IN (' . '" . $rqValue . "' . ')'; break;
				case 'NOTIN=': $comparison = $left . ' NOT IN (' . '" . $rqValue . "' . ')'; break;
				case 'ANY': $comparison = $left . '=ANY(' . '" . $rqValue . "' . ')'; break;
				case 'FRE': $comparison = '(' . '" . $rqValue . "' . ')'; break;
				case 'SEGMENT':
				case 'INTERVAL':
					$strict = $type === 'INTERVAL';
					$low = $strict ? '>' : '>=';
					$high = $strict ? '<' : '<=';
					$body = "\$rqValue = trim(\\App\\Common\\StoredPhpRuntime::evaluate($valueCode, get_defined_vars())); \$rqRange = explode(',', \$rqValue, 2); if (count(\$rqRange) === 2 && trim(\$rqRange[0]) !== '' && trim(\$rqRange[1]) !== '') { \$rqParts[$groupId][] = \"$prefix$left $low '\" . (float)\$rqRange[0] . \"' AND $left $high '\" . (float)\$rqRange[1] . \"')\"; }";
					return self::guardedSnippet($requestId, $fieldId, $body);
				case '==':
				default: $comparison = $left . " = '" . '" . $rqValue . "' . "'"; break;
			}

			$body = "\$rqValue = trim(\\App\\Common\\StoredPhpRuntime::evaluate($valueCode, get_defined_vars())); if (\$rqValue !== '') { \$rqParts[$groupId][] = \"$prefix$comparison)\"; }";
			return self::guardedSnippet($requestId, $fieldId, $body);
		}

		protected static function declarativeConditionSnippet($requestId, $groupId, $fieldId, $type, $fieldSql, array $descriptor)
		{
			$source = var_export($descriptor, true);
			$field = var_export($fieldSql, true);
			$operator = var_export($type, true);
			$prefix = var_export(
				"EXISTS (SELECT 1 FROM %%CONTENT_PREFIX%%_document_fields rf WHERE rf.document_id = a.Id AND rf.rubric_field_id = '$fieldId' AND ",
				true
			);
			$body = '$rqResolved = \\App\\Content\\Requests\\RequestConditionValue::resolve(' . $source . ', $_REQUEST); '
				. 'if ($rqResolved !== null) { '
				. '$rqComparison = \\App\\Content\\Requests\\RequestConditionValue::legacyComparison(' . $field . ', ' . $operator . ', $rqResolved); '
				. 'if ($rqComparison !== \'\') $rqParts[' . (int) $groupId . '][] = ' . $prefix . ' . $rqComparison . ")"; }';

			return self::guardedSnippet($requestId, $fieldId, $body);
		}

		protected static function guardedSnippet($requestId, $fieldId, $body)
		{
			return "<?php if (!isset(\$_POST['req_$requestId'][$fieldId])) { $body } ?>";
		}

		protected static function groupSnippet(array $groups)
		{
			$byParent = array();
			$operators = array();
			$rootId = 0;
			foreach ($groups as $group) {
				$groupId = (int) $group->Id;
				$parentId = (int) $group->parent_id;
				$operators[$groupId] = isset($group->group_operator) && $group->group_operator === 'OR' ? 'OR' : 'AND';
				$byParent[$parentId][] = $groupId;
				if ($parentId === 0 && $rootId === 0) {
					$rootId = $groupId;
				}
			}

			$order = array();
			$visited = array();
			$walk = function ($groupId) use (&$walk, &$order, &$visited, $byParent) {
				if (isset($visited[$groupId])) { return; }
				$visited[$groupId] = true;
				foreach (isset($byParent[$groupId]) ? $byParent[$groupId] : array() as $childId) {
					$walk($childId);
				}

				$order[] = $groupId;
			};
			$walk($rootId);

			$code = '<?php ';
			foreach ($order as $groupId) {
				$code .= "\$rqItems = isset(\$rqParts[$groupId]) ? \$rqParts[$groupId] : []; ";
				foreach (isset($byParent[$groupId]) ? $byParent[$groupId] : array() as $childId) {
					$code .= "if (!empty(\$rqGroupExpr[$childId])) \$rqItems[] = \$rqGroupExpr[$childId]; ";
				}

				$join = $operators[$groupId] === 'OR' ? ' OR ' : ' AND ';
				$code .= "\$rqGroupExpr[$groupId] = \$rqItems ? '(' . implode(" . var_export($join, true) . ", \$rqItems) . ')' : ''; ";
			}

			return $code . "echo !empty(\$rqGroupExpr[$rootId]) ? \$rqGroupExpr[$rootId] : '1=1'; ?>";
		}
	}
