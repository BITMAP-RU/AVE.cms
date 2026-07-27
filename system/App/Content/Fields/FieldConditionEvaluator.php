<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldConditionEvaluator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Evaluates conditional visibility, required, locked and option state of document fields. */
	class FieldConditionEvaluator
	{
		const MAX_DEPTH = 4;
		const MAX_RULES = 32;

		protected static $operators = array(
			'empty', 'not_empty', 'equals', 'not_equals', 'contains', 'not_contains',
			'greater', 'greater_or_equal', 'less', 'less_or_equal', 'in', 'not_in',
		);

		protected static $valueActionTypes = array(
			'single_line', 'single_line_numeric', 'single_line_numeric_two', 'single_line_numeric_three',
			'number', 'drop_down', 'drop_down_key', 'choice', 'checkbox', 'date', 'date_time', 'color',
		);

		public static function condition(array $field)
		{
			$settings = FieldSettings::effective($field);
			return isset($settings['form_condition']) && is_array($settings['form_condition'])
				? $settings['form_condition']
				: array();
		}

		/** Returns the normalized condition stored in rubric field-group settings. */
		public static function groupCondition(array $field)
		{
			$settings = array();
			if (isset($field['group_settings']) && is_array($field['group_settings'])) {
				$settings = $field['group_settings'];
			} elseif (!empty($field['group_settings'])) {
				$decoded = json_decode((string) $field['group_settings'], true);
				if (is_array($decoded)) { $settings = $decoded; }
			}

			return isset($settings['form_condition']) && is_array($settings['form_condition'])
				? $settings['form_condition']
				: array();
		}

		public static function normalize($condition, array $fields = array(), $currentFieldId = 0)
		{
			if (!is_array($condition) || empty($condition) || (array_key_exists('enabled', $condition) && empty($condition['enabled']))) {
				return array();
			}

			$references = self::references($fields);
			$ruleCount = 0;
			$tree = isset($condition['tree']) && is_array($condition['tree'])
				? $condition['tree']
				: array(
					'operator' => isset($condition['operator']) ? $condition['operator'] : 'and',
					'items' => isset($condition['items']) ? $condition['items'] : array(),
				);
			$tree = self::normalizeGroup($tree, $references, (int) $currentFieldId, 1, $ruleCount);
			if (empty($tree['items'])) { return array(); }

			$mode = isset($condition['mode']) ? (string) $condition['mode'] : 'show';
			if (!in_array($mode, array('show', 'hide', 'lock'), true)) { $mode = 'show'; }

			$normalized = array(
				'version' => 1,
				'mode' => $mode,
				'required' => !empty($condition['required']),
				'tree' => $tree,
			);
			$allowedValues = self::normalizeAllowedValues(isset($condition['allowed_values']) ? $condition['allowed_values'] : array());
			$optionValues = self::fieldOptionValues($fields, (int) $currentFieldId);
			if (empty($optionValues)) {
				$allowedValues = array();
			} else {
				$allowedValues = array_values(array_intersect($allowedValues, $optionValues));
			}

			if (!empty($allowedValues)) { $normalized['allowed_values'] = $allowedValues; }
			$field = self::fieldById($fields, (int) $currentFieldId);
			$valueAction = isset($condition['value_action']) ? (string) $condition['value_action'] : '';
			if (self::supportsValueAction($field) && in_array($valueAction, array('set', 'clear'), true)) {
				$normalized['value_action'] = $valueAction;
				if ($valueAction === 'set') {
					$actionValue = self::normalizeActionValue(isset($condition['action_value']) ? $condition['action_value'] : '');
					if (!empty($optionValues) && !in_array($actionValue, $optionValues, true)) {
						throw new \InvalidArgumentException('Значение автодействия отсутствует в вариантах поля');
					}

					$normalized['action_value'] = $actionValue;
				}
			}

			return $normalized;
		}

		public static function supportsValueAction(array $field)
		{
			$type = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
			if (!in_array($type, self::$valueActionTypes, true)) { return false; }
			if ($type === 'choice') {
				$settings = FieldSettings::effective($field);
				return !isset($settings['mode']) || (string) $settings['mode'] !== 'multiple';
			}

			return true;
		}

		/** Returns actions whose condition changed from unmatched to matched. */
		public static function valueActions(array $fields, array $beforeValues, array $afterValues, $isCreate = false)
		{
			$beforeContext = self::valueContext($fields, $beforeValues);
			$afterContext = self::valueContext($fields, $afterValues);
			$actions = array();
			foreach ($fields as $field) {
				$field = (array) $field;
				$id = isset($field['Id']) ? (int) $field['Id'] : 0;
				$condition = self::condition($field);
				$action = isset($condition['value_action']) ? (string) $condition['value_action'] : '';
				if ($id <= 0 || !self::supportsValueAction($field) || !in_array($action, array('set', 'clear'), true)
					|| empty($condition['tree'])) { continue; }
				$matchedAfter = self::matches($condition['tree'], $afterContext);
				$matchedBefore = self::matches($condition['tree'], $beforeContext);
				if (!$matchedAfter || (!$isCreate && $matchedBefore)) { continue; }
				$actions[$id] = array(
					'action' => $action,
					'value' => $action === 'set' ? (string) (isset($condition['action_value']) ? $condition['action_value'] : '') : '',
				);
			}

			return $actions;
		}

		public static function states(array $fields, array $values)
		{
			$context = self::valueContext($fields, $values);
			$groupStates = self::groupStates($fields, $context);
			$states = array();
			foreach ($fields as $field) {
				$field = (array) $field;
				$id = isset($field['Id']) ? (int) $field['Id'] : 0;
				if ($id <= 0) { continue; }
				$settings = FieldSettings::effective($field);
				$baseRequired = !empty($settings['required']);
				$condition = isset($settings['form_condition']) && is_array($settings['form_condition'])
					? $settings['form_condition']
					: array();
				$state = self::conditionState($condition, $context, $baseRequired);
				$groupId = isset($field['rubric_field_group']) ? (int) $field['rubric_field_group'] : 0;
				$groupState = $groupId > 0 && isset($groupStates[$groupId])
					? $groupStates[$groupId]
					: array('visible' => true, 'locked' => false, 'matched' => true);
				$visible = !empty($groupState['visible']) && !empty($state['visible']);
				$states[$id] = array(
					'visible' => $visible,
					'required' => $visible && !empty($state['required']),
					'locked' => $visible && (!empty($groupState['locked']) || !empty($state['locked'])),
					'allowed_values' => $visible && isset($state['allowed_values']) ? $state['allowed_values'] : null,
					'value_action' => isset($state['value_action']) ? $state['value_action'] : null,
					'action_value' => isset($state['action_value']) ? $state['action_value'] : null,
					'matched' => !empty($state['matched']),
					'group_matched' => !empty($groupState['matched']),
				);
			}

			return $states;
		}

		public static function visibleValues(array $fields, array $values)
		{
			$states = self::states($fields, $values);
			foreach ($states as $id => $state) {
				if (empty($state['visible'])) { unset($values[$id], $values[(string) $id]); }
			}

			return $values;
		}

		/** Evaluates one persisted condition against document field values. */
		public static function state($condition, array $fields, array $values, $baseRequired = false)
		{
			return self::conditionState(
				is_array($condition) ? $condition : array(),
				self::valueContext($fields, $values),
				(bool) $baseRequired
			);
		}

		public static function matches(array $node, array $context)
		{
			if (isset($node['items']) && is_array($node['items'])) {
				$operator = isset($node['operator']) && (string) $node['operator'] === 'or' ? 'or' : 'and';
				if (empty($node['items'])) { return true; }
				foreach ($node['items'] as $item) {
					$result = is_array($item) ? self::matches($item, $context) : false;
					if ($operator === 'and' && !$result) { return false; }
					if ($operator === 'or' && $result) { return true; }
				}

				return $operator === 'and';
			}

			$reference = isset($node['field']) ? (string) $node['field'] : '';
			$value = array_key_exists($reference, $context) ? $context[$reference] : null;
			return self::compare($value, isset($node['operator']) ? (string) $node['operator'] : 'equals', isset($node['value']) ? $node['value'] : '');
		}

		protected static function normalizeGroup(array $group, array $references, $currentFieldId, $depth, &$ruleCount)
		{
			if ($depth > self::MAX_DEPTH) { throw new \InvalidArgumentException('Условия формы вложены глубже четырёх уровней'); }
			$items = array();
			foreach (isset($group['items']) && is_array($group['items']) ? $group['items'] : array() as $item) {
				if (!is_array($item)) { continue; }
				if (isset($item['items'])) {
					$nested = self::normalizeGroup($item, $references, $currentFieldId, $depth + 1, $ruleCount);
					if (!empty($nested['items'])) { $items[] = $nested; }
					continue;
				}

				$reference = self::normalizeReference(isset($item['field']) ? $item['field'] : '', $references);
				if ($reference === '') { continue; }
				if (isset($references[$reference]) && (int) $references[$reference] === (int) $currentFieldId) {
					throw new \InvalidArgumentException('Поле не может зависеть от самого себя');
				}

				$operator = isset($item['operator']) ? (string) $item['operator'] : 'equals';
				if (!in_array($operator, self::$operators, true)) { $operator = 'equals'; }
				$items[] = array(
					'field' => $reference,
					'operator' => $operator,
					'value' => in_array($operator, array('empty', 'not_empty'), true) ? '' : self::normalizeExpected(isset($item['value']) ? $item['value'] : ''),
				);
				$ruleCount++;
				if ($ruleCount > self::MAX_RULES) { throw new \InvalidArgumentException('В одном условии допускается не более 32 правил'); }
			}

			return array(
				'operator' => isset($group['operator']) && (string) $group['operator'] === 'or' ? 'or' : 'and',
				'items' => $items,
			);
		}

		protected static function groupStates(array $fields, array $context)
		{
			$states = array();
			foreach ($fields as $field) {
				$field = (array) $field;
				$groupId = isset($field['rubric_field_group']) ? (int) $field['rubric_field_group'] : 0;
				if ($groupId <= 0 || isset($states[$groupId])) { continue; }
				$states[$groupId] = self::conditionState(self::groupCondition($field), $context, false);
			}

			return $states;
		}

		protected static function conditionState(array $condition, array $context, $baseRequired)
		{
			if (empty($condition['tree'])) {
				return array('visible' => true, 'required' => (bool) $baseRequired, 'locked' => false, 'allowed_values' => null, 'value_action' => null, 'action_value' => null, 'matched' => true);
			}

			$matched = self::matches($condition['tree'], $context);
			$mode = isset($condition['mode']) ? (string) $condition['mode'] : 'show';
			$visible = $mode === 'lock' ? true : ($mode === 'hide' ? !$matched : $matched);

			return array(
				'visible' => $visible,
				'required' => $visible && ($baseRequired || !empty($condition['required'])),
				'locked' => $visible && $matched && $mode === 'lock',
				'allowed_values' => $visible && $matched && !empty($condition['allowed_values'])
					? self::normalizeAllowedValues($condition['allowed_values'])
					: null,
				'value_action' => $matched && isset($condition['value_action']) ? (string) $condition['value_action'] : null,
				'action_value' => $matched && isset($condition['value_action']) && (string) $condition['value_action'] === 'set'
					? (string) (isset($condition['action_value']) ? $condition['action_value'] : '') : null,
				'matched' => $matched,
			);
		}

		protected static function normalizeAllowedValues($values)
		{
			if (!is_array($values)) {
				$values = preg_split('/[\r\n,]+/', (string) $values) ?: array();
			}

			$out = array();
			foreach ($values as $value) {
				if (!is_scalar($value)) { continue; }
				$value = mb_substr(trim((string) $value), 0, 255, 'UTF-8');
				if ($value !== '' && !in_array($value, $out, true)) { $out[] = $value; }
				if (count($out) >= 100) { break; }
			}

			return $out;
		}

		protected static function fieldOptionValues(array $fields, $currentFieldId)
		{
			if ((int) $currentFieldId <= 0) { return array(); }
			foreach ($fields as $field) {
				$field = (array) $field;
				if (!isset($field['Id']) || (int) $field['Id'] !== (int) $currentFieldId) { continue; }
				$settings = FieldSettings::effective($field);
				$options = isset($settings['options']) && is_array($settings['options']) ? $settings['options'] : array();
				$out = array();
				foreach ($options as $key => $option) {
					if (is_array($option)) {
						$value = isset($option['value']) ? $option['value'] : (isset($option['key']) ? $option['key'] : $key);
					} else {
						$value = is_string($key) ? $key : $option;
					}

					$value = trim((string) $value);
					if ($value !== '' && !in_array($value, $out, true)) { $out[] = $value; }
				}

				return $out;
			}

			return array();
		}

		protected static function fieldById(array $fields, $currentFieldId)
		{
			foreach ($fields as $field) {
				$field = (array) $field;
				if (isset($field['Id']) && (int) $field['Id'] === (int) $currentFieldId) { return $field; }
			}

			return array();
		}

		protected static function normalizeActionValue($value)
		{
			if (is_array($value)) { $value = reset($value); }
			return mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, 2000, 'UTF-8');
		}

		protected static function references(array $fields)
		{
			$out = array();
			foreach ($fields as $field) {
				$field = (array) $field;
				$id = isset($field['Id']) ? (int) $field['Id'] : 0;
				if ($id <= 0) { continue; }
				$out['id:' . $id] = $id;
				$alias = isset($field['rubric_field_alias']) ? strtolower(trim((string) $field['rubric_field_alias'])) : '';
				if ($alias !== '') { $out['alias:' . $alias] = $id; }
			}

			return $out;
		}

		protected static function normalizeReference($reference, array $references)
		{
			$reference = strtolower(trim((string) $reference));
			if ($reference === '') { return ''; }
			if (ctype_digit($reference)) { $reference = 'id:' . (int) $reference; }
			elseif (strpos($reference, 'id:') !== 0 && strpos($reference, 'alias:') !== 0) { $reference = 'alias:' . $reference; }
			if (!empty($references) && !isset($references[$reference])) {
				throw new \InvalidArgumentException('Поле-источник условия не найдено: ' . $reference);
			}

			return $reference;
		}

		protected static function valueContext(array $fields, array $values)
		{
			$context = array();
			foreach ($fields as $field) {
				$field = (array) $field;
				$id = isset($field['Id']) ? (int) $field['Id'] : 0;
				if ($id <= 0) { continue; }
				$value = array_key_exists($id, $values) ? $values[$id] : (array_key_exists((string) $id, $values) ? $values[(string) $id] : '');
				$context['id:' . $id] = $value;
				$alias = isset($field['rubric_field_alias']) ? strtolower(trim((string) $field['rubric_field_alias'])) : '';
				if ($alias !== '') {
					$context['alias:' . $alias] = array_key_exists($alias, $values) ? $values[$alias] : $value;
				}
			}

			return $context;
		}

		protected static function compare($actual, $operator, $expected)
		{
			$values = self::flatten($actual);
			$nonEmpty = array_values(array_filter($values, function ($value) { return trim((string) $value) !== ''; }));
			if ($operator === 'empty') { return empty($nonEmpty); }
			if ($operator === 'not_empty') { return !empty($nonEmpty); }
			$expected = (string) $expected;
			if ($operator === 'equals') { return self::containsExact($values, $expected); }
			if ($operator === 'not_equals') { return !self::containsExact($values, $expected); }
			if ($operator === 'contains' || $operator === 'not_contains') {
				$found = false;
				foreach ($values as $value) {
					if ($expected !== '' && mb_stripos((string) $value, $expected, 0, 'UTF-8') !== false) { $found = true; break; }
				}

				return $operator === 'contains' ? $found : !$found;
			}

			if (in_array($operator, array('in', 'not_in'), true)) {
				$options = preg_split('/[\r\n,]+/', $expected) ?: array();
				$options = array_values(array_filter(array_map('trim', $options), 'strlen'));
				$found = false;
				foreach ($values as $value) { if (in_array((string) $value, $options, true)) { $found = true; break; } }
				return $operator === 'in' ? $found : !$found;
			}

			$number = isset($nonEmpty[0]) ? str_replace(',', '.', (string) $nonEmpty[0]) : '';
			$target = str_replace(',', '.', $expected);
			if (!is_numeric($number) || !is_numeric($target)) { return false; }
			if ($operator === 'greater') { return (float) $number > (float) $target; }
			if ($operator === 'greater_or_equal') { return (float) $number >= (float) $target; }
			if ($operator === 'less') { return (float) $number < (float) $target; }
			if ($operator === 'less_or_equal') { return (float) $number <= (float) $target; }
			return false;
		}

		protected static function containsExact(array $values, $expected)
		{
			foreach ($values as $value) { if ((string) $value === (string) $expected) { return true; } }
			return false;
		}

		protected static function flatten($value)
		{
			if (!is_array($value)) { return array((string) $value); }
			$out = array();
			array_walk_recursive($value, function ($item, $key) use (&$out) {
				if (substr((string) $key, -8) === '_present') { return; }
				if (is_scalar($item)) { $out[] = (string) $item; }
			});
			return $out;
		}

		protected static function normalizeExpected($value)
		{
			if (is_array($value)) {
				$value = implode(',', array_map('strval', $value));
			}

			return mb_substr(trim((string) $value), 0, 1000, 'UTF-8');
		}
	}
