<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         recovery.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	define('BASEPATH', str_replace('\\', '/', __DIR__));
	define('DS', DIRECTORY_SEPARATOR);
	@ini_set('display_errors', '0');
	header('Cache-Control: no-store');
	header('X-Robots-Tag: noindex, nofollow, noarchive');
	$maintenanceFile = BASEPATH . '/storage/updates/maintenance.json';
	$maintenance = is_file($maintenanceFile) ? json_decode((string) file_get_contents($maintenanceFile), true) : null;
	$token = isset($_POST['token']) ? (string) $_POST['token'] : (isset($_GET['token']) ? (string) $_GET['token'] : '');
	if (!is_array($maintenance) || empty($maintenance['job_id']) || empty($maintenance['recovery_token_hash'])
		|| !preg_match('/^[a-f0-9]{48}$/', $token) || !hash_equals((string) $maintenance['recovery_token_hash'], hash('sha256', $token))) {
		http_response_code(404);
		exit('Not found');
	}

	$jobFile = BASEPATH . '/storage/updates/jobs/' . basename((string) $maintenance['job_id']) . '/job.json';
	$job = is_file($jobFile) ? json_decode((string) file_get_contents($jobFile), true) : null;
	if (!is_array($job) || empty($job['journal'])) { http_response_code(404); exit('Not found'); }
	$error = '';
	$done = false;
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'rollback') {
		try {
			foreach (array_reverse($job['journal']) as $entry) {
				$actual = isset($entry['actual']) ? (string) $entry['actual'] : '';
				if ($actual === '' || strpos(str_replace('\\', '/', $actual), BASEPATH . '/') !== 0) { throw new RuntimeException('Recovery journal contains an unsafe path'); }
				if (!empty($entry['existed'])) {
					$backup = isset($entry['backup']) ? (string) $entry['backup'] : '';
					if (!is_file($backup)) { throw new RuntimeException('Recovery backup is incomplete'); }
					@mkdir(dirname($actual), 0755, true);
					$temporary = $actual . '.recovery-' . bin2hex(random_bytes(4));
					if (!copy($backup, $temporary) || !@rename($temporary, $actual)) { @unlink($temporary); throw new RuntimeException('Cannot restore ' . basename($actual)); }
				} elseif (is_file($actual) && !@unlink($actual)) { throw new RuntimeException('Cannot remove added file'); }
			}

			if (!empty($job['database_applied']) && !empty($job['database_backup']) && is_dir($job['database_backup'])) {
				require_once BASEPATH . '/system/preload.php';
				if (\App\Common\CoreUpdate\DatabaseBackup::complete($job['database_backup'])) { \App\Common\CoreUpdate\DatabaseBackup::restore($job['database_backup']); }
			}

			@unlink($maintenanceFile);
			$job['status'] = 'rolled_back';
			$job['message'] = 'Аварийный откат выполнен';
			$job['updated_at'] = date(DATE_ATOM);
			$tmp = $jobFile . '.tmp-' . bin2hex(random_bytes(4));
			file_put_contents($tmp, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
			@rename($tmp, $jobFile);
			$done = true;
		} catch (Throwable $exception) { $error = $exception->getMessage(); }
	}

	function recoveryEscape($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Восстановление AVE.cms</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6f8;color:#1f2937;font:16px system-ui,sans-serif}.card{width:min(560px,calc(100% - 32px));background:#fff;border:1px solid #dbe1e8;border-radius:8px;padding:28px;box-sizing:border-box;box-shadow:0 16px 40px rgba(15,23,42,.08)}h1{font-size:24px;margin:0 0 12px}p{color:#64748b;line-height:1.6}.error{color:#b42318}.ok{color:#067647}button,a{display:inline-flex;padding:10px 16px;border-radius:6px;border:0;background:#b42318;color:#fff;text-decoration:none;font-weight:600;cursor:pointer}</style></head><body><main class="card"><?php if ($done): ?><h1 class="ok">Откат завершён</h1><p>Файлы и база данных восстановлены. Можно вернуться на сайт и проверить журнал обновления.</p><a href="./">Перейти на сайт</a><?php else: ?><h1>Аварийное восстановление</h1><p>Будет восстановлено состояние до патча <b><?= recoveryEscape($job['manifest']['id']) ?></b>. Используйте действие только если контрольная проверка или панель управления не запускается.</p><?php if ($error !== ''): ?><p class="error"><?= recoveryEscape($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="token" value="<?= recoveryEscape($token) ?>"><button type="submit" name="confirm" value="rollback">Выполнить откат</button></form><?php endif; ?></main></body></html>
