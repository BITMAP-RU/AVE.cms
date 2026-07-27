<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/InterfaceSettings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ModuleSettings;
	use App\Common\Settings;

	/** Adminx-only order and visibility settings for navigation and dashboard. */
	class InterfaceSettings
	{
		const MODULE = 'adminx_interface';

		public static function navigationCatalog(array $items)
		{
			return self::navigationHierarchy(self::arrange('navigation', self::prepare($items, 'code'), false));
		}

		public static function applyNavigation(array $items)
		{
			return self::navigationHierarchy(self::arrange('navigation', self::prepare($items, 'code'), true));
		}

		public static function dashboardCatalog(array $items)
		{
			return self::arrange('dashboard', self::prepare($items, 'layout_code'), false);
		}

		public static function applyDashboard(array $items)
		{
			return self::arrange('dashboard', self::prepare($items, 'layout_code'), true);
		}

		public static function saveNavigation(array $rows, array $available)
		{
			return self::save('navigation', $rows, $available);
		}

		public static function saveDashboard(array $rows, array $available)
		{
			$saved = self::save('dashboard', $rows, $available);
			$byCode = array();
			foreach ($available as $item) {
				if (!empty($item['layout_code'])) {
					$byCode[(string) $item['layout_code']] = $item;
				}
			}

			$moduleVisibility = array();
			foreach ($saved as $row) {
				$code = (string) $row['code'];
				if (isset($byCode[$code]) && !empty($byCode[$code]['module_code'])) {
					$moduleCode = (string) $byCode[$code]['module_code'];
					$moduleVisibility[$moduleCode] = !empty($moduleVisibility[$moduleCode]) || !empty($row['visible']);
				}
			}

			foreach ($moduleVisibility as $moduleCode => $visible) {
				ModuleSettings::set('adminx_dashboard_enabled', $visible, $moduleCode, 'bool');
			}

			return $saved;
		}

		protected static function prepare(array $items, $codeKey)
		{
			$prepared = array();
			foreach ($items as $index => $item) {
				if (!is_array($item) || empty($item[$codeKey])) {
					continue;
				}

				$item['layout_code'] = (string) $item[$codeKey];
				if (!isset($item['sort_order'])) {
					$item['sort_order'] = ($index + 1) * 10;
				}

				if (!isset($item['default_visible'])) {
					$item['default_visible'] = true;
				}

				$prepared[] = $item;
			}

			return $prepared;
		}

		protected static function arrange($scope, array $items, $visibleOnly)
		{
			$config = self::config($scope);
			$position = array();
			$visibility = array();
			foreach ($config as $index => $row) {
				$position[$row['code']] = $index;
				$visibility[$row['code']] = !empty($row['visible']);
			}

			foreach ($items as &$item) {
				$code = (string) $item['layout_code'];
				$item['layout_visible'] = isset($visibility[$code])
					? $visibility[$code]
					: !empty($item['default_visible']);
				$item['_layout_position'] = isset($position[$code]) ? $position[$code] : null;
			}

			unset($item);

			usort($items, function ($a, $b) {
				$ap = $a['_layout_position'];
				$bp = $b['_layout_position'];
				if ($ap !== null || $bp !== null) {
					if ($ap === null) return 1;
					if ($bp === null) return -1;
					if ($ap !== $bp) return $ap < $bp ? -1 : 1;
				}

				$as = isset($a['sort_order']) ? (int) $a['sort_order'] : 1000;
				$bs = isset($b['sort_order']) ? (int) $b['sort_order'] : 1000;
				if ($as !== $bs) return $as < $bs ? -1 : 1;
				return strcmp((string) $a['layout_code'], (string) $b['layout_code']);
			});

			foreach ($items as &$item) {
				unset($item['_layout_position']);
			}

			unset($item);

			if (!$visibleOnly) {
				return $items;
			}

			return array_values(array_filter($items, function ($item) {
				return !empty($item['layout_visible']);
			}));
		}

		protected static function navigationHierarchy(array $items)
		{
			$roots = array();
			$children = array();
			foreach ($items as $item) {
				$parent = isset($item['parent']) ? (string) $item['parent'] : '';
				if ($parent === '') {
					$roots[] = $item;
				} else {
					if (!isset($children[$parent])) {
						$children[$parent] = array();
					}

					$children[$parent][] = $item;
				}
			}

			$result = array();
			foreach ($roots as $root) {
				$result[] = $root;
				$code = (string) $root['layout_code'];
				if (isset($children[$code])) {
					foreach ($children[$code] as $child) {
						$result[] = $child;
					}
				}
			}

			return $result;
		}

		protected static function save($scope, array $rows, array $available)
		{
			$allowed = array();
			foreach ($available as $item) {
				$code = isset($item['layout_code']) ? (string) $item['layout_code'] : (isset($item['code']) ? (string) $item['code'] : '');
				if ($code !== '') {
					$allowed[$code] = true;
				}
			}

			$result = array();
			$seen = array();
			foreach ($rows as $row) {
				$code = is_array($row) && isset($row['code']) ? trim((string) $row['code']) : '';
				if ($code === '' || !isset($allowed[$code]) || isset($seen[$code])) {
					continue;
				}

				$seen[$code] = true;
				$result[] = array('code' => $code, 'visible' => !empty($row['visible']));
			}

			foreach (array_keys($allowed) as $code) {
				if (!isset($seen[$code])) {
					$result[] = array('code' => $code, 'visible' => true);
				}
			}

			Settings::set(self::key($scope), $result, 'json');
			return $result;
		}

		protected static function config($scope)
		{
			$value = Settings::get(self::key($scope), array());
			if (!is_array($value)) {
				return array();
			}

			$result = array();
			foreach ($value as $row) {
				if (is_array($row) && !empty($row['code'])) {
					$result[] = array('code' => (string) $row['code'], 'visible' => !empty($row['visible']));
				}
			}

			return $result;
		}

		protected static function key($scope)
		{
			return self::MODULE . '.' . (string) $scope;
		}
	}
