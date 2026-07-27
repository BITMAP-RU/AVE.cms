<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Media/ThumbnailPresetScanner.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;
	use DB;

	/** Finds thumbnail presets referenced by runtime files and current DB content. */
	class ThumbnailPresetScanner
	{
		const MAX_FILE_BYTES = 4194304;

		protected static $extensions = array(
			'php', 'twig', 'tpl', 'html', 'htm', 'js', 'css', 'less', 'json', 'sql', 'htaccess',
		);

		public static function scan()
		{
			$fileSizes = array();
			$databaseSizes = array();
			$warnings = array();
			$fileStats = self::scanFiles($fileSizes, $warnings);
			$databaseStats = self::scanDatabase($databaseSizes, $warnings);

			$sizes = array();
			foreach (ThumbnailPresetPolicy::defaults() as $size) {
				$sizes[$size] = true;
			}

			foreach (array_keys($fileSizes + $databaseSizes) as $size) {
				$sizes[$size] = true;
			}

			$sizes = array_keys($sizes);
			sort($sizes, SORT_NATURAL | SORT_FLAG_CASE);

			return array(
				'sizes' => $sizes,
				'system_sizes' => ThumbnailPresetPolicy::defaults(),
				'file_sizes' => array_keys($fileSizes),
				'database_sizes' => array_keys($databaseSizes),
				'files_scanned' => $fileStats['files'],
				'database_tables_scanned' => $databaseStats['tables'],
				'database_columns_scanned' => $databaseStats['columns'],
				'database_rows_matched' => $databaseStats['rows'],
				'warnings' => $warnings,
			);
		}

		protected static function scanFiles(array &$sizes, array &$warnings)
		{
			$files = 0;
			$roots = array(
				\App\Common\AdminLocation::path(),
				BASEPATH . '/system/App',
				BASEPATH . '/templates',
				BASEPATH . '/modules',
				BASEPATH . '/configs',
				BASEPATH . '/index.php',
				BASEPATH . '/.htaccess',
			);

			foreach ($roots as $root) {
				if (is_file($root)) {
					if (self::scanFile($root, $sizes)) {
						$files++;
					}

					continue;
				}

				if (!is_dir($root)) {
					continue;
				}

				try {
					$iterator = new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
						\RecursiveIteratorIterator::LEAVES_ONLY
					);
					foreach ($iterator as $file) {
						if (!$file->isFile() || self::excludedPath($file->getPathname())) {
							continue;
						}

						if (self::scanFile($file->getPathname(), $sizes)) {
							$files++;
						}
					}
				} catch (\UnexpectedValueException $e) {
					$warnings[] = 'Не удалось прочитать ' . self::relative($root) . ': ' . $e->getMessage();
				}
			}

			ksort($sizes, SORT_NATURAL | SORT_FLAG_CASE);
			return array('files' => $files);
		}

		protected static function scanFile($path, array &$sizes)
		{
			$extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
			if (!in_array($extension, self::$extensions, true)) {
				return false;
			}

			$bytes = @filesize($path);
			if ($bytes === false || $bytes > self::MAX_FILE_BYTES) {
				return false;
			}

			$content = @file_get_contents($path);
			if (!is_string($content)) {
				return false;
			}

			self::extract($content, $sizes);
			return true;
		}

		protected static function scanDatabase(array &$sizes, array &$warnings)
		{
			$stats = array('tables' => 0, 'columns' => 0, 'rows' => 0);
			try {
				$database = (string) DB::query('SELECT DATABASE()')->getValue();
				if ($database === '') {
					return $stats;
				}

				$rows = DB::query(
					"SELECT TABLE_NAME, COLUMN_NAME
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = %s
                    AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext')
                  ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC",
					$database
				)->getAll();
			} catch (\Throwable $e) {
				$warnings[] = 'Не удалось получить структуру БД: ' . $e->getMessage();
				return $stats;
			}

			$tables = array();
			foreach ($rows ?: array() as $row) {
				$table = isset($row['TABLE_NAME']) ? (string) $row['TABLE_NAME'] : '';
				$column = isset($row['COLUMN_NAME']) ? (string) $row['COLUMN_NAME'] : '';
				if (!self::validIdentifier($table) || !self::validIdentifier($column)
					|| !self::projectTable($table) || self::excludedTable($table)) {
					continue;
				}

				if (!isset($tables[$table])) {
					$tables[$table] = array();
				}

				$tables[$table][] = $column;
			}

			foreach ($tables as $table => $columns) {
				$parts = array();
				foreach ($columns as $column) {
					$parts[] = "COALESCE(`" . $column . "`, '')";
				}

				if (!$parts) {
					continue;
				}

				$expression = "CONCAT_WS(' ', " . implode(', ', $parts) . ')';
				try {
					$matched = DB::query(
						'SELECT ' . $expression . ' AS scan_value FROM `' . $table . '` WHERE ' . $expression . ' REGEXP %s',
						'[rcfts][0-9]{1,4}x[0-9]{1,4}r?'
					)->getAll();
					$stats['tables']++;
					$stats['columns'] += count($columns);
					$stats['rows'] += count($matched ?: array());
					foreach ($matched ?: array() as $row) {
						self::extract(isset($row['scan_value']) ? (string) $row['scan_value'] : '', $sizes);
					}
				} catch (\Throwable $e) {
					$warnings[] = 'Не удалось проверить таблицу ' . $table . ': ' . $e->getMessage();
				}
			}

			ksort($sizes, SORT_NATURAL | SORT_FLAG_CASE);
			return $stats;
		}

		protected static function extract($content, array &$sizes)
		{
			if (!preg_match_all('/(?<![A-Za-z0-9_])([rcfts]\d{1,4}x\d{1,4}r?)(?![A-Za-z0-9_])/i', (string) $content, $matches)) {
				return;
			}

			foreach ($matches[1] as $candidate) {
				$parsed = ThumbnailPresetPolicy::parse($candidate);
				if ($parsed !== null) {
					$sizes[$parsed['size']] = true;
				}
			}
		}

		protected static function projectTable($table)
		{
			foreach (self::prefixes() as $prefix) {
				if ($table === $prefix || strpos($table, $prefix . '_') === 0) {
					return true;
				}
			}

			return false;
		}

		protected static function prefixes()
		{
			$prefixes = array();
			foreach (array('system', 'content', 'catalog', 'module', 'basket', 'contacts', 'public_user', 'public_shell') as $domain) {
				try {
					$prefixes[] = (string) PublicConfiguration::prefix($domain);
				} catch (\Throwable $e) {
					// An optional domain may be absent in a minimal installation.
				}
			}

			return array_values(array_unique(array_filter($prefixes)));
		}

		protected static function excludedTable($table)
		{
			return (bool) preg_match('/_(?:constants|audit_log|module_events|not_found_log|referrer_log|users_session|api_idempotency)$/i', $table);
		}

		protected static function excludedPath($path)
		{
			$path = str_replace('\\', '/', (string) $path);
			return strpos($path, '/vendor/') !== false
				|| strpos($path, '/node_modules/') !== false
				|| strpos($path, '/tmp/') !== false
				|| strpos($path, '/storage/') !== false;
		}

		protected static function validIdentifier($value)
		{
			return preg_match('/^[A-Za-z0-9_]+$/', (string) $value) === 1;
		}

		protected static function relative($path)
		{
			return ltrim(str_replace('\\', '/', substr((string) $path, strlen(BASEPATH))), '/');
		}
	}
