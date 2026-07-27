<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Requests/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\Auth;
	use App\Common\FileCacheInvalidator;
	use App\Common\Lock;
	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Content\Fields\FieldRegistry;
	use App\Content\Fields\FieldSettings;
	use App\Content\Requests\RequestConditionValue;

	/**
	 * Запросы (листинги) и их условия. Данные ведём как есть; шаблоны (item/main) и
	 * значения условий (в т.ч. legacy-PHP) сохраняются без изменений и не исполняются.
	 */
	class Model
	{
		protected static function req()  { return ContentTables::table('request'); }
		protected static function cond() { return ContentTables::table('request_conditions'); }
		protected static function groups() { return ContentTables::table('request_condition_groups'); }

		/** Редактируемые скалярные поля запроса. */
		protected static $scalar = array(
			'request_alias', 'rubric_id', 'request_title', 'request_description',
			'request_order_by', 'request_asc_desc', 'request_items_per_page',
			'request_pagination', 'request_template_item', 'request_template_main',
			'request_cache_lifetime', 'request_result_contract',
			'request_preview_renderer', 'request_executor_mode', 'request_order_by_nat',
			'request_order_tiebreaker', 'request_sort_rules',
		);

		/** Поля-флаги enum('0','1'). */
		public static $flags = array(
			'request_show_pagination', 'request_count_items', 'request_hide_current',
			'request_only_owner', 'request_ajax', 'request_external',
			'request_cache_elements', 'request_show_statistic',
			'request_show_sql', 'request_use_query',
		);

		/** Операторы условий (легаси-коды). */
		public static function compareOptions()
		{
			return array(
				'=='  => 'Равно',
				'!='  => 'Не равно',
				'%%'  => 'Содержит текст',
				'%'   => 'Начинается с',
				'--'  => 'Не содержит текст',
				'!-'  => 'Не начинается с',
				'>'   => 'Больше',
				'<'   => 'Меньше',
				'>='  => 'Больше или равно',
				'<='  => 'Меньше или равно',
				'N>'  => 'Число больше',
				'N<'  => 'Число меньше',
				'N>=' => 'Число больше или равно',
				'N<=' => 'Число меньше или равно',
				'SEGMENT' => 'Диапазон, включая границы',
				'INTERVAL' => 'Диапазон, не включая границы',
				'IN=' => 'Входит в список',
				'NOTIN=' => 'Не входит в список',
				'ANY' => 'Совпадает с любым',
				'FRE' => 'Legacy SQL (совместимость)',
			);
		}

		public static function conditionValueModes()
		{
			return array(
				'string_scalar' => 'Взять текст как есть',
				'integer_scalar' => 'Взять целое число',
				'decimal_scalar' => 'Взять десятичное число',
				'string_list' => 'Взять список текстовых значений',
				'integer_list' => 'Взять список чисел',
				'integer_range' => 'Взять диапазон от/до',
				'decimal_range' => 'Взять десятичный диапазон',
				'integer_member' => 'Обернуть значение: |значение|',
				'integer_member_list' => 'Обернуть список: |значение|',
				'presence_constant' => 'Подставить константу, если заполнено',
			);
		}

		/** Сводка для карточек списка. */
		public static function stats()
		{
			return array(
				'requests'   => (int) DB::query('SELECT COUNT(*) FROM ' . self::req())->getValue(),
				'conditions' => (int) DB::query('SELECT COUNT(*) FROM ' . self::cond())->getValue(),
				'groups'     => (int) DB::query('SELECT COUNT(*) FROM ' . self::groups())->getValue(),
				'rubrics'    => (int) DB::query('SELECT COUNT(DISTINCT rubric_id) FROM ' . self::req())->getValue(),
			);
		}

		public static function all()
		{
			return DB::query(
				'SELECT r.Id, r.request_alias, r.rubric_id, r.request_title, r.request_order_by,
					r.request_asc_desc, r.request_items_per_page, r.request_changed,
					r.request_executor_mode, r.request_native_verified_hash,
					(SELECT COUNT(*) FROM ' . self::cond() . ' c WHERE c.request_id = r.Id) AS cond_count
             FROM ' . self::req() . ' r
             ORDER BY r.request_alias ASC'
			)->getAll();
		}

		public static function nativeAuditRows()
		{
			$rows = array();
			foreach (DB::query(
				'SELECT Id,request_alias,request_title,rubric_id,request_executor_mode,request_native_verified_hash,request_native_plan,'
					. 'request_native_audit_status,request_native_audit_details'
					. ' FROM ' . self::req() . ' ORDER BY request_title ASC,Id ASC'
			)->getAll() as $request) {
				$request = (object) $request;
				$plan = \App\Content\Requests\NativeRequestPlanCompiler::compile((int) $request->Id);
				$details = isset($request->request_native_audit_details)
					? json_decode((string) $request->request_native_audit_details, true)
					: array();
				$rows[] = array(
					'id' => (int) $request->Id,
					'alias' => (string) $request->request_alias,
					'title' => (string) $request->request_title,
					'rubric_id' => (int) $request->rubric_id,
					'mode' => self::executorMode($request),
					'eligible' => !empty($plan['eligible']),
					'verified' => self::nativeVerified($request, $plan),
					'reasons' => isset($plan['reasons']) ? array_values($plan['reasons']) : array(),
					'audit_status' => isset($request->request_native_audit_status)
						? (string) $request->request_native_audit_status
						: '',
					'audit_details' => is_array($details) ? $details : array(),
				);
			}

			return $rows;
		}

		public static function nativeAuditStats(array $rows)
		{
			$stats = array(
				'total' => count($rows),
				'eligible' => 0,
				'verified' => 0,
				'legacy' => 0,
				'shadow' => 0,
				'native' => 0,
				'unstable' => 0,
				'order_diff' => 0,
			);
			foreach ($rows as $row) {
				if (!empty($row['eligible'])) { $stats['eligible']++; }
				if (!empty($row['verified'])) { $stats['verified']++; }
				$mode = isset($row['mode']) && isset($stats[$row['mode']]) ? $row['mode'] : 'legacy';
				$stats[$mode]++;
				$status = isset($row['audit_status']) ? (string) $row['audit_status'] : '';
				if (isset($stats[$status])) { $stats[$status]++; }
			}

			return $stats;
		}

		public static function setAllExecutorMode($mode)
		{
			$mode = in_array($mode, array('legacy', 'shadow'), true) ? $mode : 'legacy';
			$requests = DB::query('SELECT Id,request_alias FROM ' . self::req())->getAll();
			DB::query('UPDATE ' . self::req() . ' SET request_executor_mode=%s', $mode);
			$affected = (int) DB::affectedRows();
			\App\Frontend\RequestRepository::reset();
			foreach ($requests as $request) {
				FileCacheInvalidator::request((int) $request['Id'], (string) $request['request_alias']);
			}

			return $affected;
		}

		/** Adds typed descriptors beside legacy PHP; public Legacy execution is unchanged. */
		public static function stageLegacyConditionValues()
		{
			$count = 0;
			$requests = array();
			$rows = DB::query(
				'SELECT * FROM ' . self::cond() . " WHERE condition_value LIKE '%<?%' ORDER BY request_id,Id"
			)->getAll();
			foreach ($rows as $row) {
				if (isset($row['condition_value_source']) && $row['condition_value_source'] === 'input') { continue; }
				$descriptor = RequestConditionValue::legacyDescriptor($row['condition_value'], $row['condition_compare']);
				if ($descriptor === null) { continue; }
				DB::Update(self::cond(), array(
					'condition_value_source' => 'input',
					'condition_value_key' => $descriptor['key'],
					'condition_value_config' => RequestConditionValue::encodeConfig($descriptor),
				), 'Id=%i', (int) $row['Id']);
				$requests[(int) $row['request_id']] = true;
				$count++;
			}

			foreach (array_keys($requests) as $requestId) {
				self::touch((int) $requestId);
			}

			return array('conditions' => $count, 'requests' => count($requests));
		}

		public static function activateVerifiedNative()
		{
			$activated = 0;
			$invalidated = array();
			foreach (DB::query('SELECT * FROM ' . self::req() . ' ORDER BY Id')->getAll() as $row) {
				$request = (object) $row;
				$plan = \App\Content\Requests\NativeRequestPlanCompiler::compile((int) $request->Id);
				if (empty($plan['eligible']) || !self::nativeVerified($request, $plan)) {
					continue;
				}

				$auditDetails = isset($request->request_native_audit_details)
					? json_decode((string) $request->request_native_audit_details, true)
					: array();
				$baseline = is_array($auditDetails) && isset($auditDetails['runtime_verification'])
					? (array) $auditDetails['runtime_verification']
					: array();
				$finalized = self::finalizeStagedConditionValues((int) $request->Id, $baseline);
				if (!$finalized) { continue; }
				if (is_array($finalized)) { self::recordNativeVerification((int) $request->Id, $finalized); }
				DB::Update(self::req(), array('request_executor_mode' => 'native'), 'Id=%i', (int) $request->Id);
				$invalidated[] = array((int) $request->Id, (string) $request->request_alias);
				$activated++;
			}

			\App\Frontend\RequestRepository::reset();
			foreach ($invalidated as $request) {
				FileCacheInvalidator::request($request[0], $request[1]);
			}

			return $activated;
		}

		public static function conditionValuesEquivalent(array $comparison)
		{
			$verification = isset($comparison['runtime_verification']) ? $comparison['runtime_verification'] : array();
			if (!$verification) { return false; }
			foreach ($verification as $item) {
				if (!in_array(isset($item['status']) ? $item['status'] : '', array('matched', 'unstable', 'order_diff'), true)) {
					return false;
				}

				if ((int) $item['legacy_total'] !== (int) $item['native_total']) { return false; }
			}

			return true;
		}

		public static function finalizeStagedConditionValues($requestId, array $baseline = array())
		{
			$rows = DB::query(
				'SELECT * FROM ' . self::cond() . " WHERE request_id=%i AND condition_value_source='input' AND condition_value LIKE '%<?%' ORDER BY Id",
				(int) $requestId
			)->getAll();
			if (!$rows) { return true; }

			$backup = array();
			foreach ($rows as $row) {
				$descriptor = RequestConditionValue::descriptor($row);
				if ($descriptor === null) { return false; }
				$backup[(int) $row['Id']] = (string) $row['condition_value'];
				DB::Update(self::cond(), array(
					'condition_value' => RequestConditionValue::tag($descriptor['key']),
				), 'Id=%i', (int) $row['Id']);
			}

			\App\Content\Requests\NativeRequestPlanCompiler::persist((int) $requestId);
			try {
				$comparison = (new \App\Content\Requests\RequestExecutorComparator())->compare((int) $requestId, 20);
			} catch (\Throwable $e) {
				$comparison = array('matched' => false);
			}

			if (!self::sameLegacyVerification($baseline, isset($comparison['runtime_verification']) ? $comparison['runtime_verification'] : array())) {
				foreach ($backup as $conditionId => $value) {
					DB::Update(self::cond(), array('condition_value' => $value), 'Id=%i', (int) $conditionId);
				}

				\App\Content\Requests\NativeRequestPlanCompiler::persist((int) $requestId);
				return false;
			}

			return $comparison;
		}

		protected static function sameLegacyVerification(array $before, array $after)
		{
			if (!$before || count($before) !== count($after)) { return false; }
			foreach ($before as $index => $item) {
				$current = isset($after[$index]) ? $after[$index] : array();
				if ((string) (isset($item['label']) ? $item['label'] : '') !== (string) (isset($current['label']) ? $current['label'] : '')) {
					return false;
				}

				if ((int) (isset($item['legacy_total']) ? $item['legacy_total'] : -1) !== (int) (isset($current['legacy_total']) ? $current['legacy_total'] : -2)) {
					return false;
				}

				if ((string) (isset($item['legacy_ids_hash']) ? $item['legacy_ids_hash'] : '') !== (string) (isset($current['legacy_ids_hash']) ? $current['legacy_ids_hash'] : '')) {
					return false;
				}
			}

			return true;
		}

		/** Add an ID tiebreaker only when it preserves the complete current Legacy order. */
		public static function stabilizeNativeOrder($requestId)
		{
			$requestId = (int) $requestId;
			return Lock::run('request-native-order:' . $requestId, function () use ($requestId) {
				$request = self::find($requestId);
				if (!$request) {
					return array('status' => 'missing', 'message' => 'Запрос не найден');
				}

				$sortRules = self::sortRules($request);
				$currentTie = '';
				if ($sortRules) {
					$lastRule = end($sortRules);
					if ($lastRule['source'] === 'document' && (string) $lastRule['key'] === 'Id') {
						$currentTie = (string) $lastRule['direction'];
					}
				}

				if ($currentTie !== '') {
					return array('status' => 'already_set', 'message' => 'Финальный порядок уже настроен');
				}

				$recommendation = (new \App\Content\Requests\RequestTieBreakerAdvisor())->recommend($requestId);
				if ($recommendation['status'] !== 'recommended') {
					return array(
						'status' => $recommendation['status'],
						'message' => isset($recommendation['reasons'][0])
							? (string) $recommendation['reasons'][0]
							: 'Безопасный финальный порядок не найден',
					);
				}

				$direction = (string) $recommendation['direction'];
				$originalMode = self::executorMode($request);
				$changed = false;
				try {
					$sortRules[] = array('source' => 'document', 'key' => 'Id', 'direction' => $direction);
					$legacySort = \App\Frontend\RequestSort::legacyStorage($sortRules);
					DB::Update(self::req(), array_merge($legacySort, array(
						'request_sort_rules' => \App\Frontend\RequestSort::encodeRules($sortRules),
						'request_executor_mode' => 'shadow',
					)), 'Id=%i', $requestId);
					$changed = true;
					\App\Content\Requests\NativeRequestPlanCompiler::persist($requestId);
					FileCacheInvalidator::request($requestId, (string) $request->request_alias);

					$comparison = (new \App\Content\Requests\RequestExecutorComparator())->compare($requestId);
					$afterFingerprint = isset($comparison['legacy'])
						? \App\Content\Requests\RequestExecutorComparator::resultFingerprint($comparison['legacy'])
						: '';
					if (empty($comparison['matched'])
						|| !hash_equals((string) $recommendation['legacy_fingerprint'], $afterFingerprint)) {
						self::restoreTieBreaker($request, $originalMode);
						return array(
							'status' => 'rolled_back',
							'message' => 'Порядок изменился при контрольной проверке. Настройка отменена.',
						);
					}

					self::recordNativeVerification($requestId, $comparison);
					DB::Update(self::req(), array('request_executor_mode' => 'native'), 'Id=%i', $requestId);
					\App\Frontend\RequestRepository::reset();
					FileCacheInvalidator::request($requestId, (string) $request->request_alias);

					return array(
						'status' => 'applied',
						'message' => 'Порядок закреплён без изменения Legacy-результата',
						'direction' => $direction,
						'total' => isset($recommendation['total']) ? (int) $recommendation['total'] : 0,
						'comparison' => $comparison,
					);
				} catch (\Throwable $e) {
					if ($changed) {
						self::restoreTieBreaker($request, $originalMode);
					}

					throw $e;
				}
			}, 10.0, true);
		}

		protected static function restoreTieBreaker($request, $mode)
		{
			DB::Update(self::req(), array(
				'request_order_by' => isset($request->request_order_by) ? (string) $request->request_order_by : '',
				'request_order_by_nat' => isset($request->request_order_by_nat) ? (int) $request->request_order_by_nat : 0,
				'request_asc_desc' => isset($request->request_asc_desc) ? (string) $request->request_asc_desc : 'ASC',
				'request_order_tiebreaker' => isset($request->request_order_tiebreaker) ? (string) $request->request_order_tiebreaker : '',
				'request_sort_rules' => isset($request->request_sort_rules) ? $request->request_sort_rules : null,
				'request_executor_mode' => (string) $mode,
			), 'Id=%i', (int) $request->Id);
			\App\Content\Requests\NativeRequestPlanCompiler::persist((int) $request->Id);
			\App\Frontend\RequestRepository::reset();
			FileCacheInvalidator::request((int) $request->Id, (string) $request->request_alias);
		}

		public static function find($id)
		{
			return DB::query('SELECT * FROM ' . self::req() . ' WHERE Id = %i LIMIT 1', (int) $id)->getObject();
		}

		public static function aliasExists($alias, $exceptId = 0)
		{
			return (bool) DB::query(
				'SELECT Id FROM ' . self::req() . ' WHERE request_alias = %s AND Id != %i LIMIT 1',
				(string) $alias, (int) $exceptId
			)->getValue();
		}

		/** Собрать writable-набор полей из входных данных. */
		protected static function fields(array $data)
		{
			$fields = array();
			foreach (self::$scalar as $f) {
				if (array_key_exists($f, $data)) {
					$fields[$f] = $data[$f];
				}
			}

			foreach (self::$flags as $f) {
				if (array_key_exists($f, $data)) {
					$fields[$f] = !empty($data[$f]) ? '1' : '0';
				}
			}

			return $fields;
		}

		public static function create(array $data)
		{
			$now = time();
			$fields = self::fields($data);
			$fields += array(
				'request_author_id' => Auth::id(),
				'request_created'   => $now,
				'request_changed'   => $now,
				'request_changed_elements' => $now,
				'request_where_cond' => isset($data['request_where_cond']) ? (string) $data['request_where_cond'] : '',
			);
			DB::Insert(self::req(), $fields);
			$id = DB::insertId();
			self::createRootGroup($id);
			\App\Content\Requests\NativeRequestPlanCompiler::persist($id);
			return $id;
		}

		public static function update($id, array $data)
		{
			$current = self::find($id);
			$fields = self::fields($data);
			$fields['request_changed'] = $fields['request_changed_elements'] = time();
			$fields['request_where_cond'] = '';
			DB::Update(self::req(), $fields, 'Id = %i', (int) $id);
			\App\Content\Requests\NativeRequestPlanCompiler::persist($id, $fields);
			FileCacheInvalidator::request($id, $current ? $current->request_alias : '');
		}

		public static function copy($id)
		{
			$row = self::find($id);
			if (!$row) {
				return 0;
			}

			$data = (array) $row;
			unset($data['Id']);
			$data['request_alias'] = self::uniqueAlias((string) $row->request_alias . '_copy');
			$data['request_title'] = (string) $row->request_title . ' (копия)';
			$data['request_author_id'] = Auth::id();
			$data['request_created'] = time();
			$data['request_changed'] = time();
			$data['request_changed_elements'] = $data['request_changed'];
			$data['request_where_cond'] = '';
			DB::Insert(self::req(), $data);
			$newId = DB::insertId();

			//-- Сначала копируем дерево групп, затем привязанные к нему условия.
			$groupMap = array();
			foreach (self::groupsFlat($id) as $group) {
				$group = (array) $group;
				$oldId = (int) $group['Id'];
				$parentId = (int) $group['parent_id'];
				DB::Insert(self::groups(), array(
					'request_id' => $newId,
					'parent_id' => $parentId > 0 && isset($groupMap[$parentId]) ? $groupMap[$parentId] : 0,
					'group_title' => (string) $group['group_title'],
					'group_operator' => (string) $group['group_operator'],
					'group_position' => (int) $group['group_position'],
				));
				$groupMap[$oldId] = DB::insertId();
			}

			if (!$groupMap) {
				$groupMap[0] = self::createRootGroup($newId);
			}

			foreach (self::conditions($id) as $condition) {
				$copy = (array) $condition;
				unset($copy['Id']);
				$copy['request_id'] = $newId;
				$oldGroup = isset($copy['condition_group_id']) ? (int) $copy['condition_group_id'] : 0;
				$copy['condition_group_id'] = isset($groupMap[$oldGroup])
					? $groupMap[$oldGroup]
					: reset($groupMap);
				DB::Insert(self::cond(), $copy);
			}

			\App\Content\Requests\NativeRequestPlanCompiler::persist($newId);

			return $newId;
		}

		protected static function uniqueAlias($base)
		{
			$base = substr(preg_replace('/[^a-z0-9_-]/', '', strtolower($base)), 0, 20) ?: 'request';
			$alias = $base;
			$i = 1;
			while (self::aliasExists($alias)) {
				$suffix = (string) $i++;
				$alias = substr($base, 0, 20 - strlen($suffix)) . $suffix;
			}

			return $alias;
		}

		public static function delete($id)
		{
			$current = self::find($id);
			DB::Delete(self::cond(), 'request_id = %i', (int) $id);
			DB::Delete(self::groups(), 'request_id = %i', (int) $id);
			DB::Delete(self::req(), 'Id = %i', (int) $id);
			FileCacheInvalidator::request($id, $current ? $current->request_alias : '');
		}

		// ---------------- Условия ----------------

		public static function conditions($requestId)
		{
			return DB::query(
				'SELECT * FROM ' . self::cond() . ' WHERE request_id = %i ORDER BY condition_position ASC, Id ASC',
				(int) $requestId
			)->getAll();
		}

		public static function rubricOptions()
		{
			return DB::query(
				'SELECT Id, rubric_title FROM ' . ContentTables::table('rubrics') . ' ORDER BY rubric_title ASC, Id ASC'
			)->getAll();
		}

		public static function rubricFields($rubricId)
		{
			$rows = DB::query(
				'SELECT Id, rubric_field_title, rubric_field_alias, rubric_field_type, rubric_field_numeric,
					rubric_field_default, rubric_field_settings
	             FROM ' . ContentTables::table('rubric_fields') . '
	             WHERE rubric_id = %i
	             ORDER BY rubric_field_position ASC, Id ASC',
				(int) $rubricId
			)->getAll();
			foreach ($rows as $index => $row) {
				$rows[$index] = self::decorateConditionField($row);
			}

			return $rows;
		}

		protected static function decorateConditionField($row)
		{
			$row = is_object($row) ? $row : (object) $row;
			$definition = (array) $row;
			$typeCode = isset($definition['rubric_field_type']) ? (string) $definition['rubric_field_type'] : '';
			$type = FieldRegistry::get($typeCode);
			$settings = FieldSettings::effective($definition);
			$kind = self::conditionFieldKind($typeCode, $type, $definition);
			$options = self::conditionFieldOptions(isset($settings['options']) ? $settings['options'] : array());
			$rubrics = array();
			foreach ((array) (isset($settings['rubric']) ? $settings['rubric'] : array()) as $rubricId) {
				$rubricId = (int) $rubricId;
				if ($rubricId > 0) { $rubrics[$rubricId] = $rubricId; }
			}

			$row->condition_kind = $kind;
			$row->condition_type_label = $type ? (string) $type->name() : $typeCode;
			$row->condition_options = array_values($options);
			$row->condition_rubrics = array_values($rubrics);
			$row->condition_value_mode = self::conditionFieldMode($kind, $typeCode, $options);
			$row->condition_operators = self::conditionFieldOperators($kind, $typeCode);
			return $row;
		}

		protected static function conditionFieldKind($typeCode, $type, array $definition)
		{
			if (in_array($typeCode, array('checkbox', 'boolean'), true)) { return 'boolean'; }
			if ($typeCode === 'date') { return 'date'; }
			if ($typeCode === 'date_time') { return 'datetime'; }
			if (in_array($typeCode, array('doc_from_rub'), true)) { return 'relation'; }
			if (in_array($typeCode, array('doc_from_rub_all', 'doc_from_rub_check', 'doc_from_rub_search', 'analoque', 'teasers'), true)) { return 'relation_list'; }
			if ($type && $type->isChoice()) { return $type->isMultiple() ? 'choice_list' : 'choice'; }
			if ($type && $type->isNumeric()) { return 'number'; }
			if (!empty($definition['rubric_field_numeric'])) { return 'number'; }

			return 'text';
		}

		protected static function conditionFieldOptions($raw)
		{
			$options = array();
			foreach ((array) $raw as $key => $item) {
				if (is_array($item)) {
					$value = isset($item['value']) ? $item['value'] : (isset($item['key']) ? $item['key'] : $key);
					$label = isset($item['label']) ? $item['label'] : (isset($item['title']) ? $item['title'] : $value);
				} else {
					$value = is_string($key) && !ctype_digit($key) ? $key : $item;
					$label = $item;
				}

				$value = trim((string) $value);
				$label = trim((string) $label);
				if ($value !== '') { $options[$value] = array('value' => $value, 'label' => $label !== '' ? $label : $value); }
			}

			return $options;
		}

		protected static function conditionFieldMode($kind, $typeCode, array $options)
		{
			if ($kind === 'relation' || $kind === 'boolean') { return 'integer_scalar'; }
			if ($kind === 'number' || $kind === 'datetime') { return 'decimal_scalar'; }
			if ($typeCode === 'catalog') { return 'integer_member'; }
			if ($kind === 'relation_list') { return 'integer_list'; }
			if ($kind === 'choice_list') {
				return self::optionsAreInteger($options) ? 'integer_list' : 'string_list';
			}

			if ($kind === 'choice' && self::optionsAreInteger($options)) { return 'integer_scalar'; }

			return 'string_scalar';
		}

		protected static function optionsAreInteger(array $options)
		{
			if (!$options) { return false; }
			foreach (array_keys($options) as $value) {
				if (!preg_match('/^-?[0-9]+$/', (string) $value)) { return false; }
			}

			return true;
		}

		protected static function conditionFieldOperators($kind, $typeCode)
		{
			if ($kind === 'boolean') { return array('==', '!='); }
			if (in_array($kind, array('number', 'date', 'datetime', 'relation'), true)) {
				return array('==', '!=', 'N>', 'N<', 'N>=', 'N<=', 'SEGMENT', 'INTERVAL', 'IN=', 'NOTIN=');
			}

			if (in_array($kind, array('choice', 'choice_list', 'relation_list'), true)) {
				return array('==', '!=', '%%', '--', 'IN=', 'NOTIN=');
			}

			if ($typeCode === 'catalog') { return array('==', '!=', '%%', '--', 'IN=', 'NOTIN='); }

			return array('==', '!=', '%%', '%', '--', '!-', 'IN=', 'NOTIN=');
		}

		public static function documentPicker($query, $rubricIds = '', $limit = 30)
		{
			$limit = max(1, min(50, (int) $limit));
			$sql = 'SELECT d.Id,d.rubric_id,d.document_title,d.document_alias,r.rubric_title'
				. ' FROM ' . ContentTables::table('documents') . ' d'
				. ' LEFT JOIN ' . ContentTables::table('rubrics') . ' r ON r.Id=d.rubric_id'
				. " WHERE d.document_deleted!='1'";
			$args = array();
			$query = trim((string) $query);
			if ($query !== '') {
				$sql .= ' AND (d.document_title LIKE %ss OR d.document_alias LIKE %ss OR d.Id=%i)';
				$args[] = $query;
				$args[] = $query;
				$args[] = (int) $query;
			}

			$ids = array();
			foreach (explode(',', (string) $rubricIds) as $rubricId) {
				$rubricId = (int) trim($rubricId);
				if ($rubricId > 0) { $ids[$rubricId] = $rubricId; }
			}

			if ($ids) { $sql .= ' AND d.rubric_id IN (' . implode(',', $ids) . ')'; }
			$sql .= ' ORDER BY d.document_changed DESC,d.Id DESC LIMIT ' . $limit;
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();
			$result = array();
			foreach ($rows as $row) {
				$result[] = array(
					'id' => (int) $row['Id'],
					'title' => (string) $row['document_title'],
					'alias' => (string) $row['document_alias'],
					'rubric_id' => (int) $row['rubric_id'],
					'rubric_title' => (string) $row['rubric_title'],
				);
			}

			return $result;
		}

		public static function paginationOptions()
		{
			try {
				return DB::query(
					'SELECT id,pagination_name FROM ' . SystemTables::table('paginations') . ' ORDER BY id ASC'
				)->getAll();
			} catch (\Throwable $e) {
				return array();
			}
		}

		public static function resultContract($request)
		{
			$rubricId = is_object($request) && isset($request->rubric_id) ? (int) $request->rubric_id : 0;
			$value = is_object($request) && isset($request->request_result_contract)
				? $request->request_result_contract
				: null;

			return \App\Content\Requests\RequestResultContract::decode($value, self::rubricFieldIds($rubricId));
		}

		public static function encodeResultContract($rubricId, array $value)
		{
			return \App\Content\Requests\RequestResultContract::encode($value, self::rubricFieldIds($rubricId));
		}

		public static function previewRenderer($request)
		{
			$value = is_object($request) && isset($request->request_preview_renderer)
				? $request->request_preview_renderer
				: 'data_cards';
			return \App\Content\Requests\RequestRendererRegistry::normalizePreviewCode($value);
		}

		public static function sortRules($request)
		{
			return \App\Frontend\RequestSort::rulesForRequest($request);
		}

		public static function previewPlan($requestId)
		{
			$request = self::find($requestId);
			if (!$request) { return array(); }

			return array(
				'rubric_id' => (int) $request->rubric_id,
				'groups' => (int) DB::query(
					'SELECT COUNT(*) FROM ' . self::groups() . ' WHERE request_id=%i',
					(int) $requestId
				)->getValue(),
				'conditions' => (int) DB::query(
					"SELECT COUNT(*) FROM " . self::cond() . " WHERE request_id=%i AND condition_status='1'",
					(int) $requestId
				)->getValue(),
				'order' => self::sortSummary($request),
				'publication' => 'Опубликован, не удалён и входит в срок публикации',
			);
		}

		protected static function sortSummary($request)
		{
			$rules = self::sortRules($request);
			if (!$rules) { return 'Без явной сортировки'; }

			$labels = array();
			$options = \App\Frontend\RequestSort::storedOptions();
			$fields = array();
			foreach (self::rubricFields((int) $request->rubric_id) as $field) {
				$fields[(int) $field->Id] = (string) $field->rubric_field_title;
			}

			foreach ($rules as $rule) {
				if ($rule['source'] === 'field') {
					$label = isset($fields[(int) $rule['key']]) ? $fields[(int) $rule['key']] : 'Поле #' . (int) $rule['key'];
				} elseif ($rule['source'] === 'random') {
					$labels[] = 'Случайный порядок';
					continue;
				} else {
					$label = isset($options[$rule['key']]) ? $options[$rule['key']] : (string) $rule['key'];
				}

				$labels[] = $label . ($rule['direction'] === 'DESC' ? ' ↓' : ' ↑');
			}

			return implode(' → ', $labels);
		}

		public static function nativePlan($requestId)
		{
			return \App\Content\Requests\NativeRequestPlanCompiler::compile((int) $requestId);
		}

		public static function nativeVerified($request, array $plan)
		{
			$verified = is_object($request) && isset($request->request_native_verified_hash)
				? (string) $request->request_native_verified_hash
				: '';
			return $verified !== '' && isset($plan['source_hash']) && hash_equals($verified, (string) $plan['source_hash']);
		}

		public static function recordNativeVerification($requestId, array $comparison)
		{
			$hash = !empty($comparison['matched']) && isset($comparison['plan']['source_hash'])
				? (string) $comparison['plan']['source_hash']
				: null;
			$details = array(
				'reasons' => isset($comparison['reasons']) ? array_values((array) $comparison['reasons']) : array(),
				'legacy_total' => isset($comparison['legacy']['total']) ? (int) $comparison['legacy']['total'] : null,
				'native_total' => isset($comparison['native']['total']) ? (int) $comparison['native']['total'] : null,
				'missing_ids' => isset($comparison['missing_ids']) ? array_slice(array_values((array) $comparison['missing_ids']), 0, 20) : array(),
				'extra_ids' => isset($comparison['extra_ids']) ? array_slice(array_values((array) $comparison['extra_ids']), 0, 20) : array(),
				'order_diagnostics' => isset($comparison['order_diagnostics']) && is_array($comparison['order_diagnostics'])
					? $comparison['order_diagnostics']
					: array(),
				'runtime_verification' => isset($comparison['runtime_verification']) && is_array($comparison['runtime_verification'])
					? $comparison['runtime_verification']
					: array(),
				'checked_at' => time(),
			);
			$fields = array(
				'request_native_verified_hash' => $hash,
				'request_native_audit_status' => isset($comparison['status']) ? (string) $comparison['status'] : 'error',
				'request_native_audit_details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			);
			if (isset($comparison['plan']) && is_array($comparison['plan'])) {
				$fields['request_native_plan'] = \App\Content\Requests\NativeRequestPlanCompiler::encode($comparison['plan']);
			}

			DB::Update(self::req(), $fields, 'Id=%i', (int) $requestId);
			\App\Frontend\RequestRepository::reset();
		}

		public static function executorMode($request)
		{
			$mode = is_object($request) && isset($request->request_executor_mode)
				? strtolower((string) $request->request_executor_mode)
				: 'legacy';
			return in_array($mode, array('legacy', 'shadow', 'native'), true) ? $mode : 'legacy';
		}

		public static function explainDocuments($requestId, $query = '', $limit = 12)
		{
			$request = self::find($requestId);
			if (!$request) { return array(); }

			$query = trim((string) $query);
			$limit = max(1, min(20, (int) $limit));
			$sql = 'SELECT Id,document_title,document_alias,document_status,document_deleted'
				. ' FROM ' . ContentTables::table('documents') . ' WHERE rubric_id=%i';
			$args = array((int) $request->rubric_id);
			if ($query !== '') {
				$sql .= ' AND (document_title LIKE %ss OR document_alias LIKE %ss OR Id=%i)';
				$args[] = $query;
				$args[] = $query;
				$args[] = (int) $query;
			}

			$sql .= ' ORDER BY document_changed DESC,Id DESC LIMIT ' . $limit;
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();
			$result = array();
			foreach ($rows ?: array() as $row) {
				$result[] = array(
					'id' => (int) $row['Id'],
					'title' => htmlspecialchars_decode((string) $row['document_title'], ENT_QUOTES),
					'alias' => (string) $row['document_alias'],
					'status' => (int) $row['document_status'],
					'deleted' => (int) $row['document_deleted'],
				);
			}

			return $result;
		}

		protected static function rubricFieldIds($rubricId)
		{
			$result = array();
			foreach (self::rubricFields((int) $rubricId) as $field) {
				$result[] = (int) $field->Id;
			}

			return $result;
		}

		public static function groupsFlat($requestId)
		{
			return DB::query(
				'SELECT * FROM ' . self::groups() . '
             WHERE request_id = %i ORDER BY parent_id ASC, group_position ASC, Id ASC',
				(int) $requestId
			)->getAll();
		}

		public static function conditionTree($requestId)
		{
			$groups = self::groupsFlat($requestId);
			if (!$groups) {
				self::createRootGroup($requestId);
				$groups = self::groupsFlat($requestId);
			}

			$nodes = array();
			foreach ($groups as $group) {
				$item = (array) $group;
				$item['conditions'] = array();
				$item['children'] = array();
				$item['depth'] = 0;
				$nodes[(int) $group->Id] = $item;
			}

			foreach (self::conditions($requestId) as $condition) {
				$groupId = isset($condition->condition_group_id) ? (int) $condition->condition_group_id : 0;
				if (!isset($nodes[$groupId])) {
					$groupId = self::rootGroupId($requestId);
				}

				if (isset($nodes[$groupId])) {
					$nodes[$groupId]['conditions'][] = self::conditionPresentation((array) $condition);
				}
			}

			$roots = array();
			foreach (array_keys($nodes) as $groupId) {
				$parentId = (int) $nodes[$groupId]['parent_id'];
				if ($parentId > 0 && isset($nodes[$parentId])) {
					$nodes[$groupId]['depth'] = min(4, (int) $nodes[$parentId]['depth'] + 1);
					$nodes[$parentId]['children'][] =& $nodes[$groupId];
				} else {
					$roots[] =& $nodes[$groupId];
				}
			}

			return $roots ? $roots[0] : array();
		}

		public static function rootGroupId($requestId)
		{
			$id = (int) DB::query(
				'SELECT Id FROM ' . self::groups() . ' WHERE request_id = %i AND parent_id = 0 ORDER BY Id LIMIT 1',
				(int) $requestId
			)->getValue();
			return $id > 0 ? $id : self::createRootGroup($requestId);
		}

		protected static function createRootGroup($requestId)
		{
			DB::Insert(self::groups(), array(
				'request_id' => (int) $requestId,
				'parent_id' => 0,
				'group_title' => '',
				'group_operator' => 'AND',
				'group_position' => 0,
			));
			return DB::insertId();
		}

		public static function findGroup($id)
		{
			return DB::query('SELECT * FROM ' . self::groups() . ' WHERE Id = %i LIMIT 1', (int) $id)->getObject();
		}

		public static function groupSave($requestId, array $data)
		{
			$id = isset($data['id']) ? (int) $data['id'] : 0;
			$parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;
			$operator = isset($data['group_operator']) && $data['group_operator'] === 'OR' ? 'OR' : 'AND';
			$title = trim(isset($data['group_title']) ? (string) $data['group_title'] : '');
			$title = function_exists('mb_substr') ? mb_substr($title, 0, 100) : substr($title, 0, 100);

			if ($id > 0) {
				$group = self::findGroup($id);
				if (!$group || (int) $group->request_id !== (int) $requestId) {
					return 0;
				}

				DB::Update(self::groups(), array(
					'group_title' => $title,
					'group_operator' => $operator,
				), 'Id = %i', $id);
				self::touch($requestId);
				return $id;
			}

			$parent = self::findGroup($parentId);
			if (!$parent || (int) $parent->request_id !== (int) $requestId || self::groupDepth($parentId) >= 3) {
				return 0;
			}

			$position = (int) DB::query(
				'SELECT COALESCE(MAX(group_position), -1) + 1 FROM ' . self::groups() . '
             WHERE request_id = %i AND parent_id = %i',
				(int) $requestId, $parentId
			)->getValue();
			DB::Insert(self::groups(), array(
				'request_id' => (int) $requestId,
				'parent_id' => $parentId,
				'group_title' => $title,
				'group_operator' => $operator,
				'group_position' => $position,
			));
			$newId = DB::insertId();
			self::touch($requestId);
			return $newId;
		}

		protected static function groupDepth($id)
		{
			$depth = 0;
			$seen = array();
			while ($id > 0 && $depth < 10 && !isset($seen[$id])) {
				$seen[$id] = true;
				$group = self::findGroup($id);
				if (!$group || (int) $group->parent_id === 0) {
					break;
				}

				$id = (int) $group->parent_id;
				$depth++;
			}

			return $depth;
		}

		public static function groupDelete($requestId, $id)
		{
			$group = self::findGroup($id);
			if (!$group || (int) $group->request_id !== (int) $requestId || (int) $group->parent_id === 0) {
				return false;
			}

			$parentId = (int) $group->parent_id;
			DB::Update(self::cond(), array('condition_group_id' => $parentId), 'request_id = %i AND condition_group_id = %i', (int) $requestId, (int) $id);
			DB::Update(self::groups(), array('parent_id' => $parentId), 'request_id = %i AND parent_id = %i', (int) $requestId, (int) $id);
			DB::Delete(self::groups(), 'Id = %i', (int) $id);
			self::touch($requestId);
			return true;
		}

		public static function findCondition($id)
		{
			return DB::query('SELECT * FROM ' . self::cond() . ' WHERE Id = %i LIMIT 1', (int) $id)->getObject();
		}

		public static function conditionSave($requestId, array $data)
		{
			$request = self::find($requestId);
			if (!$request) { throw new \InvalidArgumentException('Запрос не найден.'); }
			$fieldId = (int) (isset($data['condition_field_id']) ? $data['condition_field_id'] : 0);
			$fieldIds = self::rubricFieldIds((int) $request->rubric_id);
			if ($fieldId <= 0 || !in_array($fieldId, $fieldIds, true)) {
				throw new \InvalidArgumentException('Выберите поле текущей рубрики.');
			}

			$compare = (string) (isset($data['condition_compare']) ? $data['condition_compare'] : '==');
			if (!array_key_exists($compare, self::compareOptions())) {
				throw new \InvalidArgumentException('Выберите поддерживаемое сравнение.');
			}

			$valueFields = self::conditionValueFields($data);
			//-- Позиция управляется drag-and-drop (reorder), в форме её нет.
			$fields = array(
				'condition_field_id' => $fieldId,
				'condition_compare'  => $compare,
				'condition_join'     => (isset($data['condition_join']) && $data['condition_join'] === 'OR') ? 'OR' : 'AND',
				'condition_status'   => !empty($data['condition_status']) ? '1' : '0',
			);
			$fields += $valueFields;

			$groupId = isset($data['condition_group_id']) ? (int) $data['condition_group_id'] : 0;
			$group = self::findGroup($groupId);
			$fields['condition_group_id'] = $group && (int) $group->request_id === (int) $requestId
				? $groupId
				: self::rootGroupId($requestId);

			$id = (int) (isset($data['id']) ? $data['id'] : 0);
			if ($id > 0) {
				$condition = self::findCondition($id);
				if (!$condition || (int) $condition->request_id !== (int) $requestId) {
					return 0;
				}

				DB::Update(self::cond(), $fields, 'Id = %i AND request_id = %i', $id, (int) $requestId);
				self::touch($requestId);
				return $id;
			}

			//-- новое условие — в конец
			$fields['request_id'] = (int) $requestId;
			$fields['condition_position'] = (int) DB::query(
				'SELECT COALESCE(MAX(condition_position), -1) + 1 FROM ' . self::cond() . ' WHERE request_id = %i AND condition_group_id = %i',
				(int) $requestId, (int) $fields['condition_group_id']
			)->getValue();
			DB::Insert(self::cond(), $fields);
			$newId = DB::insertId();
			self::touch($requestId);
			return $newId;
		}

		protected static function conditionValueFields(array $data)
		{
			$source = isset($data['condition_value_source']) ? strtolower(trim((string) $data['condition_value_source'])) : 'literal';
			if ($source !== 'input') {
				return array(
					'condition_value' => (string) (isset($data['condition_value']) ? $data['condition_value'] : ''),
					'condition_value_source' => $source === 'legacy' ? 'legacy' : 'literal',
					'condition_value_key' => '',
					'condition_value_config' => null,
				);
			}

			$key = isset($data['condition_value_key']) ? trim((string) $data['condition_value_key']) : '';
			if (RequestConditionValue::tag($key) === '') {
				throw new \InvalidArgumentException('Alias публичного параметра должен начинаться с буквы или _.');
			}

			$mode = isset($data['condition_value_mode']) ? (string) $data['condition_value_mode'] : 'string_scalar';
			$constant = isset($data['condition_value_constant']) ? $data['condition_value_constant'] : '';
			$descriptor = self::descriptorForMode($mode, $key, $constant);
			return array(
				'condition_value' => RequestConditionValue::tag($key),
				'condition_value_source' => 'input',
				'condition_value_key' => $key,
				'condition_value_config' => RequestConditionValue::encodeConfig($descriptor),
			);
		}

		protected static function descriptorForMode($mode, $key, $constant)
		{
			$modes = array(
				'string_scalar' => array('cast' => 'string', 'shape' => 'scalar', 'transform' => 'plain'),
				'integer_scalar' => array('cast' => 'integer', 'shape' => 'scalar', 'transform' => 'plain'),
				'decimal_scalar' => array('cast' => 'decimal', 'shape' => 'scalar', 'transform' => 'plain'),
				'string_list' => array('cast' => 'string', 'shape' => 'list', 'transform' => 'plain'),
				'integer_list' => array('cast' => 'integer', 'shape' => 'list', 'transform' => 'plain'),
				'integer_range' => array('cast' => 'integer', 'shape' => 'range', 'transform' => 'plain'),
				'decimal_range' => array('cast' => 'decimal', 'shape' => 'range', 'transform' => 'plain'),
				'integer_member' => array('cast' => 'integer', 'shape' => 'scalar', 'transform' => 'pipe_member'),
				'integer_member_list' => array('cast' => 'integer', 'shape' => 'list', 'transform' => 'pipe_member'),
				'presence_constant' => array('cast' => 'string', 'shape' => 'presence', 'transform' => 'plain'),
			);
			if (!isset($modes[$mode])) { throw new \InvalidArgumentException('Неизвестный способ обработки параметра.'); }
			$modes[$mode]['source'] = 'input';
			$modes[$mode]['key'] = $key;
			$modes[$mode]['constant'] = $mode === 'presence_constant' ? (string) $constant : '';
			if ($mode === 'presence_constant' && trim((string) $constant) === '') {
				throw new \InvalidArgumentException('Укажите значение константы.');
			}

			return $modes[$mode];
		}

		protected static function conditionPresentation(array $condition)
		{
			$descriptor = RequestConditionValue::descriptor($condition);
			if ($descriptor !== null) {
				$condition['value_source_ui'] = 'input';
				$condition['value_key_ui'] = $descriptor['key'];
				$condition['value_mode_ui'] = self::modeForDescriptor($descriptor);
				$condition['value_constant_ui'] = $descriptor['constant'];
				return $condition;
			}

			$value = isset($condition['condition_value']) ? (string) $condition['condition_value'] : '';
			$condition['value_source_ui'] = preg_match('/<\?(?:php|=)?|\?>/i', $value) ? 'legacy' : 'literal';
			$condition['value_key_ui'] = '';
			$condition['value_mode_ui'] = 'string_scalar';
			$condition['value_constant_ui'] = '';
			return $condition;
		}

		protected static function modeForDescriptor(array $descriptor)
		{
			if ($descriptor['shape'] === 'presence') { return 'presence_constant'; }
			if ($descriptor['transform'] === 'pipe_member') {
				return $descriptor['shape'] === 'list' ? 'integer_member_list' : 'integer_member';
			}

			return $descriptor['cast'] . '_' . $descriptor['shape'];
		}

		public static function conditionDelete($id)
		{
			$condition = self::findCondition($id);
			DB::Delete(self::cond(), 'Id = %i', (int) $id);
			if ($condition) { self::touch((int) $condition->request_id); }
		}

		/** Переупорядочить условия запроса по массиву Id (drag-and-drop). */
		public static function reorderConditions($requestId, array $items)
		{
			$positions = array();
			foreach ($items as $item) {
				$id = is_array($item) && isset($item['id']) ? (int) $item['id'] : (int) $item;
				$groupId = is_array($item) && isset($item['group_id']) ? (int) $item['group_id'] : self::rootGroupId($requestId);
				if ($id <= 0) {
					continue;
				}

				$group = self::findGroup($groupId);
				if (!$group || (int) $group->request_id !== (int) $requestId) {
					continue;
				}

				if (!isset($positions[$groupId])) {
					$positions[$groupId] = 0;
				}

				DB::Update(self::cond(), array(
					'condition_group_id' => $groupId,
					'condition_position' => $positions[$groupId]++,
				), 'Id = %i AND request_id = %i', $id, (int) $requestId);
			}

			self::touch($requestId);
		}

		protected static function touch($requestId)
		{
			$request = self::find($requestId);
			$now = time();
			DB::Update(self::req(), array(
				'request_changed' => $now,
				'request_changed_elements' => $now,
				'request_where_cond' => '',
			), 'Id = %i', (int) $requestId);
			\App\Content\Requests\NativeRequestPlanCompiler::persist($requestId);
			FileCacheInvalidator::request($requestId, $request ? $request->request_alias : '');
		}
	}
