<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Database;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\DatabaseConfiguration;
	use App\Common\PublicConfiguration;
	use App\Common\SystemTables;

	/**
	 * Обслуживание БД: список таблиц текущего префикса, их размеры и операции
	 * OPTIMIZE/REPAIR/ANALYZE. Все операции — только над таблицами native-доменов
	 * (имя валидируется по фактическому списку, чужие/legacy-таблицы недоступны).
	 */
	class Model
	{
		/** Таблицы текущего префикса со статусом (размер/строки/движок). */
		public static function tables()
		{
			$out = array();
			$seen = array();
			foreach (self::prefixes() as $prefix) {
				$rows = DB::query('SHOW TABLE STATUS LIKE %s', $prefix . '\_%')->getAll();
				foreach ($rows ?: array() as $row) {
					$row = (array) $row;
					$name = (string) $row['Name'];
					if (strpos($name, self::stagePrefix() . '_stage_') === 0 || isset($seen[$name])) { continue; }
					$seen[$name] = true;
					$data = (int) $row['Data_length'];
					$index = (int) $row['Index_length'];
					$out[] = array(
						'name'   => $name,
						'prefix' => $prefix,
						'engine' => (string) $row['Engine'],
						'is_myisam' => strcasecmp((string) $row['Engine'], 'MyISAM') === 0,
						'rows'   => (int) $row['Rows'],
						'size'   => $data + $index,
						'size_h' => self::human($data + $index),
						'data'   => $data,
						'index'  => $index,
						'collation' => (string) (isset($row['Collation']) ? $row['Collation'] : ''),
					);
				}
			}

			return $out;
		}

		/** Множество валидных имён таблиц текущего префикса. */
		public static function names()
		{
			$out = array();
			foreach (self::prefixes() as $prefix) {
				$rows = DB::query('SHOW TABLES LIKE %s', $prefix . '\_%')->getAll();
				foreach ($rows ?: array() as $row) {
					$name = (string) array_values((array) $row)[0];
					if (strpos($name, self::stagePrefix() . '_stage_') === 0) { continue; }
					$out[] = $name;
				}
			}

			return array_values(array_unique($out));
		}

		public static function prefixes()
		{
			return self::nativePrefixes();
		}

		/** Базовый префикс из configs/db.config.php, совместимый с {{prefix}} installer. */
		public static function basePrefix()
		{
			return DatabaseConfiguration::prefix();
		}

		public static function portableTableName($name)
		{
			$name = (string) $name;
			$prefix = self::basePrefix() . '_';
			return strpos($name, $prefix) === 0 ? '{{prefix}}_' . substr($name, strlen($prefix)) : $name;
		}

		public static function isValidTable($name)
		{
			return in_array((string) $name, self::names(), true);
		}

		/** Разрешённое имя таблицы, включая ещё не созданную таблицу из дампа. */
		public static function isManagedTableName($name)
		{
			$name = (string) $name;
			if (!preg_match('/^[A-Za-z0-9_]+$/', $name)
				|| strpos($name, self::stagePrefix() . '_stage_') === 0) {
				return false;
			}

			foreach (self::prefixes() as $prefix) {
				if (strpos($name, $prefix . '_') === 0) { return true; }
			}

			return false;
		}

		/** Сводка по префиксу: таблиц / размер / строк / имя БД. */
		public static function stats()
		{
			$tables = self::tables();
			$size = 0;
			$rows = 0;
			$innodb = 0;
			$myisam = 0;
			foreach ($tables as $t) {
				$size += $t['size'];
				$rows += $t['rows'];
				if (strcasecmp($t['engine'], 'InnoDB') === 0) { $innodb++; }
				if (strcasecmp($t['engine'], 'MyISAM') === 0) { $myisam++; }
			}

			return array(
				'tables'   => count($tables),
				'size'     => $size,
				'size_h'   => self::human($size),
				'rows'     => $rows,
				'innodb'   => $innodb,
				'myisam'   => $myisam,
				'database' => self::databaseName(),
				'prefix'   => implode(', ', self::prefixes()),
			);
		}

		/** Человекочитаемый размер. */
		public static function human($bytes)
		{
			$bytes = (int) $bytes;
			$units = array('Б', 'КБ', 'МБ', 'ГБ', 'ТБ');
			$i = 0;
			$val = $bytes;
			while ($val >= 1024 && $i < count($units) - 1) {
				$val /= 1024;
				$i++;
			}

			return ($i === 0 ? $val : number_format($val, 1, ',', ' ')) . ' ' . $units[$i];
		}

		public static function databaseName()
		{
			try {
				return (string) DB::query('SELECT DATABASE()')->getValue();
			} catch (\Throwable $e) {
				return '';
			}
		}

		/** Выполнить maintenance-операцию над одной таблицей. */
		public static function maintenance($op, $table)
		{
			if (!self::isValidTable($table)) {
				return array('ok' => false, 'table' => $table, 'message' => 'Неизвестная таблица');
			}

			$keyword = self::opKeyword($op);
			if ($keyword === null) {
				return array('ok' => false, 'table' => $table, 'message' => 'Неизвестная операция');
			}

			try {
				$res = DB::query($keyword . ' TABLE `' . $table . '`')->getAll();
				$last = $res ? (array) end($res) : array();
				$msg = isset($last['Msg_text']) ? (string) $last['Msg_text'] : 'OK';
				return array('ok' => true, 'table' => $table, 'message' => $msg);
			} catch (\Throwable $e) {
				return array('ok' => false, 'table' => $table, 'message' => $e->getMessage());
			}
		}

		/** Перевести таблицы текущей схемы из MyISAM в InnoDB. */
		public static function convertToInnoDb()
		{
			$converted = array();
			$failed = array();
			foreach (self::tables() as $table) {
				if (!$table['is_myisam']) { continue; }
				try {
					DB::query('ALTER TABLE `' . $table['name'] . '` ENGINE=InnoDB');
					$converted[] = $table['name'];
				} catch (\Throwable $e) {
					$failed[$table['name']] = $e->getMessage();
				}
			}

			return array('converted' => $converted, 'failed' => $failed);
		}

		protected static function opKeyword($op)
		{
			$map = array('optimize' => 'OPTIMIZE', 'repair' => 'REPAIR', 'analyze' => 'ANALYZE', 'check' => 'CHECK');
			return isset($map[$op]) ? $map[$op] : null;
		}

		public static function stagePrefix()
		{
			return SystemTables::prefix();
		}

		protected static function nativePrefixes()
		{
			$prefixes = array();
			foreach (array('system', 'content', 'catalog', 'module', 'basket', 'contacts', 'public_user', 'public_shell') as $domain) {
				$prefixes[] = PublicConfiguration::prefix($domain);
			}

			return self::compactPrefixes($prefixes);
		}

		/** Remove duplicate/nested prefixes because SHOW LIKE parent_ already includes them. */
		protected static function compactPrefixes(array $prefixes)
		{
			$prefixes = array_values(array_unique(array_map('strval', $prefixes)));
			usort($prefixes, function ($left, $right) { return strlen($left) - strlen($right); });
			$out = array();
			foreach ($prefixes as $prefix) {
				$covered = false;
				foreach ($out as $parent) {
					if ($prefix === $parent || strpos($prefix, $parent . '_') === 0) { $covered = true; break; }
				}

				if (!$covered) { $out[] = $prefix; }
			}

			return $out;
		}
	}
