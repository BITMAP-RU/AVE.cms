<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/SplitTest.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Сравнение двух долей для A/B-инструментов.
	 *
	 * Общий калькулятор: им пользуются и эксперименты представлений, и варианты
	 * поп-апов. Чистые вычисления без обращений к базе и настройкам, поэтому
	 * пригоден для любого модуля, который считает показы и конверсии.
	 */
	class SplitTest
	{
		public static function analyze(array $variants, $minimumSample)
		{
			$minimumSample = max(50, min(1000000, (int) $minimumSample));
			$normalized = array();
			foreach ($variants as $variant) {
				$impressions = max(0, (int) (isset($variant['impressions']) ? $variant['impressions'] : 0));
				$conversions = max(0, min($impressions, (int) (isset($variant['conversions']) ? $variant['conversions'] : 0)));
				$variant['impressions'] = $impressions;
				$variant['conversions'] = $conversions;
				$variant['conversion_rate'] = $impressions > 0 ? round($conversions * 100 / $impressions, 2) : 0.0;
				$normalized[] = $variant;
			}

			$result = array(
				'state' => 'collecting',
				'label' => 'Данных мало',
				'description' => 'Эксперимент продолжает набирать заданную выборку.',
				'minimum_sample' => $minimumSample,
				'progress' => 0,
				'confidence' => 0.0,
				'p_value' => null,
				'winner_code' => '',
				'winner_title' => '',
				'difference_pp' => 0.0,
				'ci_low' => null,
				'ci_high' => null,
			);
			if (count($normalized) < 2) { return $result; }

			$minimumSeen = null;
			foreach ($normalized as $variant) {
				$minimumSeen = $minimumSeen === null ? $variant['impressions'] : min($minimumSeen, $variant['impressions']);
			}

			$result['progress'] = min(100, (int) floor(100 * $minimumSeen / $minimumSample));
			if ($minimumSeen < $minimumSample) {
				$result['description'] = 'Минимум ' . $minimumSample . ' показов на вариант. Сейчас набрано от ' . $minimumSeen . '.';
				return $result;
			}

			$control = $normalized[0];
			$alternative = $normalized[1];
			foreach (array_slice($normalized, 2) as $variant) {
				if ($variant['conversion_rate'] > $alternative['conversion_rate']) { $alternative = $variant; }
			}

			$left = $control['conversion_rate'] >= $alternative['conversion_rate'] ? $control : $alternative;
			$right = $left === $control ? $alternative : $control;
			$p1 = $left['conversions'] / $left['impressions'];
			$p2 = $right['conversions'] / $right['impressions'];
			$difference = $p1 - $p2;
			$pooled = ($left['conversions'] + $right['conversions']) / ($left['impressions'] + $right['impressions']);
			$pooledError = sqrt(max(0.0, $pooled * (1 - $pooled) * (1 / $left['impressions'] + 1 / $right['impressions'])));
			$intervalError = sqrt(max(0.0, ($p1 * (1 - $p1) / $left['impressions']) + ($p2 * (1 - $p2) / $right['impressions'])));
			$z = $pooledError > 0 ? $difference / $pooledError : 0.0;
			$pValue = $pooledError > 0 ? 2 * (1 - self::normalCdf(abs($z))) : 1.0;
			$adjustedP = min(1.0, $pValue * max(1, count($normalized) - 1));
			$result['p_value'] = round($adjustedP, 6);
			$result['confidence'] = round((1 - $adjustedP) * 100, 2);
			$result['difference_pp'] = round($difference * 100, 2);
			$result['ci_low'] = round(($difference - 1.959964 * $intervalError) * 100, 2);
			$result['ci_high'] = round(($difference + 1.959964 * $intervalError) * 100, 2);
			$result['progress'] = 100;

			$normalApproximation = min(
				$left['conversions'], $left['impressions'] - $left['conversions'],
				$right['conversions'], $right['impressions'] - $right['conversions']
			) >= 5;
			if (!$normalApproximation || $adjustedP > 0.05 || $difference <= 0) {
				$result['state'] = 'inconclusive';
				$result['label'] = 'Разница в пределах погрешности';
				$result['description'] = !$normalApproximation
					? 'Показов достаточно, но событий пока мало для устойчивого вывода.'
					: 'Заданная выборка набрана, статистически подтверждённого преимущества нет.';
				return $result;
			}

			$result['state'] = 'winner';
			$result['winner_code'] = isset($left['variant_code']) ? (string) $left['variant_code'] : '';
			$result['winner_title'] = isset($left['title']) ? (string) $left['title'] : $result['winner_code'];
			$result['label'] = 'Вариант ' . $result['winner_code'] . ' лучше';
			$result['description'] = 'Преимущество ' . $result['difference_pp'] . ' п.п., достоверность ' . $result['confidence'] . '%. Тест можно остановить после проверки бизнес-метрики.';
			return $result;
		}

		protected static function normalCdf($value)
		{
			$value = (float) $value;
			$t = 1 / (1 + 0.2316419 * abs($value));
			$density = 0.3989422804014327 * exp(-0.5 * $value * $value);
			$tail = $density * $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));
			return $value >= 0 ? 1 - $tail : $tail;
		}
	}
