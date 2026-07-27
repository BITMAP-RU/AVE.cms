<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/CoreUpdate/DatabaseBackup.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\CoreUpdate;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;
	use App\Common\ModuleMigrator;
	use DB;

	/** Prefix-scoped, resumable database backup used only by signed core updates. */
	class DatabaseBackup
	{
		const BATCH_SIZE = 500;

		public static function begin($directory, $tablePatterns = null)
		{
			if (!function_exists('gzopen')) { throw new \RuntimeException('Для резервной копии БД требуется zlib'); }
			$tables = self::tables($tablePatterns);
			if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) { throw new \RuntimeException('Не удалось создать каталог резервной копии БД'); }
			self::writeManifest($directory, array(
				'format' => 'ave-core-db-backup-v1',
				'created_at' => date(DATE_ATOM),
				'complete' => false,
				'scope' => $tablePatterns === null ? 'full' : 'selected',
				'tables' => $tables,
			));
			return $tables;
		}

		public static function writeSchema($directory, $table)
		{
			self::assertPlannedTable($directory, $table);
			$escaped = self::escapedTable($table);
			$create = DB::query('SHOW CREATE TABLE ' . $escaped)->getAssoc();
			$ddl = isset($create['Create Table']) ? (string) $create['Create Table'] : '';
			if ($ddl === '') { throw new \RuntimeException('Не удалось получить схему таблицы ' . $table); }
			self::writeGzip(self::tableDirectory($directory, $table) . '/000-schema.sql.gz', "DROP TABLE IF EXISTS {$escaped};\n{$ddl};\n");
		}

		public static function writeRows($directory, $table, $offset, $limit = self::BATCH_SIZE)
		{
			self::assertPlannedTable($directory, $table);
			$offset = max(0, (int) $offset);
			$limit = max(1, min(2000, (int) $limit));
			$escaped = self::escapedTable($table);
			$rows = DB::query('SELECT * FROM ' . $escaped . ' LIMIT ' . $limit . ' OFFSET ' . $offset)->getAll();
			$sql = '';
			foreach ($rows ?: array() as $row) {
				$columns = array();
				$values = array();
				foreach ((array) $row as $column => $value) {
					$columns[] = '`' . str_replace('`', '``', (string) $column) . '`';
					$values[] = $value === null ? 'NULL' : "X'" . bin2hex((string) $value) . "'";
				}

				$sql .= 'INSERT INTO ' . $escaped . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
			}

			$file = self::tableDirectory($directory, $table) . '/rows-' . str_pad((string) $offset, 12, '0', STR_PAD_LEFT) . '.sql.gz';
			self::writeGzip($file, $sql);
			return count($rows ?: array());
		}

		public static function finish($directory)
		{
			$manifest = self::manifest($directory);
			$manifest['complete'] = true;
			$manifest['completed_at'] = date(DATE_ATOM);
			self::writeManifest($directory, $manifest);
			return $manifest;
		}

		public static function complete($directory)
		{
			try { $manifest = self::manifest($directory); return !empty($manifest['complete']); }
			catch (\Throwable $e) { return false; }
		}

		public static function estimatedBytes($tablePatterns = null)
		{
			$planned = array_fill_keys(self::tables($tablePatterns), true);
			$total = 0;
			foreach (DB::query('SHOW TABLE STATUS')->getAll() ?: array() as $row) {
				$name = isset($row['Name']) ? (string) $row['Name'] : '';
				if (!isset($planned[$name])) { continue; }
				$total += max(0, (int) (isset($row['Data_length']) ? $row['Data_length'] : 0));
				$total += max(0, (int) (isset($row['Index_length']) ? $row['Index_length'] : 0));
			}

			return $total;
		}

		public static function create($directory)
		{
			$tables = self::begin($directory);
			foreach ($tables as $table) {
				self::writeSchema($directory, $table);
				$offset = 0;
				do { $count = self::writeRows($directory, $table, $offset); $offset += self::BATCH_SIZE; } while ($count === self::BATCH_SIZE);
			}

			self::finish($directory);
			return array('path' => $directory, 'tables' => count($tables), 'size' => self::directorySize($directory));
		}

		public static function restore($directory)
		{
			$manifest = self::manifest($directory);
			if (empty($manifest['complete'])) { throw new \RuntimeException('Резервная копия БД не завершена'); }
			$mysqli = DB::mysqli();
			$count = 0;
			try {
				if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=0')) { throw new \RuntimeException('Не удалось начать восстановление БД'); }
				foreach ($manifest['tables'] as $table) {
					$files = glob(self::tableDirectory($directory, $table) . '/*.sql.gz') ?: array();
					sort($files, SORT_STRING);
					if (!$files || basename($files[0]) !== '000-schema.sql.gz') { throw new \RuntimeException('Неполная копия таблицы ' . $table); }
					foreach ($files as $file) { $count += self::restoreFile($file, $mysqli); }
				}
			} finally {
				$mysqli->query('SET FOREIGN_KEY_CHECKS=1');
			}

			return $count;
		}

		protected static function restoreFile($file, $mysqli)
		{
			$handle = gzopen($file, 'rb');
			if (!$handle) { throw new \RuntimeException('Не удалось открыть фрагмент резервной копии'); }
			$statement = '';
			$count = 0;
			try {
				while (!gzeof($handle)) {
					$line = gzgets($handle, 1048576);
					if ($line === false) { break; }
					$statement .= $line;
					if (substr(rtrim($line), -1) !== ';') { continue; }
					if (!$mysqli->query(trim($statement))) { throw new \RuntimeException('Не удалось восстановить БД: ' . $mysqli->error); }
					$count++;
					$statement = '';
				}

				if (trim($statement) !== '') { throw new \RuntimeException('Фрагмент резервной копии оборван'); }
			} finally { gzclose($handle); }
			return $count;
		}

		public static function tables($tablePatterns = null)
		{
			if ($tablePatterns !== null) {
				if (!is_array($tablePatterns)) { throw new \InvalidArgumentException('Некорректный список таблиц резервной копии'); }
				return self::selectedTables($tablePatterns);
			}

			$prefixes = array();
			foreach (array('system', 'content', 'catalog', 'module', 'basket', 'contacts', 'public_user', 'public_shell') as $domain) {
				$prefixes[] = PublicConfiguration::prefix($domain);
			}

			$prefixes = self::compactPrefixes($prefixes);
			$tables = array();
			foreach ($prefixes as $prefix) {
				$rows = DB::query('SHOW FULL TABLES LIKE %s', $prefix . '\_%')->getAll();
				foreach ($rows ?: array() as $row) {
					$values = array_values((array) $row);
					$name = isset($values[0]) ? (string) $values[0] : '';
					$type = isset($values[1]) ? strtoupper((string) $values[1]) : 'BASE TABLE';
					if ($type !== 'BASE TABLE') { continue; }
					if (preg_match('/^[A-Za-z0-9_]+$/', $name)) { $tables[$name] = $name; }
				}
			}

			ksort($tables, SORT_STRING);
			return array_values($tables);
		}

		protected static function selectedTables(array $patterns)
		{
			$tables = array();
			foreach ($patterns as $pattern) {
				$pattern = trim((string) $pattern);
				if (!preg_match('/^\{\{(?:prefix|database_prefix|catalog_prefix|content_prefix|module_prefix|basket_prefix|contacts_prefix|public_shell_prefix|public_user_prefix|system_prefix)\}\}_[A-Za-z0-9_]+$/', $pattern)) {
					throw new \InvalidArgumentException('Некорректный шаблон таблицы резервной копии');
				}

				$table = ModuleMigrator::expandPrefixes($pattern);
				if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
					throw new \InvalidArgumentException('Некорректное имя таблицы резервной копии');
				}

				$row = DB::query(
					'SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES'
						. ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s LIMIT 1',
					$table
				)->getAssoc();
				if (!$row || strtoupper((string) $row['TABLE_TYPE']) !== 'BASE TABLE') { continue; }
				$tables[$table] = $table;
			}

			ksort($tables, SORT_STRING);
			return array_values($tables);
		}

		protected static function compactPrefixes(array $prefixes)
		{
			$prefixes = array_values(array_unique(array_filter(array_map('strval', $prefixes))));
			usort($prefixes, function ($left, $right) { return strlen($left) - strlen($right); });
			$result = array();
			foreach ($prefixes as $prefix) {
				$covered = false;
				foreach ($result as $parent) { if ($prefix === $parent || strpos($prefix, $parent . '_') === 0) { $covered = true; break; } }
				if (!$covered) { $result[] = $prefix; }
			}

			return $result;
		}

		protected static function manifest($directory)
		{
			$file = rtrim((string) $directory, '/\\') . '/backup.json';
			$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
			if (!is_array($data) || !isset($data['format'], $data['tables']) || $data['format'] !== 'ave-core-db-backup-v1' || !is_array($data['tables'])) { throw new \RuntimeException('Реестр резервной копии БД повреждён'); }
			foreach ($data['tables'] as $table) { if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) { throw new \RuntimeException('Реестр резервной копии содержит небезопасное имя'); } }
			return $data;
		}

		protected static function writeManifest($directory, array $manifest)
		{
			$file = rtrim((string) $directory, '/\\') . '/backup.json';
			@mkdir(dirname($file), 0750, true);
			$tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
			$content = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
			if (file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) { @unlink($tmp); throw new \RuntimeException('Не удалось сохранить реестр резервной копии'); }
			@chmod($file, 0600);
		}

		protected static function writeGzip($file, $content)
		{
			@mkdir(dirname($file), 0750, true);
			$tmp = $file . '.part';
			$handle = gzopen($tmp, 'wb6');
			if (!$handle) { throw new \RuntimeException('Не удалось создать фрагмент резервной копии'); }
			$written = gzwrite($handle, (string) $content);
			gzclose($handle);
			if ($written === false || (int) $written !== strlen((string) $content) || !@rename($tmp, $file)) { @unlink($tmp); throw new \RuntimeException('Не удалось завершить фрагмент резервной копии'); }
			@chmod($file, 0600);
		}

		protected static function assertPlannedTable($directory, $table) { $table = (string) $table; $manifest = self::manifest($directory); if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !in_array($table, $manifest['tables'], true)) { throw new \InvalidArgumentException('Таблица не входит в план резервной копии'); } }
		protected static function escapedTable($table) { if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) { throw new \InvalidArgumentException('Некорректное имя таблицы'); } return '`' . $table . '`'; }
		protected static function tableDirectory($directory, $table) { return rtrim((string) $directory, '/\\') . '/tables/' . (string) $table; }
		protected static function directorySize($directory) { $size = 0; $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)); foreach ($iterator as $file) { if ($file->isFile()) { $size += $file->getSize(); } } return $size; }
	}
