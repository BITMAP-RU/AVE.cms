<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/QueryDebug.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use App\Helpers\Number;

	/**
	 * Класс для отладки SQL запросов
	 * Показывает информацию о том, был ли запрос взят из кеша или выполнен напрямую
	 */
	class QueryDebug
	{
		/**
		 * Список всех отладочных сообщений
		 * @var array
		 */
		protected static $_debug_list = [];

		/**
		 * Включен ли дебаг
		 * @var bool
		 */
		protected static $_enabled = false;

		/**
		 * Инициализация дебага
		 * @param bool $enabled Включить/выключить дебаг
		 */
		public static function init($enabled = false)
		{
			self::$_enabled = $enabled;
		}

		/**
		 * Проверить, включен ли дебаг
		 * @return bool
		 */
		public static function isEnabled()
		{
			return self::$_enabled;
		}

		/**
		 * Зарегистрировать попытку получения из кеша
		 * @param string $query Хешированный запрос
		 * @param bool $hit true если кеш найден, false если промах
		 * @param float $time Время выполнения в секундах
		 */
		public static function logCacheHit(string $query, bool $hit, float $time)
		{
			if (!self::$_enabled) {
				return;
			}

			self::$_debug_list[] = [
				'type' => $hit ? 'cache_hit' : 'cache_miss',
				'query' => QueryLogger::queryTrim($query),
				'time' => Number::numFormat($time * 1000, 2, ',', ''),
				'unit' => 'ms',
				'source' => $hit ? 'Кеш' : 'БД'
			];
		}

		/**
		 * Зарегистрировать выполнение запроса напрямую ( без кеша )
		 * @param string $query Выполненный запрос
		 * @param float $time Время выполнения в секундах
		 * @param int $affected_rows Количество затронутых строк
		 */
		public static function logDirectQuery(string $query, float $time, int $affected_rows = 0)
		{
			if (!self::$_enabled) {
				return;
			}

			self::$_debug_list[] = [
				'type' => 'direct',
				'query' => QueryLogger::queryTrim($query),
				'time' => Number::numFormat($time * 1000, 2, ',', ''),
				'unit' => 'ms',
				'source' => 'БД',
				'affected' => $affected_rows
			];
		}

		/**
		 * Зарегистрировать сохранение в кеш
		 * @param string $query Сохраненный запрос
		 * @param float $time Время сохранения
		 * @param int $ttl Время жизни кеша в секундах
		 */
		public static function logCacheSave(string $query, float $time, int $ttl)
		{
			if (!self::$_enabled) {
				return;
			}

			self::$_debug_list[] = [
				'type' => 'cache_save',
				'query' => QueryLogger::queryTrim($query),
				'time' => Number::numFormat($time * 1000, 2, ',', ''),
				'unit' => 'ms',
				'source' => 'Кеш (сохранено)',
				'ttl' => $ttl . 's'
			];
		}

		/**
		 * Получить все отладочные сообщения
		 * @return array
		 */
		public static function getDebugList()
		{
			return self::$_debug_list;
		}

		/**
		 * Получить количество запросов
		 * @return array ['total' => int, 'from_cache' => int, 'from_db' => int, 'cached_saved' => int]
		 */
		public static function getStats()
		{
			$stats = [
				'total' => 0,
				'from_cache' => 0,
				'from_db' => 0,
				'cached_saved' => 0
			];

			foreach (self::$_debug_list as $item) {
				$stats['total']++;
				switch ($item['type']) {
					case 'cache_hit':
					case 'cache_save':
						$stats['from_cache']++;
						break;
					case 'direct':
						$stats['from_db']++;
						break;
				}

				if ($item['type'] === 'cache_save') {
					$stats['cached_saved']++;
				}
			}

			return $stats;
		}

		/**
		 * Очистить список отладки
		 */
		public static function clear()
		{
			self::$_debug_list = [];
		}

		/**
		 * Сформировать HTML отчет для отладки
		 * @return string
		 */
		public static function getHtmlReport()
		{
			if (!self::$_enabled || empty(self::$_debug_list)) {
				return '';
			}

			$stats = self::getStats();
			$totalTime = 0;
			foreach (self::$_debug_list as $item) {
				$totalTime += floatval($item['time']);
			}

			$html = '<div id="sql-debug" style="margin-top: 20px; border: 1px solid #ddd; border-radius: 5px; overflow: hidden;">';
			$html .= '<div style="background: #f5f5f5; padding: 10px; border-bottom: 1px solid #ddd;">';
			$html .= '<strong>SQL Debug Report</strong> | ';
			$html .= 'Всего запросов: ' . $stats['total'] . ' | ';
			$html .= 'Из кеша: ' . $stats['from_cache'] . ' | ';
			$html .= 'Напрямую: ' . $stats['from_db'] . ' | ';
			$html .= 'Кеш сохранено: ' . $stats['cached_saved'] . ' | ';
			$html .= 'Общее время: ' . Number::numFormat($totalTime, 2, ',', '') . ' ms';
			$html .= '</div>';

			$html .= '<table style="width: 100%; border-collapse: collapse; font-family: monospace; font-size: 12px;">';
			$html .= '<thead><tr style="background: #e8e8e8;">';
			$html .= '<th style="padding: 8px; border-bottom: 2px solid #ddd; text-align: left;">Тип</th>';
			$html .= '<th style="padding: 8px; border-bottom: 2px solid #ddd; text-align: left;">Источник</th>';
			$html .= '<th style="padding: 8px; border-bottom: 2px solid #ddd; text-align: right;">Время</th>';
			$html .= '<th style="padding: 8px; border-bottom: 2px solid #ddd; text-align: left;">Запрос</th>';
			$html .= '</tr></thead>';
			$html .= '<tbody>';

			foreach (self::$_debug_list as $item) {
				$bgColor = $item['source'] === 'Кеш' ? '#e8f5e8' : '#f5f5f5';
				if ($item['source'] === 'Кеш (сохранено)') {
					$bgColor = '#fff3e0';
				}

				$html .= '<tr style="background: ' . $bgColor . ';">';
				$html .= '<td style="padding: 6px 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['type']) . '</td>';
				$html .= '<td style="padding: 6px 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['source']) . '</td>';
				$html .= '<td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right;">' . $item['time'] . ' ' . $item['unit'] . '</td>';
				$html .= '<td style="padding: 6px 8px; border-bottom: 1px solid #eee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' . htmlspecialchars($item['query']) . '">';
				$html .= htmlspecialchars($item['query']);
				if (isset($item['ttl'])) {
					$html .= ' <span style="color: #666;">[' . $item['ttl'] . ']</span>';
				}

				if (isset($item['affected'])) {
					$html .= ' <span style="color: #666;">(' . $item['affected'] . ' rows)</span>';
				}

				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';

			return $html;
		}
	}
