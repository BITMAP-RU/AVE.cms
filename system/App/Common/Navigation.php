<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Navigation.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Общий реестр навигации.
	 *
	 * Модули объявляют свои пункты меню через Navigation::add(), а layout получает
	 * готовый, отфильтрованный по правам список через Navigation::forCurrentUser().
	 * Класс не знает о конкретных модулях: проверка прав передаётся колбэком.
	 */
	class Navigation
	{
		/** @var array code => item */
		protected static $items = [];

		protected function __construct()
		{
			//
		}

		/**
		 * Добавить пункт меню.
		 *
		 * Ключи item:
		 *   code        — уникальный код пункта (обязателен);
		 *   label       — подпись (обязательна);
		 *   url         — ссылка;
		 *   icon        — имя иконки (опционально);
		 *   permission  — код права для показа ('' — виден всегда);
		 *   group       — код группы (для grouped());
		 *   sort_order  — порядок;
		 *   parent      — код родительского пункта (для подменю);
		 *   match       — префикс(ы) пути для подсветки активного пункта (string|array);
		 *   match_regex — регулярное выражение или список выражений для динамических URL;
		 *   exact       — подсвечивать только при точном совпадении url;
		 *   badge       — число или callable, возвращающий число (например, счётчик).
		 *   visible     — bool или callable; позволяет скрыть пункт по состоянию системы.
		 */
		public static function add(array $item)
		{
			if (empty($item['code']) || !isset($item['label'])) {
				return;
			}

			$item = array_merge([
				'code' => '',
				'label' => '',
				'url' => '#',
				'icon' => '',
				'permission' => '',
				'group' => '',
				'sort_order' => 100,
				'parent' => '',
				'match' => [],
				'match_regex' => [],
				'exact' => false,
				'badge' => null,
				'visible' => true,
			], $item);

			if (is_string($item['match'])) {
				$item['match'] = $item['match'] === '' ? [] : [$item['match']];
			}

			if (is_string($item['match_regex'])) {
				$item['match_regex'] = $item['match_regex'] === '' ? [] : [$item['match_regex']];
			}

			$item['sort_order'] = (int) $item['sort_order'];

			self::$items[(string) $item['code']] = $item;
		}

		/** Добавить несколько пунктов сразу. */
		public static function addMany(array $items)
		{
			foreach ($items as $item) {
				if (is_array($item)) {
					self::add($item);
				}
			}
		}

		/** Все зарегистрированные пункты, отсортированные по sort_order, затем label. */
		public static function all()
		{
			$items = array_values(self::$items);
			usort($items, function ($a, $b) {
				if ($a['sort_order'] === $b['sort_order']) {
					return strcmp((string) $a['label'], (string) $b['label']);
				}

				return $a['sort_order'] < $b['sort_order'] ? -1 : 1;
			});
			return $items;
		}

		/**
		 * Пункты, доступные текущему пользователю (badge уже вычислен в число).
		 *
		 * @param callable|null $can Проверка права: (string $code) => bool.
		 *                           По умолчанию — Permission::check.
		 */
		public static function forCurrentUser($can = null)
		{
			if (!is_callable($can)) {
				$can = ['App\\Common\\Permission', 'check'];
			}

			$result = [];
			foreach (self::all() as $item) {
				$visible = isset($item['visible']) ? $item['visible'] : true;
				if ((is_callable($visible) && !call_user_func($visible)) || (!is_callable($visible) && !$visible)) {
					continue;
				}

				if ($item['permission'] !== '' && !call_user_func($can, $item['permission'])) {
					continue;
				}

				$item['badge'] = self::resolveBadge($item['badge']);
				$result[] = $item;
			}

			return $result;
		}

		/** Доступные пользователю пункты, сгруппированные по ключу group. */
		public static function grouped($can = null)
		{
			$groups = [];
			foreach (self::forCurrentUser($can) as $item) {
				$groups[(string) $item['group']][] = $item;
			}

			return $groups;
		}

		/** Активен ли пункт для указанного пути запроса. */
		public static function isActive(array $item, $currentPath)
		{
			$currentPath = (string) $currentPath;
			$url = isset($item['url']) ? (string) $item['url'] : '';

			if (!empty($item['exact'])) {
				return $currentPath === $url;
			}

			if ($url !== '' && $currentPath === $url) {
				return true;
			}

			$prefixes = isset($item['match']) ? (array) $item['match'] : [];
			foreach ($prefixes as $prefix) {
				$prefix = (string) $prefix;
				if ($prefix !== '' && strpos($currentPath, $prefix) === 0) {
					return true;
				}
			}

			$patterns = isset($item['match_regex']) ? (array) $item['match_regex'] : [];
			foreach ($patterns as $pattern) {
				$pattern = (string) $pattern;
				if ($pattern !== '' && @preg_match($pattern, $currentPath) === 1) {
					return true;
				}
			}

			return false;
		}

		protected static function resolveBadge($badge)
		{
			if (is_callable($badge)) {
				return (int) call_user_func($badge);
			}

			if (is_numeric($badge)) {
				return (int) $badge;
			}

			return 0;
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
