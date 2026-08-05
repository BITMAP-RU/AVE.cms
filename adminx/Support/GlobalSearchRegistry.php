<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/GlobalSearchRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Permission;
	use App\Common\Language;

	/**
	 * Реестр провайдеров административного поиска.
	 *
	 * Provider получает ($query, $limit) и возвращает список безопасных
	 * admin-relative результатов. Ошибка одного provider не ломает палитру.
	 */
	class GlobalSearchRegistry
	{
		protected static $providers = array();
		protected static $reported = array();

		public static function register($code, array $definition)
		{
			$code = self::code($code);
			$provider = isset($definition['provider']) ? $definition['provider'] : null;
			if ($code === '' || !is_callable($provider)) {
				return false;
			}

			self::$providers[$code] = array(
				'code' => $code,
				'provider' => $provider,
				'permission' => isset($definition['permission']) ? trim((string) $definition['permission']) : '',
				'priority' => isset($definition['priority']) ? (int) $definition['priority'] : 100,
				'limit' => max(1, min(30, isset($definition['limit']) ? (int) $definition['limit'] : 8)),
			);
			return true;
		}

		public static function search($query, $limit = 30)
		{
			$query = trim(preg_replace('/\s+/u', ' ', (string) $query));
			$limit = max(1, min(50, (int) $limit));
			if (mb_strlen($query, 'UTF-8') < 2) {
				return array();
			}

			$providers = array_values(self::$providers);
			usort($providers, function ($left, $right) {
				$order = (int) $left['priority'] <=> (int) $right['priority'];
				return $order !== 0 ? $order : strnatcasecmp((string) $left['code'], (string) $right['code']);
			});

			$items = array();
			foreach ($providers as $providerOrder => $definition) {
				if ($definition['permission'] !== '' && !Permission::check($definition['permission'])) {
					continue;
				}

				try {
					$rows = call_user_func(
						$definition['provider'],
						$query,
						min($definition['limit'], $limit)
					);
				} catch (\Throwable $e) {
					self::report($definition['code'], $e);
					continue;
				}

				$position = 0;
				foreach (is_array($rows) ? $rows : array() as $row) {
					$item = self::normalize($row, $definition['code']);
					if (!$item) {
						continue;
					}

					$item['_provider_order'] = $providerOrder;
					$item['_position'] = $position++;
					$items[] = $item;
				}
			}

			usort($items, function ($left, $right) {
				$provider = (int) $left['_provider_order'] <=> (int) $right['_provider_order'];
				if ($provider !== 0) {
					return $provider;
				}

				$score = (float) $right['score'] <=> (float) $left['score'];
				return $score !== 0 ? $score : ((int) $left['_position'] <=> (int) $right['_position']);
			});

			$items = array_slice($items, 0, $limit);
			foreach ($items as &$item) {
				unset($item['_provider_order'], $item['_position'], $item['score']);
			}

			unset($item);
			return $items;
		}

		public static function providers()
		{
			return array_keys(self::$providers);
		}

		public static function resetRuntime()
		{
			self::$providers = array();
			self::$reported = array();
		}

		protected static function normalize($row, $providerCode)
		{
			if (!is_array($row)) {
				return null;
			}

			$title = self::text(isset($row['title']) ? $row['title'] : '', 190);
			$url = self::localUrl(isset($row['url']) ? $row['url'] : '');
			if ($title === '' || $url === '') {
				return null;
			}

			$icon = trim((string) (isset($row['icon']) ? $row['icon'] : 'ti ti-search'));
			if (!preg_match('/^ti(?:\s+ti-[a-z0-9-]+)+$/', $icon)) {
				$icon = 'ti ti-search';
			}

			return array(
				'type' => self::code(isset($row['type']) ? $row['type'] : $providerCode),
				'group' => Language::translateSource(self::text(isset($row['group']) ? $row['group'] : 'Результаты', 80)),
				'title' => $title,
				'subtitle' => self::text(isset($row['subtitle']) ? $row['subtitle'] : '', 300),
				'url' => $url,
				'icon' => $icon,
				'score' => isset($row['score']) && is_numeric($row['score']) ? (float) $row['score'] : 0.0,
			);
		}

		protected static function localUrl($url)
		{
			$url = trim((string) $url);
			if ($url === ''
				|| $url[0] !== '/'
				|| strpos($url, '//') === 0
				|| strpos($url, '\\') !== false
				|| preg_match('/[\r\n\x00-\x1f]/', $url)) {
				return '';
			}

			return substr($url, 0, 1000);
		}

		protected static function code($value)
		{
			$value = strtolower(trim((string) $value));
			return substr(preg_replace('/[^a-z0-9_.-]+/', '_', $value), 0, 64);
		}

		protected static function text($value, $limit)
		{
			$value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');
			$value = trim(preg_replace('/\s+/u', ' ', $value));
			return mb_substr($value, 0, (int) $limit, 'UTF-8');
		}

		protected static function report($code, \Throwable $e)
		{
			if (isset(self::$reported[$code])) {
				return;
			}

			self::$reported[$code] = true;
			error_log('Global search provider ' . $code . ': ' . $e->getMessage());
		}
	}
