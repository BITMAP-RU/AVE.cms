<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicSysblockRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/** Target storage and native handlers for public system blocks. */
	class PublicSysblockRegistry
	{
		protected static $handlers = array();
		protected static $table = '';
		protected static $rows = array();

		public static function configure($table)
		{
			$table = trim((string) $table);
			if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
				throw new \InvalidArgumentException('Некорректная таблица системных блоков');
			}

			self::$table = $table;
			self::$rows = array();
		}

		public static function register($code, $id, $handler, $priority = 10)
		{
			$code = trim((string) $code); $id = trim((string) $id);
			if ($code === '' || $id === '' || !is_callable($handler)) {
				throw new \InvalidArgumentException('Некорректный обработчик системного блока');
			}

			self::$handlers[] = array('code' => $code, 'id' => $id, 'handler' => $handler, 'priority' => (int) $priority);
			usort(self::$handlers, function ($left, $right) { return $left['priority'] <=> $right['priority']; });
		}

		public static function render($id, array $params = array(), array $context = array())
		{
			$id = (string) $id;
			foreach (self::$handlers as $definition) {
				if ($definition['id'] !== $id) { continue; }
				try {
					$output = call_user_func($definition['handler'], $id, $params, $context);
					return array('handled' => true, 'final' => true, 'evaluate' => false, 'output' => is_string($output) ? $output : '');
				} catch (\Throwable $e) {
					error_log('Public sysblock [' . $definition['code'] . ':' . $id . ']: ' . $e->getMessage());
					return array('handled' => true, 'final' => true, 'evaluate' => false, 'output' => '');
				}
			}

			if (self::$table === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) {
				return array('handled' => true, 'final' => true, 'evaluate' => false, 'output' => '');
			}

			try {
				$row = self::row($id);
				if (!$row) {
					return array('handled' => true, 'final' => true, 'evaluate' => false, 'output' => '');
				}

				if (isset($row['sysblock_active']) && (string) $row['sysblock_active'] !== '1') {
					return array('handled' => true, 'final' => false, 'evaluate' => false, 'output' => '');
				}

				return array(
					'handled' => true,
					'final' => false,
					'evaluate' => !empty($row['sysblock_eval']),
					'output' => (string) $row['sysblock_text'],
				);
			} catch (\Throwable $e) {
				error_log('Public sysblock [' . $id . ']: ' . $e->getMessage());
				return array('handled' => true, 'final' => true, 'evaluate' => false, 'output' => '');
			}
		}

		public static function metadata($id)
		{
			$id = trim((string) $id);
			if (self::$table === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) {
				return null;
			}

			$row = self::row($id);
			return $row ?: null;
		}

		protected static function row($id)
		{
			$key = is_numeric($id) ? 'id:' . (int) $id : 'alias:' . (string) $id;
			$loading = Lifecycle::event('content.sysblock.loading', 'sysblock', 'loading', $id, array(
				'cache_key' => $key,
			), null, array(), 'public_sysblock_registry');
			if ($loading->cancelled()) {
				return is_array($loading->result()) ? $loading->result() : null;
			}

			$cacheHit = array_key_exists($key, self::$rows);
			if (!array_key_exists($key, self::$rows)) {
				$row = DB::query(
					'SELECT id, sysblock_name, sysblock_alias, sysblock_active, sysblock_external, sysblock_ajax, sysblock_text, sysblock_eval'
					. ' FROM `' . self::$table . '` WHERE '
					. (is_numeric($id) ? 'id = %i' : 'sysblock_alias = %s') . ' LIMIT 1',
					is_numeric($id) ? (int) $id : $id
				)->getAssoc();
				$row = $row ?: null;
				self::$rows[$key] = $row;
				if ($row) {
					self::$rows['id:' . (int) $row['id']] = $row;
					if (!empty($row['sysblock_alias'])) {
						self::$rows['alias:' . (string) $row['sysblock_alias']] = $row;
					}
				}
			}

			$loaded = Lifecycle::event('content.sysblock.loaded', 'sysblock', 'loaded', $id, array(
				'cache_key' => $key,
			), self::$rows[$key], array('cache_hit' => $cacheHit), 'public_sysblock_registry');
			return $loaded->result();
		}

		public static function all() { return self::$handlers; }
		public static function reset() { self::$handlers = array(); self::$rows = array(); }
	}
