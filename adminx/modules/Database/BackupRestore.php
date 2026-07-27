<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/BackupRestore.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Database;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\CompiledModuleRegistry;
	use App\Common\Session;
	use App\Common\Twig;
	use DB;

	/** Проверяет и восстанавливает полный дамп с обязательной страховочной копией. */
	class BackupRestore
	{
		const MAX_ARCHIVE_BYTES = 536870912;
		const INSPECTION_SESSION_KEY = 'database_backup_inspection';
		const RUN_SESSION_KEY = 'database_backup_restore_run';

		public static function inspect($name, $includeToken = true)
		{
			$path = Backup::path($name);
			if ($path === null || strpos(basename($path), 'adminx_') !== 0) {
				throw new \RuntimeException('Для восстановления выберите полный дамп, созданный AVE.cms');
			}

			$size = (int) filesize($path);
			if ($size <= 0 || $size > self::MAX_ARCHIVE_BYTES) {
				throw new \RuntimeException('Размер резервной копии недопустим');
			}

			$summary = SqlDumpReader::scan($path);
			$summary['name'] = basename($path);
			$summary['size'] = $size;
			$summary['size_h'] = Model::human($size);
			$summary['hash'] = hash_file('sha256', $path);
			$summary['mtime'] = (int) filemtime($path);
			$summary['current_database'] = Model::databaseName();
			$summary['current_prefixes'] = implode(', ', Model::prefixes());
			$summary['warnings'] = self::warnings($summary);
			if ($includeToken) {
				$summary['token'] = self::token($summary);
				Session::set(self::INSPECTION_SESSION_KEY, $summary);
			}

			return $summary;
		}

		public static function prepare($name, $token, $actorId)
		{
			$summary = Session::get(self::INSPECTION_SESSION_KEY);
			if (!is_array($summary) || !isset($summary['name']) || !hash_equals((string) $summary['name'], basename((string) $name))) {
				throw new \RuntimeException('Проверка резервной копии устарела. Выполните её повторно');
			}

			if (!hash_equals(self::token($summary), (string) $token)) {
				throw new \RuntimeException('Проверка резервной копии устарела. Выполните её повторно');
			}

			$path = Backup::path($summary['name']);
			if ($path === null || (int) filesize($path) !== (int) $summary['size']
				|| (int) filemtime($path) !== (int) $summary['mtime']
				|| !hash_equals((string) $summary['hash'], (string) hash_file('sha256', $path))) {
				throw new \RuntimeException('Файл изменился после проверки. Выполните проверку повторно');
			}

			$id = date('YmdHis') . '-' . bin2hex(random_bytes(6));
			$runToken = bin2hex(random_bytes(24));
			$job = array(
				'id' => $id,
				'actor_id' => (int) $actorId,
				'status' => 'prepared',
				'stage' => 'prepare',
				'progress' => 2,
				'title' => 'Восстановление подготовлено',
				'detail' => 'Ожидание запуска защищённого процесса',
				'table' => '',
				'current' => 0,
				'total' => (int) $summary['table_count'],
				'error' => '',
				'result' => array(),
				'created_at' => date(DATE_ATOM),
				'updated_at' => date(DATE_ATOM),
			);
			self::saveJob($job);
			Session::set(self::RUN_SESSION_KEY, array(
				'job_id' => $id,
				'token_hash' => hash('sha256', $runToken),
				'summary' => $summary,
			));
			Session::del(self::INSPECTION_SESSION_KEY);

			return array('job' => $job, 'run_token' => $runToken);
		}

		public static function status($id, $actorId)
		{
			$job = self::loadJob($id);
			if ((int) $job['actor_id'] !== (int) $actorId) {
				throw new \RuntimeException('Задание восстановления принадлежит другому пользователю');
			}

			return $job;
		}

		public static function restore($jobId, $token, callable $progress = null)
		{
			$run = Session::get(self::RUN_SESSION_KEY);
			if (!is_array($run) || empty($run['job_id']) || !hash_equals((string) $run['job_id'], (string) $jobId)
				|| empty($run['token_hash']) || !hash_equals((string) $run['token_hash'], hash('sha256', (string) $token))
				|| empty($run['summary']) || !is_array($run['summary'])) {
				throw new \RuntimeException('Запуск восстановления устарел. Проверьте копию повторно');
			}

			$summary = $run['summary'];
			$job = self::loadJob($jobId);
			Session::del(self::RUN_SESSION_KEY);
			$emit = function (array $payload) use (&$job, $progress) {
				$job = array_merge($job, $payload);
				$job['updated_at'] = date(DATE_ATOM);
				self::saveJob($job);
				if ($progress) { call_user_func($progress, $job); }
			};
			$emit(array('status' => 'running', 'stage' => 'verify', 'progress' => 5, 'title' => 'Проверяем исходный файл', 'detail' => $summary['name']));

			$lockPath = Backup::dir() . DS . '.adminx-restore.lock';
			$lock = fopen($lockPath, 'c');
			if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
				if ($lock) { fclose($lock); }
				$message = 'Другое восстановление уже выполняется';
				$emit(array('status' => 'failed', 'stage' => 'failed', 'progress' => 100, 'title' => 'Восстановление не запущено', 'detail' => $message, 'error' => $message));
				throw new \RuntimeException($message);
			}

			$safety = null;
			try {
				$emit(array('stage' => 'backup', 'progress' => 10, 'title' => 'Создаём страховочную копию', 'detail' => 'Рабочая база пока не изменяется'));
				$safety = Backup::create('before_restore', function (array $state) use ($emit) {
					$total = max(1, (int) $state['total']);
					$percent = 10 + (int) floor(((max(1, (int) $state['current']) - 1) / $total) * 25);
					$emit(array(
						'stage' => 'backup',
						'progress' => $percent,
						'title' => 'Страховочная копия',
						'detail' => (string) $state['table'] . (!empty($state['rows']) ? ', строк: ' . (int) $state['rows'] : ''),
						'table' => (string) $state['table'],
						'current' => (int) $state['current'],
						'total' => (int) $state['total'],
					));
				});
				$path = Backup::path($summary['name']);
				if ($path === null || !hash_equals($summary['hash'], hash_file('sha256', $path))) {
					throw new \RuntimeException('Файл изменился после проверки. Восстановление отменено');
				}

				$emit(array('stage' => 'clean', 'progress' => 37, 'title' => 'Подготавливаем рабочую схему', 'detail' => 'Удаляем таблицы текущей установки', 'table' => '', 'current' => 0));
				self::dropCurrentTables(function ($table, $current, $total) use ($emit) {
					$emit(array(
						'stage' => 'clean',
						'progress' => 37 + (int) floor(($current / max(1, $total)) * 6),
						'title' => 'Подготавливаем рабочую схему',
						'detail' => $table,
						'table' => $table,
						'current' => $current,
						'total' => $total,
					));
				});
				$emit(array('stage' => 'restore', 'progress' => 44, 'title' => 'Восстанавливаем таблицы', 'detail' => 'Начинаем импорт резервной копии', 'current' => 0, 'total' => (int) $summary['table_count']));
				self::apply($path, (int) $summary['table_count'], function ($table, $current, $total, $statements) use ($emit) {
					$emit(array(
						'stage' => 'restore',
						'progress' => 44 + (int) floor(($current / max(1, $total)) * 45),
						'title' => 'Восстанавливаем таблицы',
						'detail' => $table . ', SQL-команд: ' . $statements,
						'table' => $table,
						'current' => $current,
						'total' => $total,
					));
				});
				$emit(array('stage' => 'cache', 'progress' => 92, 'title' => 'Обновляем runtime', 'detail' => 'Очищаем кеши и реестр модулей', 'table' => '', 'current' => 0));
				self::clearRuntimeCaches();
			} catch (\Throwable $e) {
				$originalMessage = $e->getMessage();
				$rollbackOk = false;
				if ($safety && !empty($safety['name'])) {
					try {
						$emit(array('stage' => 'rollback', 'progress' => 94, 'title' => 'Возвращаем исходную базу', 'detail' => $originalMessage, 'table' => '', 'current' => 0));
						self::dropCurrentTables();
						self::apply(Backup::path($safety['name']), 0, function ($table) use ($emit) {
							$emit(array('stage' => 'rollback', 'progress' => 96, 'title' => 'Возвращаем исходную базу', 'detail' => $table, 'table' => $table));
						});
						self::clearRuntimeCaches();
						$rollbackOk = true;
					} catch (\Throwable $rollbackError) {
						error_log('Database backup rollback failed: ' . $rollbackError->getMessage());
					}
				}

				error_log('Database backup restore failed: ' . $e->getMessage());
				$message = $rollbackOk
					? 'Восстановление не выполнено: ' . $originalMessage . '. Исходная база возвращена из страховочной копии'
					: 'Восстановление прервано: ' . $originalMessage . '. Автоматический откат не выполнен; используйте страховочный бэкап';
				$emit(array('status' => 'failed', 'stage' => 'failed', 'progress' => 100, 'title' => 'Восстановление остановлено', 'detail' => $message, 'error' => $message));
				throw new \RuntimeException($message);
			} finally {
				flock($lock, LOCK_UN);
				fclose($lock);
			}

			$result = array(
				'name' => $summary['name'],
				'tables' => $summary['table_count'],
				'rows' => $summary['inserts'],
				'safety_backup' => isset($safety['name']) ? $safety['name'] : '',
			);
			$emit(array('status' => 'completed', 'stage' => 'complete', 'progress' => 100, 'title' => 'База восстановлена', 'detail' => 'Все таблицы импортированы и кеш очищен', 'result' => $result));

			return $result;
		}

		protected static function apply($path, $expectedTables = 0, callable $progress = null)
		{
			if ($path === null || !is_file($path)) {
				throw new \RuntimeException('Файл резервной копии не найден');
			}

			$mysqli = DB::mysqli();
			$insertBatch = array();
			$insertBytes = 0;
			$insertTable = '';
			$currentTable = '';
			$enumFallbacks = array();
			$tableIndex = 0;
			$statements = 0;
			$flush = function () use ($mysqli, &$insertBatch, &$insertBytes, &$insertTable) {
				if ($insertBatch) { self::executeInsertBatch($mysqli, $insertBatch, $insertTable); }
				$insertBatch = array();
				$insertBytes = 0;
				$insertTable = '';
			};
			SqlDumpReader::scan($path, function ($statement, $info) use ($mysqli, &$insertBatch, &$insertBytes, &$insertTable, &$currentTable, &$enumFallbacks, &$tableIndex, &$statements, $expectedTables, $progress, $flush) {
				$table = isset($info['table']) ? (string) $info['table'] : '';
				if ($table !== '' && $table !== $currentTable) {
					$flush();
					$currentTable = $table;
					$enumFallbacks = array();
					$tableIndex++;
					$statements = 0;
				}

				$statements++;
				if ($progress && $table !== '' && ($statements === 1 || $statements % 500 === 0)) {
					call_user_func($progress, $table, $tableIndex, max($tableIndex, (int) $expectedTables), $statements);
				}

				if ($info['type'] === 'create') {
					$enumFallbacks = SqlDumpReader::enumFallbacks($statement);
				} elseif ($info['type'] === 'insert') {
					$statement = SqlDumpReader::normalizeEnumInsert($statement, $enumFallbacks);
					$insertTable = $table;
					$insertBatch[] = $statement;
					$insertBytes += strlen($statement);
					if (count($insertBatch) >= 200 || $insertBytes >= 2097152) { $flush(); }
					return;
				}

				$flush();
				if (!$mysqli->query($statement)) {
					throw new \RuntimeException(($table !== '' ? 'таблица ' . $table . ': ' : '') . $mysqli->error);
				}
			});
			$flush();
		}

		protected static function executeInsertBatch($mysqli, array $statements, $table = '')
		{
			if (count($statements) === 1) {
				if (!$mysqli->query($statements[0])) {
					throw new \RuntimeException(($table !== '' ? 'таблица ' . $table . ': ' : '') . $mysqli->error);
				}

				return;
			}

			if (!$mysqli->multi_query(implode(";\n", $statements))) {
				throw new \RuntimeException(($table !== '' ? 'таблица ' . $table . ': ' : '') . $mysqli->error);
			}

			do {
				$result = $mysqli->store_result();
				if ($result) { $result->free(); }
				if (!$mysqli->more_results()) { break; }
			} while ($mysqli->next_result());

			if ($mysqli->errno) {
				throw new \RuntimeException(($table !== '' ? 'таблица ' . $table . ': ' : '') . $mysqli->error);
			}
		}

		/** Удаляет текущий набор, чтобы восстановление не оставляло таблицы из другой версии. */
		protected static function dropCurrentTables(callable $progress = null)
		{
			$mysqli = DB::mysqli();
			if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=0')) {
				throw new \RuntimeException('Не удалось отключить проверку внешних ключей');
			}

			try {
				$tables = Model::names();
				$total = count($tables);
				foreach ($tables as $index => $table) {
					if (!$mysqli->query('DROP TABLE IF EXISTS `' . $table . '`')) {
						throw new \RuntimeException('Не удалось подготовить текущую схему к восстановлению');
					}

					if ($progress) { call_user_func($progress, $table, $index + 1, $total); }
				}
			} finally {
				$mysqli->query('SET FOREIGN_KEY_CHECKS=1');
			}
		}

		protected static function token(array $summary)
		{
			$payload = implode('|', array(
				$summary['name'],
				$summary['hash'],
				$summary['size'],
				isset($summary['mtime']) ? $summary['mtime'] : 0,
				$summary['current_database'],
				$summary['current_prefixes'],
			));

			return hash_hmac('sha256', $payload, (string) Session::csrfToken());
		}

		protected static function warnings(array $summary)
		{
			$warnings = array();
			if ($summary['database'] !== '' && $summary['database'] !== $summary['current_database']) {
				$warnings[] = 'Дамп создан в базе «' . $summary['database'] . '», текущая база — «' . $summary['current_database'] . '».';
			}

			if ($summary['prefixes'] !== '' && $summary['prefixes'] !== '{{prefix}}'
				&& $summary['prefixes'] !== $summary['current_prefixes']) {
				$warnings[] = 'Префикс в заголовке отличается от текущего; фактические имена таблиц уже проверены.';
			}

			return $warnings;
		}

		protected static function clearRuntimeCaches()
		{
			Cache::flush();
			CompiledModuleRegistry::invalidate();
			Twig::resetInstance();
			foreach (array('sql', 'twig', 'tpl', 'content-snapshots', 'module', 'feeds') as $directory) {
				self::removeContents(BASEPATH . DS . 'tmp' . DS . 'cache' . DS . $directory);
			}
		}

		protected static function jobRoot()
		{
			$root = BASEPATH . DS . 'storage' . DS . 'database-restore' . DS . 'jobs';
			if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root)) {
				throw new \RuntimeException('Не удалось создать каталог заданий восстановления');
			}

			return $root;
		}

		protected static function jobFile($id)
		{
			$id = trim((string) $id);
			if (!preg_match('/^[0-9]{14}-[a-f0-9]{12}$/', $id)) {
				throw new \InvalidArgumentException('Некорректный идентификатор восстановления');
			}

			return self::jobRoot() . DS . $id . '.json';
		}

		protected static function loadJob($id)
		{
			$file = self::jobFile($id);
			$job = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
			if (!is_array($job) || empty($job['id'])) { throw new \RuntimeException('Задание восстановления не найдено'); }
			return $job;
		}

		protected static function saveJob(array $job)
		{
			$file = self::jobFile($job['id']);
			$temporary = $file . '.tmp-' . bin2hex(random_bytes(4));
			$content = json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
			if (file_put_contents($temporary, $content, LOCK_EX) === false || !@rename($temporary, $file)) {
				@unlink($temporary);
				throw new \RuntimeException('Не удалось сохранить состояние восстановления');
			}

			@chmod($file, 0600);
		}

		protected static function removeContents($directory)
		{
			if (!is_dir($directory)) { return; }
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($iterator as $item) {
				$item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
			}
		}
	}
