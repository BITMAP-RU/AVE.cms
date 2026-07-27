<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestOrderDiagnostics.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Frontend\RequestSort;
	use DB;

	/** Explains the first ordering difference using actual configured sort values. */
	class RequestOrderDiagnostics
	{
		public static function explain(array $legacyIds, array $nativeIds, array $plan)
		{
			$index = self::firstDifference($legacyIds, $nativeIds);
			if ($index === null) {
				return array();
			}

			$start = max(0, $index - 3);
			$legacyWindow = array_slice($legacyIds, $start, 8);
			$nativeWindow = array_slice($nativeIds, $start, 8);
			$legacyId = isset($legacyIds[$index]) ? (int) $legacyIds[$index] : 0;
			$nativeId = isset($nativeIds[$index]) ? (int) $nativeIds[$index] : 0;
			$values = self::loadValues(
				array_values(array_unique(array_merge($legacyWindow, $nativeWindow))),
				isset($plan['sort']) && is_array($plan['sort']) ? $plan['sort'] : array()
			);
			$criteria = array();
			$keysEqual = true;
			foreach (isset($plan['sort']) && is_array($plan['sort']) ? $plan['sort'] : array() as $sort) {
				$criterion = self::criterion($sort, $legacyId, $nativeId, $values);
				if (!$criterion) {
					continue;
				}

				$criteria[] = $criterion;
				if (empty($criterion['equal'])) {
					$keysEqual = false;
				}
			}

			return array(
				'index' => $index,
				'position' => $index + 1,
				'legacy_id' => $legacyId,
				'native_id' => $nativeId,
				'legacy_window' => array_values($legacyWindow),
				'native_window' => array_values($nativeWindow),
				'keys_equal' => $keysEqual,
				'criteria' => $criteria,
			);
		}

		public static function firstDifference(array $legacyIds, array $nativeIds)
		{
			$length = max(count($legacyIds), count($nativeIds));
			for ($index = 0; $index < $length; $index++) {
				if (!isset($legacyIds[$index], $nativeIds[$index])
					|| (int) $legacyIds[$index] !== (int) $nativeIds[$index]) {
					return $index;
				}
			}

			return null;
		}

		protected static function loadValues(array $ids, array $sort)
		{
			$result = array('documents' => array(), 'fields' => array(), 'field_titles' => array());
			$ids = array_values(array_filter(array_map('intval', $ids)));
			if (!$ids) {
				return $result;
			}

			$columns = array('Id');
			$fieldIds = array();
			foreach ($sort as $item) {
				if (isset($item['type']) && $item['type'] === 'document') {
					$column = RequestSort::documentColumn(isset($item['column']) ? $item['column'] : '');
					if ($column !== null && !in_array($column, $columns, true)) {
						$columns[] = $column;
					}
				} elseif (isset($item['type']) && $item['type'] === 'field' && !empty($item['field_id'])) {
					$fieldIds[] = (int) $item['field_id'];
				}
			}

			$select = array_map(function ($column) {
				return '`' . str_replace('`', '', $column) . '`';
			}, $columns);
			foreach (DB::query(
				'SELECT ' . implode(',', $select) . ' FROM %b WHERE Id IN %li',
				ContentTables::table('documents'),
				$ids
			)->getAll() as $row) {
				$result['documents'][(int) $row['Id']] = (array) $row;
			}

			$fieldIds = array_values(array_unique($fieldIds));
			if (!$fieldIds) {
				return $result;
			}

			foreach (DB::query(
				'SELECT document_id,rubric_field_id,field_value,field_number_value FROM %b'
					. ' WHERE document_id IN %li AND rubric_field_id IN %li',
				ContentTables::table('document_fields'),
				$ids,
				$fieldIds
			)->getAll() as $row) {
				$result['fields'][(int) $row['document_id']][(int) $row['rubric_field_id']] = (array) $row;
			}

			foreach (DB::query(
				'SELECT Id,rubric_field_title FROM %b WHERE Id IN %li',
				ContentTables::table('rubric_fields'),
				$fieldIds
			)->getAll() as $row) {
				$result['field_titles'][(int) $row['Id']] = (string) $row['rubric_field_title'];
			}

			return $result;
		}

		protected static function criterion(array $sort, $legacyId, $nativeId, array $values)
		{
			$direction = isset($sort['direction']) && $sort['direction'] === 'DESC' ? 'DESC' : 'ASC';
			$code = '';
			$label = '';
			$legacyValue = null;
			$nativeValue = null;
			if (isset($sort['type']) && $sort['type'] === 'field') {
				$fieldId = isset($sort['field_id']) ? (int) $sort['field_id'] : 0;
				$column = !empty($sort['numeric']) ? 'field_number_value' : 'field_value';
				$code = 'field_' . $fieldId;
				$label = isset($values['field_titles'][$fieldId]) && $values['field_titles'][$fieldId] !== ''
					? $values['field_titles'][$fieldId]
					: 'Поле #' . $fieldId;
				$legacyValue = isset($values['fields'][$legacyId][$fieldId][$column])
					? $values['fields'][$legacyId][$fieldId][$column]
					: null;
				$nativeValue = isset($values['fields'][$nativeId][$fieldId][$column])
					? $values['fields'][$nativeId][$fieldId][$column]
					: null;
			} elseif (isset($sort['type']) && $sort['type'] === 'document') {
				$column = RequestSort::documentColumn(isset($sort['column']) ? $sort['column'] : '');
				if ($column === null) {
					return array();
				}

				$code = $column;
				$label = self::documentLabel($column);
				$legacyValue = isset($values['documents'][$legacyId][$column])
					? $values['documents'][$legacyId][$column]
					: null;
				$nativeValue = isset($values['documents'][$nativeId][$column])
					? $values['documents'][$nativeId][$column]
					: null;
			} else {
				return array();
			}

			return array(
				'code' => $code,
				'label' => $label,
				'direction' => $direction,
				'legacy_value' => self::formatValue($legacyValue, $code),
				'native_value' => self::formatValue($nativeValue, $code),
				'equal' => self::sortValuesEqual($sort, $legacyId, $legacyValue, $nativeValue),
			);
		}

		/** Compare text through the real column collation used by MySQL ORDER BY. */
		protected static function sortValuesEqual(array $sort, $documentId, $legacyValue, $nativeValue)
		{
			if ($legacyValue === null || $nativeValue === null) {
				return $legacyValue === $nativeValue;
			}

			if (isset($sort['type']) && $sort['type'] === 'field' && empty($sort['numeric'])) {
				return (bool) DB::query(
					'SELECT field_value=%s FROM %b WHERE document_id=%i AND rubric_field_id=%i LIMIT 1',
					(string) $nativeValue,
					ContentTables::table('document_fields'),
					(int) $documentId,
					isset($sort['field_id']) ? (int) $sort['field_id'] : 0
				)->getValue();
			}

			if (isset($sort['type']) && $sort['type'] === 'document') {
				$column = RequestSort::documentColumn(isset($sort['column']) ? $sort['column'] : '');
				if ($column !== null && in_array($column, array(
					'document_alias', 'document_short_alias', 'document_title',
				), true)) {
					return (bool) DB::query(
						'SELECT `' . $column . '`=%s FROM %b WHERE Id=%i LIMIT 1',
						(string) $nativeValue,
						ContentTables::table('documents'),
						(int) $documentId
					)->getValue();
				}
			}

			return self::comparable($legacyValue) === self::comparable($nativeValue);
		}

		protected static function comparable($value)
		{
			return $value === null ? "\0NULL" : (string) $value;
		}

		protected static function formatValue($value, $code)
		{
			if ($value === null) { return 'нет значения'; }
			if ((string) $value === '') { return 'пусто'; }
			if (in_array($code, array('document_published', 'document_expire', 'document_changed'), true)
				&& ctype_digit((string) $value) && (int) $value > 0) {
				return date('d.m.Y H:i:s', (int) $value);
			}

			$value = (string) $value;
			return function_exists('mb_substr') ? mb_substr($value, 0, 80) : substr($value, 0, 80);
		}

		protected static function documentLabel($column)
		{
			$labels = array(
				'Id' => 'ID документа',
				'document_published' => 'Дата публикации',
				'document_changed' => 'Дата изменения',
				'document_expire' => 'Дата окончания',
				'document_position' => 'Позиция',
				'document_title' => 'Название',
				'document_count_view' => 'Просмотры',
				'document_count_print' => 'Печать',
			);

			return isset($labels[$column]) ? $labels[$column] : $column;
		}
	}
