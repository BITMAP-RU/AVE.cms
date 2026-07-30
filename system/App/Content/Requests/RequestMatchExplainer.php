<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestMatchExplainer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Session;
	use App\Content\ContentTables;
	use App\Frontend\PublicSettings;
	use App\Frontend\RequestRenderer;
	use DB;

	/** Explains why a document is included in or excluded from a saved request. */
	class RequestMatchExplainer
	{
		public function explain($requestId, $documentId, array $parameters = array())
		{
			$request = $this->request((int) $requestId);
			$document = $this->document((int) $documentId);
			if (!$request || !$document) {
				throw new \InvalidArgumentException(!$request ? 'Запрос не найден' : 'Документ не найден');
			}

			$parameters = $this->normalizeParameters($parameters);
			$previousRequest = $_REQUEST;
			$previousGet = $_GET;
			$_REQUEST = $parameters;
			$_GET = $parameters;

			try {
				$matched = $this->matchesExecutor((int) $request['Id'], (int) $document['Id']);
				$base = $this->baseChecks($request, $document);
				$fields = $this->fieldDefinitions((int) $request['rubric_id']);
				$values = $this->documentValues((int) $document['Id']);
				$groups = $this->conditionTree((int) $request['Id'], $request, $document, $fields, $values);

				return array(
					'matched' => $matched,
					'request' => array(
						'id' => (int) $request['Id'],
						'title' => htmlspecialchars_decode((string) $request['request_title'], ENT_QUOTES),
						'alias' => (string) $request['request_alias'],
						'rubric_id' => (int) $request['rubric_id'],
					),
					'document' => array(
						'id' => (int) $document['Id'],
						'title' => htmlspecialchars_decode((string) $document['document_title'], ENT_QUOTES),
						'alias' => (string) $document['document_alias'],
						'rubric_id' => (int) $document['rubric_id'],
					),
					'parameters' => $parameters,
					'base' => $base,
					'groups' => $groups ? array($groups) : array(),
					'notes' => array(
						'Итог всегда определяется текущим публичным executor.',
						$parameters
							? 'Условия проверены с указанными параметрами публичной страницы.'
							: 'Параметры публичной страницы не переданы.',
					),
				);
			} finally {
				$_REQUEST = $previousRequest;
				$_GET = $previousGet;
			}
		}

		protected function normalizeParameters(array $parameters)
		{
			$result = array();
			foreach ($parameters as $key => $value) {
				$key = preg_replace('/[^a-z0-9_.-]+/i', '', (string) $key);
				if ($key === '' || $this->sensitiveKey($key)) {
					continue;
				}

				if (is_array($value)) {
					$items = array();
					foreach (array_slice($value, 0, 50) as $item) {
						if (is_scalar($item) || $item === null) {
							$items[] = mb_substr((string) $item, 0, 500);
						}
					}

					$result[$key] = $items;
				} elseif (is_scalar($value) || $value === null) {
					$result[$key] = mb_substr((string) $value, 0, 500);
				}
			}

			return $result;
		}

		protected function sensitiveKey($key)
		{
			return (bool) preg_match(
				'/(?:pass|secret|token|cookie|session|authorization|credential|api[_-]?key|парол|секрет|токен|ключ[_ -]?api)/iu',
				(string) $key
			);
		}

		protected function matchesExecutor($requestId, $documentId)
		{
			$result = (new RequestRenderer())->render($requestId, array(
				'RETURN_DATA' => 1,
				'LIMIT' => 1,
				'START' => 0,
				'USER_WHERE' => 'a.Id = ' . (int) $documentId,
			));

			return is_array($result) && !empty($result['rows']);
		}

		protected function baseChecks(array $request, array $document)
		{
			$now = time();
			$notFoundId = defined('PAGE_NOT_FOUND_ID') ? (int) PAGE_NOT_FOUND_ID : 0;
			$checks = array(
				$this->check('rubric', 'Рубрика запроса', (int) $document['rubric_id'] === (int) $request['rubric_id'],
					'Документ #' . (int) $document['rubric_id'] . ', запрос #' . (int) $request['rubric_id']),
				$this->check('deleted', 'Не удалён', empty($document['document_deleted']),
					empty($document['document_deleted']) ? 'Документ доступен' : 'Документ помечен удалённым'),
				$this->check('status', 'Опубликован', (int) $document['document_status'] === 1,
					(int) $document['document_status'] === 1 ? 'Состояние: опубликован' : 'Состояние: снят с публикации'),
				$this->check('system', 'Не системная страница', (int) $document['Id'] !== 1 && ($notFoundId <= 0 || (int) $document['Id'] !== $notFoundId),
					'Главная и страница 404 исключаются из запросов'),
			);

			if (PublicSettings::get('use_doctime')) {
				$published = (int) $document['document_published'];
				$expires = (int) $document['document_expire'];
				$checks[] = $this->check('published_at', 'Срок публикации', $published <= $now && ($expires === 0 || $expires >= $now),
					'Начало: ' . ($published > 0 ? date('d.m.Y H:i', $published) : 'без ограничения')
					. '; окончание: ' . ($expires > 0 ? date('d.m.Y H:i', $expires) : 'без ограничения'));
			}

			if (!empty($request['request_only_owner'])) {
				$ownerId = (int) Session::get('user_id');
				$checks[] = $this->check('owner', 'Только документы текущего автора', (int) $document['document_author_id'] === $ownerId,
					'Автор документа: #' . (int) $document['document_author_id'] . '; текущий пользователь: #' . $ownerId);
			}

			return $checks;
		}

		protected function conditionTree($requestId, array $request, array $document, array $fields, array $values)
		{
			$groups = DB::query(
				'SELECT * FROM %b WHERE request_id=%i ORDER BY parent_id,group_position,Id',
				ContentTables::table('request_condition_groups'), $requestId
			)->getAll() ?: array();
			$conditions = DB::query(
				"SELECT * FROM %b WHERE request_id=%i AND condition_status='1' ORDER BY condition_position,Id",
				ContentTables::table('request_conditions'), $requestId
			)->getAll() ?: array();

			if (!$groups) { return array(); }

			$nodes = array();
			foreach ($groups as $group) {
				$group = (array) $group;
				$nodes[(int) $group['Id']] = array(
					'id' => (int) $group['Id'],
					'parent_id' => (int) $group['parent_id'],
					'title' => trim((string) $group['group_title']) !== '' ? (string) $group['group_title'] : 'Группа #' . (int) $group['Id'],
					'operator' => (string) $group['group_operator'] === 'OR' ? 'OR' : 'AND',
					'conditions' => array(),
					'children' => array(),
					'state' => 'empty',
				);
			}

			foreach ($conditions as $condition) {
				$condition = (array) $condition;
				$groupId = isset($nodes[(int) $condition['condition_group_id']])
					? (int) $condition['condition_group_id']
					: (int) array_key_first($nodes);
				$nodes[$groupId]['conditions'][] = $this->condition($condition, $request, $document, $fields, $values);
			}

			$rootId = 0;
			foreach ($nodes as $id => $node) {
				if ($node['parent_id'] === 0 && $rootId === 0) { $rootId = $id; }
			}

			return $rootId > 0 ? $this->buildGroup($rootId, $nodes) : array();
		}

		protected function buildGroup($groupId, array $nodes)
		{
			$group = $nodes[$groupId];
			foreach ($nodes as $childId => $node) {
				if ((int) $node['parent_id'] === (int) $groupId) {
					$group['children'][] = $this->buildGroup($childId, $nodes);
				}
			}

			$states = array();
			foreach ($group['conditions'] as $condition) {
				if ($condition['state'] !== 'skipped') { $states[] = $condition['state']; }
			}

			foreach ($group['children'] as $child) {
				if ($child['state'] !== 'empty') { $states[] = $child['state']; }
			}

			$group['state'] = $this->groupState($group['operator'], $states);

			return $group;
		}

		protected function groupState($operator, array $states)
		{
			if (!$states) { return 'empty'; }
			if ($operator === 'OR') {
				if (in_array('passed', $states, true)) { return 'passed'; }
				return in_array('unknown', $states, true) ? 'unknown' : 'failed';
			}

			if (in_array('failed', $states, true)) { return 'failed'; }
			return in_array('unknown', $states, true) ? 'unknown' : 'passed';
		}

		protected function condition(array $condition, array $request, array $document, array $fields, array $values)
		{
			$fieldId = (int) $condition['condition_field_id'];
			$field = isset($fields[$fieldId]) ? $fields[$fieldId] : array();
			$value = isset($values[$fieldId]) ? $values[$fieldId] : null;
			$actual = $value === null
				? null
				: (!empty($field['numeric']) ? $value['number'] : $value['value']);
			$expected = '';
			$error = '';
			$template = trim((string) $condition['condition_value']);
			if ($template === '') {
				return array(
					'id' => (int) $condition['Id'],
					'field_id' => $fieldId,
					'field' => isset($field['title']) ? $field['title'] : 'Поле #' . $fieldId,
					'alias' => isset($field['alias']) ? $field['alias'] : '',
					'operator' => (string) $condition['condition_compare'],
					'actual' => $actual,
					'expected' => '',
					'state' => 'skipped',
					'note' => 'Пустое условие не включается в SQL выборки.',
				);
			}

			try {
				$descriptor = RequestConditionValue::descriptor($condition);
				if ($descriptor !== null) {
					$resolved = RequestConditionValue::resolve($descriptor, $_REQUEST);
					if ($resolved === null) {
						return array(
							'id' => (int) $condition['Id'],
							'field_id' => $fieldId,
							'field' => isset($field['title']) ? $field['title'] : 'Поле #' . $fieldId,
							'alias' => isset($field['alias']) ? $field['alias'] : '',
							'operator' => (string) $condition['condition_compare'],
							'actual' => $actual,
							'expected' => RequestConditionValue::tag($descriptor['key']),
							'state' => 'skipped',
							'note' => 'Публичный параметр не передан, условие не участвует в выборке.',
						);
					}

					$expected = $this->resolvedLabel($resolved);
					$passed = $this->compareResolved((string) $condition['condition_compare'], $actual, $resolved, $value !== null);
				} else {
					$expected = $this->runtimeValue($template);
					$passed = $this->compare((string) $condition['condition_compare'], $actual, $expected, $value !== null);
				}
			} catch (\Throwable $e) {
				$passed = null;
				$error = 'Не удалось разобрать значение: ' . $e->getMessage();
			}

			return array(
				'id' => (int) $condition['Id'],
				'field_id' => $fieldId,
				'field' => isset($field['title']) ? $field['title'] : 'Поле #' . $fieldId,
				'alias' => isset($field['alias']) ? $field['alias'] : '',
				'operator' => (string) $condition['condition_compare'],
				'actual' => $actual,
				'expected' => $expected,
				'state' => $passed === null ? 'unknown' : ($passed ? 'passed' : 'failed'),
				'note' => $error !== '' ? $error : ($passed === null ? 'Условие проверено итоговым executor, но не разбирается локально.' : ''),
			);
		}

		protected function compareResolved($operator, $actual, array $resolved, $exists)
		{
			if (!$exists || $actual === null) { return false; }
			if ($resolved['kind'] === 'range') {
				$number = (float) $actual;
				return ($resolved['min'] === null || $number >= (float) $resolved['min'])
					&& ($resolved['max'] === null || $number <= (float) $resolved['max']);
			}

			if ($resolved['kind'] === 'list') {
				foreach ($resolved['values'] as $item) {
					$expected = isset($resolved['transform']) && $resolved['transform'] === 'pipe_member'
						? '|' . $item . '|'
						: $item;
					if ($this->compare(
						isset($resolved['transform']) && $resolved['transform'] === 'pipe_member' ? '%%' : '==',
						$actual,
						$expected,
						true
					)) { return true; }
				}

				return false;
			}

			return $this->compare($operator, $actual, isset($resolved['value']) ? $resolved['value'] : '', true);
		}

		protected function resolvedLabel(array $resolved)
		{
			if ($resolved['kind'] === 'range') {
				return (string) $resolved['min'] . ' … ' . (string) $resolved['max'];
			}

			if ($resolved['kind'] === 'list') { return implode(', ', $resolved['values']); }
			return isset($resolved['value']) ? (string) $resolved['value'] : '';
		}

		protected function runtimeValue($template)
		{
			if (strpos($template, '<?') !== false || strpos($template, '?>') !== false) {
				throw new \RuntimeException('PHP-выражение не исполняется повторно в инспекторе');
			}

			return trim((string) $template);
		}

		protected function compare($operator, $actual, $expected, $exists)
		{
			if (!$exists || $actual === null) { return false; }
			if ($operator === 'FRE' || $operator === 'ANY') { return null; }
			if (in_array($operator, array('N>', '>', 'N<', '<', 'N>=', '>=', 'N<=', '<='), true)) {
				$left = (float) $actual;
				$right = (float) str_replace(',', '.', (string) $expected);
				if (in_array($operator, array('N>', '>'), true)) { return $left > $right; }
				if (in_array($operator, array('N<', '<'), true)) { return $left < $right; }
				if (in_array($operator, array('N>=', '>='), true)) { return $left >= $right; }
				return $left <= $right;
			}

			if (in_array($operator, array('SEGMENT', 'INTERVAL'), true)) {
				$range = array_map('trim', explode(',', (string) $expected, 2));
				if (count($range) !== 2 || $range[0] === '' || $range[1] === '') { return null; }
				$number = (float) $actual;
				return $operator === 'SEGMENT'
					? $number >= (float) $range[0] && $number <= (float) $range[1]
					: $number > (float) $range[0] && $number < (float) $range[1];
			}

			$left = $this->lower((string) $actual);
			$right = $this->lower((string) $expected);
			if ($operator === '!=') { return $left !== $right; }
			if ($operator === '%%') { return strpos($left, $right) !== false; }
			if ($operator === '%') { return strpos($left, $right) === 0; }
			if ($operator === '--') { return strpos($left, $right) === false; }
			if ($operator === '!-') { return strpos($left, $right) !== 0; }
			if (in_array($operator, array('IN=', 'NOTIN='), true)) {
				$list = $this->listValues($expected);
				$contains = in_array($left, $list, true);
				return $operator === 'NOTIN=' ? !$contains : $contains;
			}

			return $left === $right;
		}

		protected function listValues($value)
		{
			$result = array();
			foreach (str_getcsv((string) $value, ',', "'") as $item) {
				$item = trim((string) $item, " \t\n\r\0\x0B'\"");
				if ($item !== '') { $result[] = $this->lower($item); }
			}

			return $result;
		}

		protected function lower($value)
		{
			return function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
		}

		protected function check($code, $label, $passed, $detail)
		{
			return array('code' => $code, 'label' => $label, 'state' => $passed ? 'passed' : 'failed', 'detail' => $detail);
		}

		protected function request($id)
		{
			return DB::query('SELECT * FROM %b WHERE Id=%i LIMIT 1', ContentTables::table('request'), $id)->getAssoc();
		}

		protected function document($id)
		{
			return DB::query('SELECT * FROM %b WHERE Id=%i LIMIT 1', ContentTables::table('documents'), $id)->getAssoc();
		}

		protected function fieldDefinitions($rubricId)
		{
			$result = array();
			foreach (DB::query(
				'SELECT Id,rubric_field_title,rubric_field_alias,rubric_field_numeric FROM %b WHERE rubric_id=%i',
				ContentTables::table('rubric_fields'), $rubricId
			)->getAll() ?: array() as $field) {
				$field = (array) $field;
				$result[(int) $field['Id']] = array(
					'title' => (string) $field['rubric_field_title'],
					'alias' => (string) $field['rubric_field_alias'],
					'numeric' => !empty($field['rubric_field_numeric']),
				);
			}

			return $result;
		}

		protected function documentValues($documentId)
		{
			$result = array();
			foreach (DB::query(
				'SELECT rubric_field_id,field_value,field_number_value FROM %b WHERE document_id=%i',
				ContentTables::table('document_fields'), $documentId
			)->getAll() ?: array() as $row) {
				$row = (array) $row;
				$result[(int) $row['rubric_field_id']] = array(
					'value' => (string) $row['field_value'],
					'number' => $row['field_number_value'] === null ? null : (float) $row['field_number_value'],
				);
			}

			return $result;
		}
	}
