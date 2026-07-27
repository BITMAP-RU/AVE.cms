<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         index.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	define('START_MICROTIME', microtime());
	define('START_MEMORY', memory_get_usage());
	define('BASEPATH', str_replace('\\', '/', __DIR__));

	if (!@filesize(BASEPATH . '/configs/db.config.php')) {
		if (!is_file(BASEPATH . '/storage/installed.lock')) {
			$basePath = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php')), '/');
			header('Location: ' . $basePath . '/setup/', true, 302);
			exit;
		}

		http_response_code(503);
		header('Content-Type: text/plain; charset=UTF-8');
		echo 'Конфигурация базы данных отсутствует. Восстановите configs/db.config.php.';
		exit;
	}

	if (is_file(BASEPATH . '/storage/updates/maintenance.json')) {
		http_response_code(503);
		header('Content-Type: text/html; charset=UTF-8');
		header('Retry-After: 60');
		header('Cache-Control: no-store');
		echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>Обновление AVE.cms</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6f8;color:#1f2937;font:16px system-ui,sans-serif}.box{max-width:520px;padding:32px;text-align:center}h1{font-size:24px;margin:0 0 12px}p{color:#64748b;line-height:1.6}</style>'
			. '<div class="box"><h1>Сайт обновляется</h1><p>AVE.cms устанавливает системное обновление. Страница станет доступна автоматически после контрольной проверки.</p></div></html>';
		exit;
	}

	require_once BASEPATH . '/system/preload.php';

	\App\Frontend\PublicKernel::run();
