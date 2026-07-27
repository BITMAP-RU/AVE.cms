<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldConditionImpact.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldConditionEvaluator;
	use App\Content\Fields\FieldSettings;
	use App\Content\Fields\FieldSettingsForm;
	use App\Helpers\Json;
	use DB;

	/** Calculates document-form impact before conditional rules are persisted. */
	class FieldConditionImpact
	{
		const MAX_DOCUMENTS = 5000;

		public static function preview($rubricId, array $drafts, $enabled)
		{
			$rubricId = (int) $rubricId;
			$rubric = Model::one($rubricId);
			if (!$rubric) {
				throw new \RuntimeException('Рубрика не найдена');
			}

			$enabled = (bool) $enabled;
			$enabledBefore = !empty($rubric['form_conditions_enabled']);
			$before = self::fieldMap(Model::fieldsForRubric($rubricId));
			$after = self::proposedFields($before, $drafts);
			$targets = self::changedTargets($before, $after, $enabledBefore, $enabled);
			return self::analyzeTransition($rubricId, $before, $after, $targets, $enabledBefore, $enabled);
		}

		public static function previewGroup($groupId, $condition)
		{
			$groupId = (int) $groupId;
			$group = Model::group($groupId);
			if (!$group) { throw new \RuntimeException('Группа не найдена'); }
			$rubricId = (int) $group['rubric_id'];
			$rubric = Model::one($rubricId);
			if (!$rubric) { throw new \RuntimeException('Рубрика не найдена'); }

			$enabled = !empty($rubric['form_conditions_enabled']);
			$before = self::fieldMap(Model::fieldsForRubric($rubricId));
			$after = self::proposedGroupFields($before, $groupId, $condition);
			$targets = array();
			if ($enabled && self::groupConditionKey($before, $groupId) !== self::groupConditionKey($after, $groupId)) {
				foreach ($before as $fieldId => $field) {
					if ((int) $field['rubric_field_group'] === $groupId) { $targets[] = (int) $fieldId; }
				}
			}

			$result = self::analyzeTransition($rubricId, $before, $after, $targets, $enabled, $enabled);
			$result['scope'] = 'group';
			$result['group_id'] = $groupId;
			$result['group_title'] = isset($group['group_title']) ? (string) $group['group_title'] : '';
			return $result;
		}

		protected static function analyzeTransition($rubricId, array $before, array $after, array $targets, $enabledBefore, $enabledAfter)
		{
			$result = array(
				'enabled_before' => $enabledBefore,
				'enabled_after' => $enabledAfter,
				'conditions_before' => self::conditionCount($before),
				'conditions_after' => self::conditionCount($after),
				'changed_fields' => count($targets),
				'documents_total' => 0,
				'documents_analyzed' => 0,
				'documents_changed' => 0,
				'hidden_before' => 0,
				'hidden_after' => 0,
				'required_before' => 0,
				'required_after' => 0,
				'locked_before' => 0,
				'locked_after' => 0,
				'limited_before' => 0,
				'limited_after' => 0,
				'action_before' => 0,
				'action_after' => 0,
				'truncated' => false,
				'details' => array(),
				'requires_confirmation' => !empty($targets),
				'fingerprint' => self::fingerprintFromFields($rubricId, $before, $after, $enabledBefore, $enabledAfter),
			);
			if (empty($targets)) {
				return $result;
			}

			$total = (int) DB::query(
				"SELECT COUNT(*) FROM " . Model::docsTable() . " WHERE rubric_id=%i AND document_deleted!='1'",
				$rubricId
			)->getValue();
			$documents = DB::query(
				"SELECT Id FROM " . Model::docsTable() . " WHERE rubric_id=%i AND document_deleted!='1' ORDER BY Id LIMIT " . self::MAX_DOCUMENTS,
				$rubricId
			)->getAll() ?: array();
			$result['documents_total'] = $total;
			$result['documents_analyzed'] = count($documents);
			$result['truncated'] = $total > count($documents);
			$documentIds = array_map(function ($row) { return (int) $row['Id']; }, $documents);
			$neededFields = self::neededFieldIds($targets, $before, $after);
			$values = self::documentValues($rubricId, $documentIds, $neededFields);
			$beforeEvaluation = self::evaluationFields($before, $neededFields);
			$afterEvaluation = self::evaluationFields($after, $neededFields);
			$details = array();
			foreach ($targets as $fieldId) {
				$field = isset($after[$fieldId]) ? $after[$fieldId] : $before[$fieldId];
				$details[$fieldId] = array(
					'id' => $fieldId,
					'title' => (string) $field['rubric_field_title'],
					'alias' => (string) $field['rubric_field_alias'],
					'changed_documents' => 0,
					'hidden_before' => 0,
					'hidden_after' => 0,
					'required_before' => 0,
					'required_after' => 0,
					'locked_before' => 0,
					'locked_after' => 0,
					'limited_before' => 0,
					'limited_after' => 0,
					'action_before' => 0,
					'action_after' => 0,
				);
			}

			$changedDocuments = array();
			foreach ($documentIds as $documentId) {
				$documentValues = isset($values[$documentId]) ? $values[$documentId] : array();
				$beforeStates = $enabledBefore ? FieldConditionEvaluator::states($beforeEvaluation, $documentValues) : array();
				$afterStates = $enabledAfter ? FieldConditionEvaluator::states($afterEvaluation, $documentValues) : array();
				foreach ($targets as $fieldId) {
					$beforeState = $enabledBefore && isset($beforeStates[$fieldId])
						? $beforeStates[$fieldId]
						: self::defaultState(isset($before[$fieldId]) ? $before[$fieldId] : array());
					$afterState = $enabledAfter && isset($afterStates[$fieldId])
						? $afterStates[$fieldId]
						: self::defaultState(isset($after[$fieldId]) ? $after[$fieldId] : array());
					$visibleBefore = !empty($beforeState['visible']);
					$visibleAfter = !empty($afterState['visible']);
					$requiredBefore = !empty($beforeState['required']);
					$requiredAfter = !empty($afterState['required']);
					$lockedBefore = !empty($beforeState['locked']);
					$lockedAfter = !empty($afterState['locked']);
					$allowedBefore = isset($beforeState['allowed_values']) && is_array($beforeState['allowed_values'])
						? array_values($beforeState['allowed_values']) : array();
					$allowedAfter = isset($afterState['allowed_values']) && is_array($afterState['allowed_values'])
						? array_values($afterState['allowed_values']) : array();
					$limitedBefore = !empty($allowedBefore);
					$limitedAfter = !empty($allowedAfter);
					$actionBefore = isset($beforeState['value_action']) ? (string) $beforeState['value_action'] : '';
					$actionAfter = isset($afterState['value_action']) ? (string) $afterState['value_action'] : '';
					$actionValueBefore = isset($beforeState['action_value']) ? (string) $beforeState['action_value'] : '';
					$actionValueAfter = isset($afterState['action_value']) ? (string) $afterState['action_value'] : '';
					if (!$visibleBefore) { $details[$fieldId]['hidden_before']++; $result['hidden_before']++; }
					if (!$visibleAfter) { $details[$fieldId]['hidden_after']++; $result['hidden_after']++; }
					if ($requiredBefore) { $details[$fieldId]['required_before']++; $result['required_before']++; }
					if ($requiredAfter) { $details[$fieldId]['required_after']++; $result['required_after']++; }
					if ($lockedBefore) { $details[$fieldId]['locked_before']++; $result['locked_before']++; }
					if ($lockedAfter) { $details[$fieldId]['locked_after']++; $result['locked_after']++; }
					if ($limitedBefore) { $details[$fieldId]['limited_before']++; $result['limited_before']++; }
					if ($limitedAfter) { $details[$fieldId]['limited_after']++; $result['limited_after']++; }
					if ($actionBefore !== '') { $details[$fieldId]['action_before']++; $result['action_before']++; }
					if ($actionAfter !== '') { $details[$fieldId]['action_after']++; $result['action_after']++; }
					if ($visibleBefore !== $visibleAfter || $requiredBefore !== $requiredAfter || $lockedBefore !== $lockedAfter
						|| $allowedBefore !== $allowedAfter || $actionBefore !== $actionAfter || $actionValueBefore !== $actionValueAfter) {
						$details[$fieldId]['changed_documents']++;
						$changedDocuments[$documentId] = true;
					}
				}
			}

			$result['documents_changed'] = count($changedDocuments);
			$result['details'] = array_values($details);
			return $result;
		}

		public static function fingerprint($rubricId, array $drafts, $enabled)
		{
			$rubric = Model::one((int) $rubricId);
			if (!$rubric) { throw new \RuntimeException('Рубрика не найдена'); }
			$before = self::fieldMap(Model::fieldsForRubric((int) $rubricId));
			$after = self::proposedFields($before, $drafts);
			return self::fingerprintFromFields(
				(int) $rubricId,
				$before,
				$after,
				!empty($rubric['form_conditions_enabled']),
				(bool) $enabled
			);
		}

		protected static function proposedFields(array $fields, array $drafts)
		{
			foreach ($drafts as $draft) {
				if (!is_array($draft) || empty($draft['id']) || !isset($draft['settings']) || !is_array($draft['settings'])) { continue; }
				$fieldId = (int) $draft['id'];
				if (!isset($fields[$fieldId])) { continue; }
				$settings = FieldSettings::effective($fields[$fieldId]);
				$type = (string) $fields[$fieldId]['rubric_field_type'];
				foreach (FieldSettingsForm::keys($type) as $key) { unset($settings[$key]); }
				$settings = array_replace($settings, FieldSettingsForm::collect($type, $draft['settings']));
				$fields[$fieldId]['rubric_field_settings'] = FieldSettings::encode($settings);
			}

			foreach ($drafts as $draft) {
				if (!is_array($draft) || empty($draft['id'])) { continue; }
				$fieldId = (int) $draft['id'];
				if (!isset($fields[$fieldId]) || !array_key_exists('condition', $draft)) { continue; }
				$settings = FieldSettings::effective($fields[$fieldId]);
				$condition = FieldConditionEvaluator::normalize($draft['condition'], array_values($fields), $fieldId);
				if ($condition) { $settings['form_condition'] = $condition; }
				else { unset($settings['form_condition']); }
				$fields[$fieldId]['rubric_field_settings'] = FieldSettings::encode($settings);
			}

			return $fields;
		}

		protected static function proposedGroupFields(array $fields, $groupId, $condition)
		{
			$normalized = FieldConditionEvaluator::normalize($condition, array_values($fields));
			foreach ($fields as &$field) {
				if ((int) $field['rubric_field_group'] !== (int) $groupId) { continue; }
				$settings = Json::toArray(isset($field['group_settings']) ? $field['group_settings'] : '');
				if ($normalized) { $settings['form_condition'] = $normalized; }
				else { unset($settings['form_condition']); }
				$field['group_settings'] = Json::payload($settings);
			}

			unset($field);
			return $fields;
		}

		protected static function changedTargets(array $before, array $after, $enabledBefore, $enabledAfter)
		{
			if (!$enabledBefore && !$enabledAfter) {
				return array();
			}

			$targets = array();
			foreach ($before as $fieldId => $field) {
				$old = FieldConditionEvaluator::condition($field);
				$new = isset($after[$fieldId]) ? FieldConditionEvaluator::condition($after[$fieldId]) : array();
				if (self::conditionKey($old) !== self::conditionKey($new)
					|| ($enabledBefore !== $enabledAfter && (!empty($old) || !empty($new)))) {
					$targets[] = (int) $fieldId;
				}
			}

			return $targets;
		}

		protected static function neededFieldIds(array $targets, array $before, array $after)
		{
			$aliases = array();
			foreach ($before as $fieldId => $field) {
				$alias = strtolower(trim((string) $field['rubric_field_alias']));
				if ($alias !== '') { $aliases[$alias] = (int) $fieldId; }
			}

			$ids = array_fill_keys($targets, true);
			foreach ($targets as $fieldId) {
				foreach (array($before, $after) as $fields) {
					$field = isset($fields[$fieldId]) ? $fields[$fieldId] : array();
					foreach (array(FieldConditionEvaluator::condition($field), FieldConditionEvaluator::groupCondition($field)) as $condition) {
						self::collectReferences(isset($condition['tree']) ? $condition['tree'] : array(), $aliases, $ids);
					}
				}
			}

			return array_map('intval', array_keys($ids));
		}

		protected static function collectReferences(array $node, array $aliases, array &$ids)
		{
			if (isset($node['items']) && is_array($node['items'])) {
				foreach ($node['items'] as $item) {
					if (is_array($item)) { self::collectReferences($item, $aliases, $ids); }
				}

				return;
			}

			$reference = isset($node['field']) ? strtolower((string) $node['field']) : '';
			if (strpos($reference, 'id:') === 0) {
				$id = (int) substr($reference, 3);
				if ($id > 0) { $ids[$id] = true; }
			} elseif (strpos($reference, 'alias:') === 0) {
				$alias = substr($reference, 6);
				if (isset($aliases[$alias])) { $ids[$aliases[$alias]] = true; }
			}
		}

		protected static function documentValues($rubricId, array $documentIds, array $fieldIds)
		{
			$values = array();
			foreach ($documentIds as $documentId) {
				$values[$documentId] = array_fill_keys($fieldIds, '');
			}

			if (empty($documentIds) || empty($fieldIds)) { return $values; }

			$rows = DB::query(
				'SELECT df.document_id,df.rubric_field_id,df.field_value,COALESCE(dft.field_value,\'\') more'
				. ' FROM ' . Model::docFieldsTable() . ' df'
				. ' INNER JOIN ' . Model::docsTable() . ' d ON d.Id=df.document_id'
				. ' LEFT JOIN ' . Model::docFieldsTextTable() . ' dft ON dft.document_id=df.document_id AND dft.rubric_field_id=df.rubric_field_id'
				. " WHERE d.rubric_id=%i AND d.document_deleted!='1' AND df.document_id IN %li AND df.rubric_field_id IN %li",
				(int) $rubricId,
				$documentIds,
				$fieldIds
			)->getAll() ?: array();
			foreach ($rows as $row) {
				$values[(int) $row['document_id']][(int) $row['rubric_field_id']] = (string) $row['field_value'] . (string) $row['more'];
			}

			return $values;
		}

		protected static function evaluationFields(array $fields, array $ids)
		{
			$out = array();
			foreach ($ids as $id) {
				if (isset($fields[$id])) { $out[] = $fields[$id]; }
			}

			return $out;
		}

		protected static function defaultState(array $field)
		{
			$settings = FieldSettings::effective($field);
			return array(
				'visible' => true,
				'required' => !empty($settings['required']),
				'locked' => false,
				'allowed_values' => null,
				'value_action' => null,
				'action_value' => null,
			);
		}

		protected static function conditionCount(array $fields)
		{
			$count = 0;
			foreach ($fields as $field) {
				if (FieldConditionEvaluator::condition($field)) { $count++; }
			}

			return $count;
		}

		protected static function fingerprintFromFields($rubricId, array $before, array $after, $enabledBefore, $enabledAfter)
		{
			$payload = array(
				'rubric_id' => (int) $rubricId,
				'enabled_before' => (bool) $enabledBefore,
				'enabled_after' => (bool) $enabledAfter,
				'before' => self::conditions($before),
				'after' => self::conditions($after),
				'before_groups' => self::groupConditions($before),
				'after_groups' => self::groupConditions($after),
				'documents' => self::documentSnapshot((int) $rubricId),
			);
			return hash('sha256', Json::encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}

		protected static function documentSnapshot($rubricId)
		{
			$row = DB::query(
				"SELECT COUNT(*) count,COALESCE(MAX(Id),0) max_id,COALESCE(MAX(document_changed),0) changed"
				. " FROM " . Model::docsTable() . " WHERE rubric_id=%i AND document_deleted!='1'",
				(int) $rubricId
			)->getAssoc() ?: array();

			return array(
				'count' => isset($row['count']) ? (int) $row['count'] : 0,
				'max_id' => isset($row['max_id']) ? (int) $row['max_id'] : 0,
				'changed' => isset($row['changed']) ? (int) $row['changed'] : 0,
			);
		}

		protected static function conditions(array $fields)
		{
			$out = array();
			foreach ($fields as $fieldId => $field) {
				$condition = FieldConditionEvaluator::condition($field);
				if ($condition) { $out[(string) $fieldId] = $condition; }
			}

			ksort($out, SORT_NUMERIC);
			return $out;
		}

		protected static function groupConditions(array $fields)
		{
			$out = array();
			foreach ($fields as $field) {
				$groupId = isset($field['rubric_field_group']) ? (int) $field['rubric_field_group'] : 0;
				if ($groupId <= 0 || isset($out[(string) $groupId])) { continue; }
				$condition = FieldConditionEvaluator::groupCondition($field);
				if ($condition) { $out[(string) $groupId] = $condition; }
			}

			ksort($out, SORT_NUMERIC);
			return $out;
		}

		protected static function groupConditionKey(array $fields, $groupId)
		{
			foreach ($fields as $field) {
				if ((int) $field['rubric_field_group'] === (int) $groupId) {
					return self::conditionKey(FieldConditionEvaluator::groupCondition($field));
				}
			}

			return self::conditionKey(array());
		}

		protected static function conditionKey(array $condition)
		{
			return Json::encode($condition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function fieldMap(array $fields)
		{
			$out = array();
			foreach ($fields as $field) { $out[(int) $field['Id']] = $field; }
			return $out;
		}
	}
