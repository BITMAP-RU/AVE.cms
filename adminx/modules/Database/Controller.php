<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Database/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Database;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\ModuleManager;
	use App\Common\Permission;
	use App\Helpers\Request;
	use App\Helpers\Response;

	/**
	 * База данных: таблицы + обслуживание + резервные копии. Мутации — ajax по
	 * контракту success/error с CSRF и правом manage_database. Операции только над
	 * таблицами текущего префикса.
	 */
	class Controller extends BaseController
	{
		/** GET /database */
		public function index(array $params = [])
		{
			AdminAssets::addStyle($this->base() . '/modules/Database/assets/database.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Database/assets/database.js', 50);

			return $this->render('@database/index.twig', [
				'tables'     => Model::tables(),
				'stats'      => Model::stats(),
				'backups'    => Backup::all(),
				'backup_keep' => Backup::keep(),
				'backup_keep_max' => Backup::KEEP_MAX,
				'schema'     => ModuleManager::coreSchemaStatus(),
				'can_manage' => Permission::check('manage_database'),
			]);
		}

		/** POST /database/maintenance — OPTIMIZE/REPAIR/ANALYZE (table='*' = все). */
		public function maintenance(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$op = Request::postStr('op');
			$table = Request::postStr('table');

			if ($table === '*') {
				$ok = 0; $fail = 0;
				foreach (Model::names() as $name) {
					$r = Model::maintenance($op, $name);
					$r['ok'] ? $ok++ : $fail++;
				}

				if ($ok === 0 && $fail === 0) {
					return $this->error('Нет таблиц для обработки', [], 422);
				}

				return $this->success('Готово: ' . $ok . ' таблиц' . ($fail ? ', ошибок: ' . $fail : ''));
			}

			$result = Model::maintenance($op, $table);
			if (!$result['ok']) {
				return $this->error($result['table'] . ': ' . $result['message'], [], 422);
			}

			return $this->success($result['table'] . ': ' . $result['message'], [
				'data' => ['table' => $result['table']],
			]);
		}

		/** POST /database/backup — создать дамп. */
		public function backupCreate(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			try {
				$info = Backup::create();
			} catch (\Throwable $e) {
				return $this->error('Не удалось создать бэкап: ' . $e->getMessage(), [], 500);
			}

			$message = 'Бэкап создан: ' . $info['name'] . ' (' . $info['tables'] . ' таблиц)';
			if (!empty($info['pruned'])) {
				$message .= '. Удалено старых копий: ' . (int) $info['pruned'];
			}

			return $this->success($message, [
				'redirect' => $this->base() . '/database?tab=backups',
			]);
		}

		/** POST /database/backup/keep — сколько копий хранить. */
		public function backupKeep(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$keep = Backup::setKeep(Request::postInt('keep', Backup::KEEP_DEFAULT));
			$pruned = Backup::prune();
			$message = $keep > 0
				? 'Хранить последних копий: ' . $keep
				: 'Ротация выключена, копии не удаляются';
			if ($pruned['deleted'] > 0) { $message .= '. Удалено сейчас: ' . (int) $pruned['deleted']; }
			return $this->success($message, array('redirect' => $this->base() . '/database?tab=backups'));
		}

		/** POST /database/backup/upload — загрузить и проверить внешний файл дампа. */
		public function backupUpload(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			try {
				$info = Backup::upload(isset($_FILES['backup']) ? (array) $_FILES['backup'] : array());
				return $this->success('Копия загружена и проверена: ' . $info['name'], array(
					'data' => $info,
					'redirect' => $this->base() . '/database?tab=backups',
				));
			} catch (\Throwable $e) {
				return $this->error('Не удалось загрузить копию: ' . $e->getMessage(), array(), 422);
			}
		}

		/** GET /database/backup/download?file=... — скачать дамп. */
		public function backupDownload(array $params = [])
		{
			if (!Permission::check('view_database')) {
				Response::forbidden();
				return null;
			}

			$path = Backup::path(Request::getStr('file'));
			if ($path === null) {
				Response::notFound('Файл не найден');
				return null;
			}

			$contentType = substr($path, -3) === '.gz' ? 'application/gzip' : 'application/sql';
			Response::download($path, basename($path), $contentType);
			return null;
		}

		/** POST /database/backup/delete — удалить дамп. */
		public function backupDelete(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$file = Request::postStr('file');
			if (!Backup::delete($file)) {
				return $this->error('Не удалось удалить файл', [], 422);
			}

			return $this->success('Бэкап удалён');
		}

		/** POST /database/backup/inspect — проверить состав дампа до подтверждения. */
		public function backupInspect(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			try {
				$summary = BackupRestore::inspect(Request::postStr('file'));
				return $this->success('Резервная копия проверена', array('data' => $summary));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		/** POST /database/backup/restore/start — подтвердить и подготовить потоковое восстановление. */
		public function backupRestoreStart(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$file = Request::postStr('file');
			try {
				$prepared = BackupRestore::prepare($file, Request::postStr('restore_token'), Auth::id());
				return $this->success('Восстановление подготовлено', array('data' => $prepared));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		/** POST /database/backup/restore/run — NDJSON-поток фактического восстановления. */
		public function backupRestoreRun(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			$jobId = Request::postStr('job_id');
			$runToken = Request::postStr('run_token');
			@set_time_limit(0);
			ignore_user_abort(true);
			header('Content-Type: application/x-ndjson; charset=UTF-8');
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header('X-Accel-Buffering: no');
			@ini_set('output_buffering', '0');
			@ini_set('zlib.output_compression', '0');
			while (ob_get_level() > 0) { @ob_end_flush(); }
			ob_implicit_flush(true);
			$emit = function (array $payload) {
				echo json_encode(array('type' => 'progress', 'job' => $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
				@ob_flush();
				flush();
			};

			try {
				$result = BackupRestore::restore($jobId, $runToken, $emit);
				try {
					$user = Auth::user();
					AuditLog::record('database.backup_restored', array(
						'actor_id' => Auth::id(),
						'actor_name' => $user && isset($user['name']) ? $user['name'] : '',
						'target_type' => 'database_backup',
						'target_id' => null,
						'meta' => $result,
					));
				} catch (\Throwable $auditError) {
					error_log('Database restore audit failed: ' . $auditError->getMessage());
				}

				echo json_encode(array(
					'type' => 'complete',
					'message' => 'База восстановлена из ' . $result['name'],
					'result' => $result,
					'redirect' => $this->base() . '/database?tab=backups',
				), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
			} catch (\Throwable $e) {
				try {
					AuditLog::record('database.backup_restore_failed', array(
						'actor_id' => Auth::id(),
						'target_type' => 'database_backup',
						'target_id' => null,
						'meta' => array('job_id' => $jobId, 'reason' => $e->getMessage()),
					));
				} catch (\Throwable $auditError) {
					error_log('Database restore failure audit failed: ' . $auditError->getMessage());
				}

				echo json_encode(array('type' => 'error', 'message' => $e->getMessage()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
			}

			return null;
		}

		/** POST /database/backup/restore/status — состояние на случай обрыва потока. */
		public function backupRestoreStatus(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			try {
				return $this->success('Состояние восстановления', array(
					'data' => array('job' => BackupRestore::status(Request::postStr('job_id'), Auth::id())),
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 404);
			}
		}

		/** POST /database/engines/innodb — завершить перевод рабочей схемы на InnoDB. */
		public function convertToInnoDb(array $params = array())
		{
			if (($resp = $this->guard()) !== null) { return $resp; }
			try {
				$result = Model::convertToInnoDb();
				$converted = count($result['converted']);
				$failed = count($result['failed']);
				if ($failed > 0) {
					return $this->error('Переведено: ' . $converted . ', ошибок: ' . $failed, array('data' => $result), 422);
				}

				return $this->success($converted > 0 ? 'Таблицы переведены в InnoDB: ' . $converted : 'Таблиц MyISAM не осталось', array('data' => $result));
			} catch (\Throwable $e) {
				return $this->error('Не удалось изменить движок таблиц: ' . $e->getMessage(), array(), 422);
			}
		}

		/** POST /database/schema/update — применить новые миграции ядровых разделов. */
		public function updateSchema(array $params = array())
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			try {
				$results = ModuleManager::updateCoreSchema();
				$applied = 0;
				foreach ($results as $result) {
					if (isset($result['status']) && $result['status'] === 'applied') {
						$applied++;
					}
				}

				$permissions = Permission::syncRegistryToDb();

				return $this->success($applied > 0
					? 'Применено миграций ядра: ' . $applied
					: 'Все миграции ядра уже применены', array('data' => array('applied' => $applied, 'permissions' => $permissions)));
			} catch (\Throwable $e) {
				return $this->error('Не удалось применить миграции ядра: ' . $e->getMessage(), array(), 422);
			}
		}

		// ------------------------------------------------------------------ //

		protected function guard()
		{
			return $this->guardPermission('manage_database');
		}
	}
