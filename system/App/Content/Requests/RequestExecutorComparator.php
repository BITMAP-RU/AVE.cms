<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestExecutorComparator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Frontend\RequestRenderer;
	use DB;

	/** Runs Legacy and Native side by side and reports differences without changing public output. */
	class RequestExecutorComparator
	{
		const VERIFY_LIMIT = 5000;

		public function compare($requestId, $limit = 20)
		{
			$requestId = (int) $requestId;
			$plan = NativeRequestPlanCompiler::compile($requestId);
			return $this->comparePlan($requestId, $plan, $limit);
		}

		/** Compare Legacy with an explicit candidate plan without persisting it. */
		public function comparePlan($requestId, array $plan, $limit = 20)
		{
			$requestId = (int) $requestId;
			$limit = max(self::VERIFY_LIMIT, max(1, (int) $limit));
			if (empty($plan['eligible'])) {
				return array(
					'status' => 'unsupported',
					'matched' => false,
					'reasons' => isset($plan['reasons']) ? $plan['reasons'] : array(),
					'plan' => $plan,
				);
			}

			$contexts = self::runtimeContexts($plan);
			$results = array();
			$verification = array();
			foreach ($contexts as $context) {
				$result = $this->compareContext($requestId, $plan, $limit, $context['input']);
				$results[] = $result;
				$verification[] = array(
					'label' => $context['label'],
					'keys' => array_keys($context['input']),
					'status' => $result['status'],
					'legacy_total' => isset($result['legacy']['total']) ? (int) $result['legacy']['total'] : null,
					'native_total' => isset($result['native']['total']) ? (int) $result['native']['total'] : null,
					'legacy_ids_hash' => isset($result['legacy']['ids']) ? self::idsHash($result['legacy']['ids']) : '',
					'native_ids_hash' => isset($result['native']['ids']) ? self::idsHash($result['native']['ids']) : '',
				);
			}

			$selected = $results[0];
			foreach ($results as $result) {
				if (in_array($result['status'], array('mismatch', 'order_diff', 'incomplete'), true)) {
					$selected = $result;
					break;
				}
			}

			$allMatched = true;
			foreach ($results as $result) {
				$allMatched = $allMatched && !empty($result['matched']);
			}

			$selected['matched'] = $allMatched;
			$selected['runtime_verification'] = $verification;
			return $selected;
		}

		protected static function idsHash(array $ids)
		{
			return hash('sha256', implode(',', array_map('intval', $ids)));
		}

		protected function compareContext($requestId, array $plan, $limit, array $runtimeInput)
		{
			$previousRequest = $_REQUEST;
			$_REQUEST = $runtimeInput;
			try {
				$legacy = (new RequestRenderer())->render($requestId, array(
					'RETURN_DATA' => 1,
					'LIMIT' => $limit,
					'START' => 0,
					'EXECUTOR' => 'legacy',
				));
				$native = (new NativeRequestExecutor())->execute($requestId, array(
					'LIMIT' => $limit,
					'START' => 0,
					'RUNTIME_INPUT' => $runtimeInput,
				), $plan);
			} finally {
				$_REQUEST = $previousRequest;
			}

			$legacyIds = self::ids(isset($legacy['rows']) ? $legacy['rows'] : array());
			$nativeIds = self::ids(isset($native['rows']) ? $native['rows'] : array());
			$missing = array_values(array_diff($legacyIds, $nativeIds));
			$extra = array_values(array_diff($nativeIds, $legacyIds));
			$totalsMatch = (int) $legacy['total'] === (int) $native['total'];
			$orderMatch = $legacyIds === $nativeIds;
			$setsMatch = !$missing && !$extra;
			$complete = max((int) $legacy['total'], (int) $native['total']) <= self::VERIFY_LIMIT;
			$deterministic = !empty($plan['deterministic_order']);
			$orderDiagnostics = !$orderMatch && $setsMatch
				? RequestOrderDiagnostics::explain($legacyIds, $nativeIds, $plan)
				: array();
			$status = !$complete
				? 'incomplete'
				: ($totalsMatch && $orderMatch
					? ($deterministic ? 'matched' : 'unstable')
					: ($totalsMatch && $setsMatch ? 'order_diff' : 'mismatch'));

			return array(
				'status' => $status,
				'matched' => $complete && $deterministic && $totalsMatch && $orderMatch,
				'complete' => $complete,
				'deterministic_order' => $deterministic,
				'checked' => min(self::VERIFY_LIMIT, max(count($legacyIds), count($nativeIds))),
				'totals_match' => $totalsMatch,
				'order_match' => $orderMatch,
				'legacy' => array(
					'total' => (int) $legacy['total'],
					'ids' => $legacyIds,
					'query_time' => (float) $legacy['query_time'],
					'sql' => (string) $legacy['sql'],
				),
				'native' => array(
					'total' => (int) $native['total'],
					'ids' => $nativeIds,
					'query_time' => (float) $native['query_time'],
					'sql' => (string) $native['sql'],
				),
				'missing_ids' => $missing,
				'extra_ids' => $extra,
				'order_diagnostics' => $orderDiagnostics,
				'reasons' => self::reasons($status, $orderDiagnostics),
				'plan' => $plan,
			);
		}

		protected static function runtimeContexts(array $plan)
		{
			$contexts = array(array('label' => 'Без входных значений', 'input' => array()));
			$all = array();
			foreach (isset($plan['runtime_sources']) ? $plan['runtime_sources'] : array() as $key => $source) {
				$value = self::runtimeProbe($plan, (string) $key, $source);
				$input = array((string) $key => $value);
				$contexts[] = array('label' => (string) $key, 'input' => $input);
				$all[(string) $key] = $value;
			}

			if (count($all) > 1) {
				$contexts[] = array('label' => 'Все входные значения', 'input' => $all);
			}

			return $contexts;
		}

		protected static function runtimeProbe(array $plan, $key, array $source)
		{
			$condition = self::runtimeCondition($plan, $key);
			$cast = isset($source['cast']) ? (string) $source['cast'] : '';
			$shape = isset($source['shape']) ? (string) $source['shape'] : 'scalar';
			if (!$condition || $cast === 'constant' || $shape === 'presence') {
				if (isset($source['source'])) { return RequestConditionValue::probe($source); }
				return RequestRuntimeValue::probe($source);
			}

			$fieldId = (int) $condition['field_id'];
			$rows = DB::query(
				'SELECT rf.field_value,rf.field_number_value FROM %b rf'
					. ' INNER JOIN %b d ON d.Id=rf.document_id'
					. ' WHERE rf.rubric_field_id=%i AND d.rubric_id=%i AND d.document_deleted=%s'
					. ' AND d.document_status=%s ORDER BY rf.document_id ASC LIMIT 100',
				ContentTables::table('document_fields'),
				ContentTables::table('documents'),
				$fieldId,
				(int) $plan['rubric_id'],
				'0',
				'1'
			)->getAll();

			foreach ($rows ?: array() as $row) {
				if ($cast === 'integer' || $cast === 'decimal') {
					$value = is_numeric($row['field_number_value'])
						? (string) $row['field_number_value']
						: (string) $row['field_value'];
					if (is_numeric($value)) {
						$value = $cast === 'integer' ? (string) (int) $value : (string) (float) $value;
						if ($shape === 'range') { return array('min' => $value, 'max' => $value); }
						if ($shape === 'list') { return array($value); }
						return $value;
					}
				}

				$pipe = isset($source['transform']) && $source['transform'] === 'pipe_member'
					? true
					: strpos($cast, 'pipe_') === 0;
				$value = self::textProbe((string) $row['field_value'], $pipe);
				if ($value !== '') {
					if ($shape === 'range') { return array('min' => $value, 'max' => $value); }
					if ($shape === 'list') { return array($value); }
					return $value;
				}
			}

			if (isset($source['source'])) { return RequestConditionValue::probe($source); }
			return RequestRuntimeValue::probe($source);
		}

		protected static function runtimeCondition(array $plan, $key)
		{
			foreach (isset($plan['groups']) ? $plan['groups'] : array() as $group) {
				foreach (isset($group['conditions']) ? $group['conditions'] : array() as $condition) {
					if (isset($condition['value_source']['key']) && $condition['value_source']['key'] === $key) {
						return $condition;
					}
				}
			}

			return null;
		}

		protected static function textProbe($value, $pipe)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return '';
			}

			if ($pipe && preg_match('/\|([^|\r\n]{1,100})\|/', $value, $match) === 1) {
				return trim((string) $match[1]);
			}

			if ($pipe) {
				return '';
			}

			return function_exists('mb_substr') ? mb_substr($value, 0, 64, 'UTF-8') : substr($value, 0, 64);
		}

		protected static function reasons($status, array $orderDiagnostics)
		{
			if ($status === 'unstable') {
				return array('Состав совпадает, но сортировка не заканчивается уникальным ID. Запрос оставлен в Legacy, чтобы порядок на сайте не менялся.');
			}

			if ($status === 'order_diff') {
				return !empty($orderDiagnostics['keys_equal'])
					? array('Первая перестановка произошла при одинаковом значении сортировки. Нужен финальный ID.')
					: array('Порядок различается на разных значениях сортировки. Проверьте настройки и Native-компилятор.');
			}

			return array();
		}

		public static function resultFingerprint(array $result)
		{
			$total = isset($result['total']) ? (int) $result['total'] : 0;
			$ids = isset($result['ids']) && is_array($result['ids']) ? array_values($result['ids']) : array();
			return hash('sha256', json_encode(array($total, $ids), JSON_UNESCAPED_SLASHES));
		}

		protected static function ids(array $rows)
		{
			$result = array();
			foreach ($rows as $row) {
				$id = is_object($row) && isset($row->Id)
					? (int) $row->Id
					: (is_array($row) && isset($row['Id']) ? (int) $row['Id'] : 0);
				if ($id > 0) {
					$result[] = $id;
				}
			}

			return $result;
		}
	}
