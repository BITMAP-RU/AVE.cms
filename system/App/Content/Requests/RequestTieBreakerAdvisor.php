<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestTieBreakerAdvisor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Finds an ID direction that preserves the complete current Legacy sequence. */
	class RequestTieBreakerAdvisor
	{
		public function recommend($requestId)
		{
			$requestId = (int) $requestId;
			$basePlan = NativeRequestPlanCompiler::compile($requestId);
			if (empty($basePlan['eligible'])) {
				return array(
					'status' => 'unsupported',
					'direction' => '',
					'reasons' => isset($basePlan['reasons']) ? $basePlan['reasons'] : array(),
				);
			}

			if (!empty($basePlan['deterministic_order'])) {
				return array(
					'status' => 'already_deterministic',
					'direction' => '',
					'reasons' => array(),
				);
			}

			$comparator = new RequestExecutorComparator();
			foreach (array('ASC', 'DESC') as $direction) {
				$plan = NativeRequestPlanCompiler::compile($requestId, array(
					'request_order_tiebreaker' => $direction,
				));
				$comparison = $comparator->comparePlan($requestId, $plan);
				if (!empty($comparison['matched'])) {
					return array(
						'status' => 'recommended',
						'direction' => $direction,
						'legacy_fingerprint' => RequestExecutorComparator::resultFingerprint($comparison['legacy']),
						'total' => (int) $comparison['legacy']['total'],
						'reasons' => array(),
					);
				}
			}

			return array(
				'status' => 'no_match',
				'direction' => '',
				'reasons' => array('Ни ID ASC, ни ID DESC не сохраняют текущую последовательность Legacy.'),
			);
		}
	}
