<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/DatabaseSchema.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use InvalidArgumentException;

	/** Portable schema checks for MySQL/MariaDB versions without ALTER IF NOT EXISTS. */
	class DatabaseSchema
	{
		protected static $tables = array();
		protected static $columns = array();
		protected static $indexes = array();

		public static function columnExists($table, $column)
		{
			self::assertIdentifier($table, 'table');
			self::assertIdentifier($column, 'column');
			$key = (string) $table . '.' . (string) $column;
			if (!empty(self::$columns[$key])) { return true; }
			$exists = (bool) DB::query(
				'SELECT 1 FROM information_schema.COLUMNS'
					. ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
				$table,
				$column
			)->getValue();
			if ($exists) { self::$columns[$key] = true; }
			return $exists;
		}

		public static function indexExists($table, $index)
		{
			self::assertIdentifier($table, 'table');
			self::assertIdentifier($index, 'index');
			$key = (string) $table . '.' . (string) $index;
			if (!empty(self::$indexes[$key])) { return true; }
			$exists = (bool) DB::query(
				'SELECT 1 FROM information_schema.STATISTICS'
					. ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s LIMIT 1',
				$table,
				$index
			)->getValue();
			if ($exists) { self::$indexes[$key] = true; }
			return $exists;
		}

		public static function tableExists($table)
		{
			self::assertIdentifier($table, 'table');
			if (!empty(self::$tables[$table])) { return true; }
			$exists = (bool) DB::query(
				'SELECT 1 FROM information_schema.TABLES'
					. ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
				$table
			)->getValue();
			if ($exists) { self::$tables[$table] = true; }
			return $exists;
		}

		public static function reset()
		{
			self::$tables = array();
			self::$columns = array();
			self::$indexes = array();
		}

		public static function alter($statement)
		{
			$statement = self::prepareAlter($statement);
			if ($statement === null) {
				return false;
			}

			DB::query($statement);
			self::reset();
			return true;
		}

		public static function prepareAlter($statement)
		{
			$statement = trim((string) $statement);
			if (preg_match(
				'/^ALTER\s+TABLE\s+IF\s+EXISTS\s+(?:`([^`]+)`|([A-Za-z0-9_]+))\s+(.+)$/is',
				$statement,
				$optionalTable
			)) {
				$table = $optionalTable[1] !== '' ? $optionalTable[1] : $optionalTable[2];
				if (!self::tableExists($table)) {
					return null;
				}

				$statement = 'ALTER TABLE `' . $table . '` ' . $optionalTable[3];
			}

			if (stripos($statement, 'IF NOT EXISTS') === false) {
				return $statement;
			}

			if (!preg_match('/^ALTER\s+TABLE\s+(?:`([^`]+)`|([A-Za-z0-9_]+))\s+(.+)$/is', $statement, $match)) {
				return $statement;
			}

			$table = $match[1] !== '' ? $match[1] : $match[2];
			self::assertIdentifier($table, 'table');
			$clauses = array();
			foreach (self::splitClauses($match[3]) as $clause) {
				$prepared = self::prepareClause($table, $clause);
				if ($prepared !== null) {
					$clauses[] = $prepared;
				}
			}

			if (!$clauses) {
				return null;
			}

			return 'ALTER TABLE `' . $table . '` ' . implode(', ', $clauses);
		}

		protected static function prepareClause($table, $clause)
		{
			$clause = trim((string) $clause);
			if (preg_match(
				'/^ADD\s+((?:UNIQUE\s+)?(?:INDEX|KEY))\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s+(.+)$/is',
				$clause,
				$match
			)) {
				if (self::indexExists($table, $match[2])) {
					return null;
				}

				return 'ADD ' . strtoupper(preg_replace('/\s+/', ' ', $match[1]))
					. ' `' . $match[2] . '` ' . trim($match[3]);
			}

			if (preg_match(
				'/^ADD\s+(COLUMN\s+)?IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s+(.+)$/is',
				$clause,
				$match
			)) {
				if (self::columnExists($table, $match[2])) {
					return null;
				}

				return 'ADD ' . ($match[1] !== '' ? 'COLUMN ' : '')
					. '`' . $match[2] . '` ' . trim($match[3]);
			}

			return $clause;
		}

		protected static function splitClauses($sql)
		{
			$clauses = array();
			$buffer = '';
			$quote = null;
			$escape = false;
			$depth = 0;
			$length = strlen((string) $sql);

			for ($i = 0; $i < $length; $i++) {
				$char = $sql[$i];
				if ($quote !== null) {
					$buffer .= $char;
					if ($escape) {
						$escape = false;
					} elseif ($char === '\\') {
						$escape = true;
					} elseif ($char === $quote) {
						$quote = null;
					}

					continue;
				}

				if ($char === "'" || $char === '"' || $char === '`') {
					$quote = $char;
					$buffer .= $char;
					continue;
				}

				if ($char === '(') {
					$depth++;
				} elseif ($char === ')' && $depth > 0) {
					$depth--;
				}

				if ($char === ',' && $depth === 0) {
					$clauses[] = trim($buffer);
					$buffer = '';
					continue;
				}

				$buffer .= $char;
			}

			if (trim($buffer) !== '') {
				$clauses[] = trim($buffer);
			}

			return $clauses;
		}

		protected static function assertIdentifier($identifier, $type)
		{
			if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $identifier)) {
				throw new InvalidArgumentException('Invalid database ' . $type . ' identifier.');
			}
		}
	}
