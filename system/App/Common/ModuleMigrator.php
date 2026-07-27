<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ModuleMigrator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	use DB;
	use RuntimeException;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Идемпотентные миграции модулей.
	 *
	 * Модуль объявляет SQL- или PHP-файлы в module.php, а ModuleManager применяет
	 * их только при явной установке/обновлении пакета или web-обновлении схемы
	 * ядра. Обычная загрузка public/Adminx не выполняет миграции.
	 */
	class ModuleMigrator
	{
		protected static $ensured = false;
		protected static $lastResults = [];
		protected static $appliedChecksums;

		public static function table()
		{
			return SystemTables::table('module_migrations');
		}

		public static function attemptsTable()
		{
			return SystemTables::table('module_migration_attempts');
		}

		public static function ensureTable()
		{
			if (self::$ensured) {
				return;
			}

			DB::query(
				"CREATE TABLE IF NOT EXISTS " . self::table() . " (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              module VARCHAR(80) CHARACTER SET ascii NOT NULL,
              migration VARCHAR(190) CHARACTER SET ascii NOT NULL,
              checksum CHAR(40) NOT NULL,
              applied_at DATETIME NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uniq_module_migration (module, migration),
              INDEX idx_module (module)
			) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4"
			);
			DB::query(
				"CREATE TABLE IF NOT EXISTS " . self::attemptsTable() . " (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              module VARCHAR(80) CHARACTER SET ascii NOT NULL,
              migration VARCHAR(190) CHARACTER SET ascii NOT NULL,
              checksum CHAR(40) NOT NULL,
              operation VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT '',
              status VARCHAR(20) CHARACTER SET ascii NOT NULL,
              queries_count INT UNSIGNED NOT NULL DEFAULT 0,
              error_message TEXT NULL,
              actor_id INT UNSIGNED NULL,
              started_at DATETIME NOT NULL,
              completed_at DATETIME NULL,
              PRIMARY KEY (id),
              INDEX idx_module_started (module, started_at),
              INDEX idx_status (status)
			) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4"
			);

			self::$ensured = true;
		}

		/**
		 * Применить список миграций из дескриптора модуля.
		 *
		 * @param string $module
		 * @param array|string|null $migrations
		 * @param string $basePath
		 * @return array
		 */
		public static function apply($module, $migrations, $basePath, array $context = array())
		{
			$module = trim((string) $module);
			if ($module === '' || empty($migrations)) {
				return [];
			}

			if (is_string($migrations)) {
				$migrations = [$migrations];
			}

			if (!is_array($migrations)) {
				return [];
			}

			self::ensureTable();

			$results = [];
			foreach ($migrations as $migration) {
				$item = self::normalize($migration, $basePath);
				if (!$item) {
					continue;
				}

				$attemptId = self::startAttempt($module, $item['id'], $item['file'], $context);
				try {
					$result = self::applyFile($module, $item['id'], $item['file'], $item['legacy_checksums'], $context);
					self::finishAttempt($attemptId, $result['status'], isset($result['queries']) ? (int) $result['queries'] : 0);
					$results[] = $result;
				} catch (\Throwable $e) {
					self::finishAttempt($attemptId, 'failed', 0, $e->getMessage());
					throw $e;
				}
			}

			self::$lastResults = array_merge(self::$lastResults, $results);
			return $results;
		}

		public static function lastResults()
		{
			return self::$lastResults;
		}

		/**
		 * Проверить список миграций без их выполнения.
		 *
		 * @return array ['total' => int, 'pending' => int, 'changed' => int]
		 */
		public static function inspect($module, $migrations, $basePath)
		{
			$result = array('total' => 0, 'pending' => 0, 'changed' => 0);
			if (empty($migrations)) {
				return $result;
			}

			if (is_string($migrations)) {
				$migrations = array($migrations);
			}

			if (!is_array($migrations)) {
				return $result;
			}

			$applied = self::appliedChecksums();
			foreach ($migrations as $migration) {
				$item = self::normalize($migration, $basePath);
				if (!$item) {
					continue;
				}

				$result['total']++;
				$key = (string) $module . "\0" . (string) $item['id'];
				if (!isset($applied[$key])) {
					$result['pending']++;
					continue;
				}

				// The migration ID is authoritative. Signed package updates may correct
				// an old source file, but an applied migration never runs twice.
			}

			return $result;
		}

		/** Проверить, был ли ID миграции уже применён для владельца. */
		public static function isApplied($module, $migration)
		{
			$key = trim((string) $module) . "\0" . trim((string) $migration);
			return isset(self::appliedChecksums()[$key]);
		}

		/** Забыть примененные миграции модуля после явной деинсталляции. */
		public static function forget($module)
		{
			$module = trim((string) $module);
			if ($module === '') {
				return 0;
			}

			self::ensureTable();
			$deleted = DB::Delete(self::table(), 'module = %s', $module);
			self::$appliedChecksums = null;
			return $deleted;
		}

		public static function splitSql($sql)
		{
			$sql = str_replace(["\r\n", "\r"], "\n", (string) $sql);
			$statements = [];
			$buffer = '';
			$quote = null;
			$escape = false;
			$lineComment = false;
			$blockComment = false;
			$len = strlen($sql);

			for ($i = 0; $i < $len; $i++) {
				$ch = $sql[$i];
				$next = $i + 1 < $len ? $sql[$i + 1] : '';

				if ($lineComment) {
					if ($ch === "\n") {
						$lineComment = false;
						$buffer .= $ch;
					}

					continue;
				}

				if ($blockComment) {
					if ($ch === '*' && $next === '/') {
						$blockComment = false;
						$i++;
					}

					continue;
				}

				if ($quote === null && $ch === '-' && $next === '-') {
					$lineComment = true;
					$i++;
					continue;
				}

				if ($quote === null && $ch === '#') {
					$lineComment = true;
					continue;
				}

				if ($quote === null && $ch === '/' && $next === '*') {
					$blockComment = true;
					$i++;
					continue;
				}

				$buffer .= $ch;

				if ($quote !== null) {
					if ($escape) {
						$escape = false;
						continue;
					}

					if ($ch === '\\') {
						$escape = true;
						continue;
					}

					if ($ch === $quote) {
						$quote = null;
					}

					continue;
				}

				if ($ch === "'" || $ch === '"' || $ch === '`') {
					$quote = $ch;
					continue;
				}

				if ($ch === ';') {
					$stmt = trim(substr($buffer, 0, -1));
					if ($stmt !== '') {
						$statements[] = $stmt;
					}

					$buffer = '';
				}
			}

			$tail = trim($buffer);
			if ($tail !== '') {
				$statements[] = $tail;
			}

			return $statements;
		}

		protected static function normalize($migration, $basePath)
		{
			$legacyChecksums = array();
			if (is_string($migration)) {
				$file = $migration;
				$id = basename($file);
			} elseif (is_array($migration) && !empty($migration['file'])) {
				$file = (string) $migration['file'];
				$id = !empty($migration['id']) ? (string) $migration['id'] : basename($file);
				$legacyChecksums = isset($migration['legacy_checksums']) ? (array) $migration['legacy_checksums'] : array();
			} else {
				return null;
			}

			foreach ($legacyChecksums as &$legacyChecksum) {
				$legacyChecksum = strtolower(trim((string) $legacyChecksum));
				if (!preg_match('/^[a-f0-9]{40}$/', $legacyChecksum)) {
					throw new RuntimeException('Некорректный legacy checksum миграции: ' . $id);
				}
			}

			unset($legacyChecksum);

			$path = self::resolvePath($basePath, $file);
			return ['id' => $id, 'file' => $path, 'legacy_checksums' => array_values(array_unique($legacyChecksums))];
		}

		protected static function applyFile($module, $migration, $file, array $legacyChecksums = array(), array $context = array())
		{
			if (!is_file($file)) {
				throw new RuntimeException('Migration file not found: ' . $file);
			}

			$content = file_get_contents($file);
			if ($content === false) {
				throw new RuntimeException('Migration file is not readable: ' . $file);
			}

			$checksum = sha1($content);
			$existing = DB::query(
				"SELECT checksum FROM " . self::table() . " WHERE module = %s AND migration = %s LIMIT 1",
				$module,
				$migration
			)->getAssoc();

			if (!empty($existing)) {
				$appliedChecksum = strtolower((string) $existing['checksum']);
				if (!hash_equals($appliedChecksum, $checksum)) {
					DB::Update(self::table(), array('checksum' => $checksum), 'module = %s AND migration = %s', $module, $migration);
					self::$appliedChecksums = null;
				}

				return [
					'module' => $module,
					'migration' => $migration,
					'status' => 'skipped',
					'checksum' => $checksum,
				];
			}

			$type = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
			$transactional = $type === 'php';
			if ($transactional) {
				DB::startTransaction();
			}

			try {
				if ($type === 'sql') {
					$count = self::applySqlMigration($content);
				} elseif ($type === 'php') {
					$count = self::applyPhpMigration($file, array_merge($context, array(
						'module' => $module,
						'migration' => $migration,
					)));
				} else {
					throw new RuntimeException('Unsupported migration file type: ' . $file);
				}

				DB::Insert(self::table(), [
					'module' => $module,
					'migration' => $migration,
					'checksum' => $checksum,
					'applied_at' => date('Y-m-d H:i:s'),
				]);
				self::$appliedChecksums = null;

				if ($transactional) {
					DB::commit();
				}
			} catch (\Throwable $e) {
				if ($transactional) {
					DB::rollback();
				}

				throw $e;
			}

			return [
				'module' => $module,
				'migration' => $migration,
				'status' => 'applied',
				'queries' => $count,
				'checksum' => $checksum,
			];
		}

		protected static function appliedChecksums()
		{
			if (self::$appliedChecksums !== null) {
				return self::$appliedChecksums;
			}

			self::$appliedChecksums = array();
			if (!DatabaseSchema::tableExists(self::table())) {
				return self::$appliedChecksums;
			}

			foreach (DB::query('SELECT module, migration, checksum FROM ' . self::table())->getAll() ?: array() as $row) {
				$row = (array) $row;
				$key = (string) $row['module'] . "\0" . (string) $row['migration'];
				self::$appliedChecksums[$key] = strtolower((string) $row['checksum']);
			}

			return self::$appliedChecksums;
		}

		protected static function startAttempt($module, $migration, $file, array $context)
		{
			if (!is_file($file)) {
				throw new RuntimeException('Migration file not found: ' . $file);
			}

			$checksum = sha1_file($file);
			if ($checksum === false) {
				throw new RuntimeException('Migration file is not readable: ' . $file);
			}

			DB::Insert(self::attemptsTable(), array(
				'module' => (string) $module,
				'migration' => (string) $migration,
				'checksum' => $checksum,
				'operation' => isset($context['operation']) ? (string) $context['operation'] : '',
				'status' => 'running',
				'queries_count' => 0,
				'error_message' => null,
				'actor_id' => isset($context['actor_id']) ? (int) $context['actor_id'] : (Auth::id() ?: null),
				'started_at' => date('Y-m-d H:i:s'),
				'completed_at' => null,
			));

			return (int) DB::insertId();
		}

		protected static function finishAttempt($attemptId, $status, $queries = 0, $error = '')
		{
			DB::Update(self::attemptsTable(), array(
				'status' => (string) $status,
				'queries_count' => max(0, (int) $queries),
				'error_message' => $error !== '' ? (string) $error : null,
				'completed_at' => date('Y-m-d H:i:s'),
			), 'id = %i', (int) $attemptId);
		}

		protected static function applySqlMigration($sql)
		{
			$sql = self::expandPrefixes($sql);
			$count = 0;
			foreach (self::splitSql($sql) as $statement) {
				if (DatabaseSchema::alter($statement)) {
					$count++;
				}
			}

			return $count;
		}

		protected static function applyPhpMigration($file, array $context)
		{
			$handler = require $file;
			if (!is_callable($handler)) {
				throw new RuntimeException('PHP migration must return a callable: ' . $file);
			}

			$result = call_user_func($handler, $context);
			if ($result === null) {
				return 0;
			}

			if (!is_int($result) || $result < 0) {
				throw new RuntimeException('PHP migration must return a non-negative query count: ' . $file);
			}

			return $result;
		}

		protected static function resolvePath($basePath, $file)
		{
			if ($file !== '' && $file[0] === '/') {
				return $file;
			}

			return rtrim((string) $basePath, DS) . DS . ltrim((string) $file, DS);
		}

		public static function expandPrefixes($sql)
		{
			$replacements = array(
				'{{prefix}}' => SystemTables::prefix(),
				'{{database_prefix}}' => DatabaseConfiguration::prefix(),
			);
			foreach (array(
				'catalog_prefix' => 'catalog',
				'content_prefix' => 'content',
				'module_prefix' => 'module',
				'basket_prefix' => 'basket',
				'contacts_prefix' => 'contacts',
				'public_shell_prefix' => 'public_shell',
				'public_user_prefix' => 'public_user',
				'system_prefix' => 'system',
			) as $placeholder => $domain) {
				$replacements['{{' . $placeholder . '}}'] = PublicConfiguration::prefix($domain);
			}

			return str_replace(array_keys($replacements), array_values($replacements), (string) $sql);
		}
	}
