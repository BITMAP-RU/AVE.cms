<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldInstallationAudit.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use DB;

	/** Preflight for excluding the root legacy fields directory from distributions. */
	class FieldInstallationAudit
	{
		public static function report($legacyRoot = null, $scanRuntime = true, array $databaseUsage = null)
		{
			$legacyRoot = $legacyRoot === null ? BASEPATH . '/fields' : (string) $legacyRoot;
			$registered = array_keys(FieldRegistry::all());
			sort($registered, SORT_NATURAL | SORT_FLAG_CASE);
			$usage = $databaseUsage === null ? self::usage() : $databaseUsage;
			$used = array_keys($usage);
			$legacy = self::legacyTypes($legacyRoot);
			$missing = array_values(array_diff($used, $registered));
			$legacyOnly = array_values(array_diff($legacy, $registered));
			$references = $scanRuntime ? self::runtimeReferences($legacyRoot) : array();

			return array(
				'safe_to_remove' => !$missing && !$references,
				'legacy_root' => $legacyRoot,
				'legacy_exists' => is_dir($legacyRoot),
				'legacy_types' => $legacy,
				'registered_types' => $registered,
				'database_usage' => $usage,
				'missing_handlers' => $missing,
				'legacy_only_types' => $legacyOnly,
				'runtime_references' => $references,
				'runtime_scan_performed' => (bool) $scanRuntime,
				'native_template_overrides' => self::templateOverrides(),
				'legacy_file_count' => self::fileCount($legacyRoot),
			);
		}

		public static function assertRemovable($legacyRoot = null)
		{
			$report = self::report($legacyRoot, true);
			if (!$report['safe_to_remove']) {
				$parts = array();
				if ($report['missing_handlers']) {
					$parts[] = 'нет обработчиков: ' . implode(', ', $report['missing_handlers']);
				}

				if ($report['runtime_references']) {
					$parts[] = 'остались подключения legacy fields: ' . count($report['runtime_references']);
				}

				throw new \RuntimeException('Папку fields удалять рано: ' . implode('; ', $parts));
			}

			return $report;
		}

		protected static function usage()
		{
			$rows = DB::query(
				'SELECT rubric_field_type, COUNT(*) AS total FROM %b GROUP BY rubric_field_type ORDER BY rubric_field_type',
				ContentTables::table('rubric_fields')
			)->getAll();
			$usage = array();
			foreach ($rows ?: array() as $row) {
				$code = trim((string) $row['rubric_field_type']);
				if ($code !== '') {
					$usage[$code] = (int) $row['total'];
				}
			}

			return $usage;
		}

		protected static function legacyTypes($root)
		{
			if (!is_dir($root)) { return array(); }
			$types = array();
			foreach (scandir($root) ?: array() as $entry) {
				if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) { continue; }
				if (is_dir(rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $entry)) { $types[] = (string) $entry; }
			}

			sort($types, SORT_NATURAL | SORT_FLAG_CASE);
			return $types;
		}

		protected static function runtimeReferences($legacyRoot)
		{
			$roots = array(BASEPATH . '/system', \App\Common\AdminLocation::path(), BASEPATH . '/modules');
			$references = array();
			foreach ($roots as $root) {
				if (!is_dir($root)) { continue; }
				$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
				foreach ($iterator as $file) {
					if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
					$path = str_replace('\\', '/', $file->getPathname());
					if ($path === str_replace('\\', '/', __FILE__) || strpos($path, '/vendor/') !== false) { continue; }
					$content = @file_get_contents($file->getPathname());
					if ($content === false) { continue; }
					if (preg_match('~(?:BASE(?:PATH|_DIR)|dirname\s*\([^)]*\))\s*\.\s*[\'\"]/?fields/|/fields/[A-Za-z0-9_-]+/field\.php~', $content)) {
						$references[] = str_replace(rtrim(str_replace('\\', '/', BASEPATH), '/') . '/', '', $path);
					}
				}
			}

			sort($references, SORT_NATURAL | SORT_FLAG_CASE);
			return array_values(array_unique($references));
		}

		protected static function templateOverrides()
		{
			$root = __DIR__ . '/Templates';
			if (!is_dir($root)) { return array(); }
			$files = array();
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
					$files[] = str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $file->getPathname()));
				}
			}

			sort($files, SORT_NATURAL | SORT_FLAG_CASE);
			return $files;
		}

		protected static function fileCount($root)
		{
			if (!is_dir($root)) { return 0; }
			$count = 0;
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) { if ($file->isFile()) { $count++; } }
			return $count;
		}
	}
