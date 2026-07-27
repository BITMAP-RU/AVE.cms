<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldTypes.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldRegistry;
	use App\Common\AuditLog;
	use App\Common\ModuleSettings;

	class FieldTypes
	{
		protected static $cache = null;
		protected static $enabledLoaded = false;
		protected static $enabledIds = array();
		protected static $newTypeIds = array(
			'content',
			'number',
			'date_time',
			'choice',
			'contact',
			'color',
			'range',
			'dimensions',
			'packages',
			'address',
			'period',
		);

		public static function all()
		{
			if (self::$cache !== null) {
				return self::$cache;
			}

			$out = array();

			foreach (FieldRegistry::all() as $id => $plugin) {
				$out[$id] = self::nativeDescribe($id, $plugin);
			}

			uasort($out, function ($a, $b) {
				return strcasecmp($a['name'], $b['name']);
			});

			self::$cache = array_values($out);
			return self::$cache;
		}

		public static function names()
		{
			$names = array();
			foreach (self::all() as $type) {
				$names[$type['id']] = $type['name'];
			}

			return $names;
		}

		/** Types available when creating a new rubric field. */
		public static function enabled()
		{
			return array_values(array_filter(self::all(), function ($type) {
				return !empty($type['enabled']);
			}));
		}

		public static function isEnabled($id)
		{
			return in_array((string) $id, self::enabledIds(), true);
		}

		public static function setEnabled($id, $enabled, $actorId = 0)
		{
			$id = (string) $id;
			if (!FieldRegistry::has($id)) {
				throw new \RuntimeException('Тип поля не найден');
			}

			$before = self::isEnabled($id);
			$ids = self::enabledIds();
			if ($enabled && !in_array($id, $ids, true)) {
				$ids[] = $id;
			} elseif (!$enabled) {
				$ids = array_values(array_diff($ids, array($id)));
			}

			sort($ids, SORT_NATURAL | SORT_FLAG_CASE);
			ModuleSettings::set('enabled_field_types', $ids, 'rubrics', 'json');
			self::reset();
			AuditLog::record($enabled ? 'rubric.field_type_enabled' : 'rubric.field_type_disabled', array(
				'actor_id' => (int) $actorId > 0 ? (int) $actorId : null,
				'target_type' => 'field_type',
				'meta' => array('code' => $id, 'before' => $before, 'after' => (bool) $enabled),
			));
			return self::one($id);
		}

		public static function one($id)
		{
			$id = (string) $id;
			foreach (self::all() as $type) {
				if ($type['id'] === $id) {
					return $type;
				}
			}

			return null;
		}

		public static function exists($id)
		{
			return self::one($id) !== null;
		}

		public static function summary(array $usage = array())
		{
			$types = self::all();
			$ok = 0;
			$warning = 0;
			$error = 0;
			$assets = 0;
			$templates = 0;
			foreach ($types as $type) {
				if ($type['status'] === 'ok') {
					$ok++;
				} elseif ($type['status'] === 'error') {
					$error++;
				} else {
					$warning++;
				}

				$assets += count($type['assets']);
				$templates += count($type['templates']);
			}

			return array(
				'types' => count($types),
				'new' => count(array_filter($types, function ($type) { return !empty($type['is_new']); })),
				'enabled' => count(array_filter($types, function ($type) { return !empty($type['enabled']); })),
				'disabled' => count(array_filter($types, function ($type) { return empty($type['enabled']); })),
				'ok' => $ok,
				'warning' => $warning,
				'error' => $error,
				'assets' => $assets,
				'templates' => $templates,
				'used' => array_sum($usage),
			);
		}

		protected static function nativeDescribe($id, $plugin)
		{
			$editor = FieldAdminEditors::describe($id);
			$registration = FieldRegistry::metadata($id);
			$creatable = !is_array($registration) || !array_key_exists('creatable', $registration) || !empty($registration['creatable']);
			return array(
				'id' => (string) $id,
				'name' => (string) $plugin->name(),
				'source' => is_array($registration) && isset($registration['source']) ? (string) $registration['source'] : 'core',
				'function' => '',
				'path' => '',
				'directory' => '',
				'templates' => array(),
				'assets' => array(),
				'lang' => array(),
				'capabilities' => array(
					'legacy_function' => false,
					'admin_template' => true,
					'document_template' => true,
					'request_template' => true,
					'options_template' => !empty($plugin->settingsSchema()),
					'assets' => false,
					'native' => true,
				),
				'description' => isset($editor['summary']) ? (string) $editor['summary'] : (string) $plugin->name(),
				'is_new' => in_array((string) $id, self::$newTypeIds, true),
				'enabled' => $creatable && self::isEnabled($id),
				'installed' => true,
				'creatable' => $creatable,
				'admin_editor' => $editor,
				'status' => 'ok',
				'issues' => array(),
			);
		}

		protected static function enabledIds()
		{
			if (self::$enabledLoaded) {
				return self::$enabledIds;
			}

			$configured = ModuleSettings::get('enabled_field_types', null, 'rubrics');
			$available = array_keys(FieldRegistry::all());
			if (!is_array($configured)) {
				self::$enabledIds = array_values($available);
			} else {
				self::$enabledIds = array_values(array_intersect(array_map('strval', $configured), $available));
			}

			self::$enabledLoaded = true;
			return self::$enabledIds;
		}

		protected static function reset()
		{
			self::$cache = null;
			self::$enabledLoaded = false;
			self::$enabledIds = array();
		}

	}
