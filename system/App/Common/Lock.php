<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Lock.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Межпроцессная блокировка на основе flock().
	 *
	 * Нужна для атомарного read-modify-write поверх файлового кеша: сам Cache пишет
	 * значения через atomic rename (новый inode на каждую запись), поэтому flock по
	 * файлу-значению не держится. Здесь блокировка живёт в отдельном lock-файле,
	 * который никогда не переименовывается — flock стабилен на его inode.
	 *
	 * Использование:
	 *   $count = Lock::run('rl:' . $ip, function () use ($key) {
	 *       $data = Cache::get($key);
	 *       ... // read-modify-write без гонок
	 *       Cache::set($key, $data, $ttl);
	 *       return $data['attempts'];
	 *   });
	 */
	class Lock
	{
		/** @var string Директория lock-файлов */
		protected static $_dir = null;

		protected static function dir()
		{
			if (self::$_dir === null) {
				self::$_dir = rtrim(str_replace('\\', '/', BASEPATH), '/') . '/tmp/cache/locks/';
				if (!is_dir(self::$_dir)) {
					@mkdir(self::$_dir, 0775, true);
				}
			}

			return self::$_dir;
		}

		/**
		 * Выполнить $callback под эксклюзивной блокировкой $name.
		 *
		 * Если получить дескриптор lock-файла не удалось, callback всё равно
		 * выполняется (без блокировки) — доступность важнее строгой атомарности.
		 *
		 * @param  string   $name            Логическое имя ресурса
		 * @param  callable $callback        Критическая секция
		 * @param  float    $timeoutSeconds  Сколько ждать блокировку неблокирующими попытками
		 * @return mixed     Результат $callback
		 */
		public static function run($name, callable $callback, $timeoutSeconds = 5.0, $required = false)
		{
			$file = self::dir() . md5((string) $name) . '.lock';

			// Гарантируем существование lock-файла ДО борьбы за блокировку и затем
			// открываем уже существующий файл (без O_CREAT). На ФС со слабой
			// когерентностью каталога (сетевые/overlay-моунты) конкурентное создание
			// файла через 'c' может дать процессам рассинхронизированные дескрипторы,
			// и flock перестанет сериализовать доступ. После create+reopen все
			// процессы сходятся к одному inode.
			if (!is_file($file)) {
				$seed = @fopen($file, 'c');
				if ($seed !== false) {
					fclose($seed);
				}
			}

			$fp = @fopen($file, 'r+');
			if ($fp === false) {
				$fp = @fopen($file, 'c');
			}

			if ($fp === false) {
				if ($required) {
					throw new \RuntimeException('Не удалось создать обязательную блокировку: ' . (string) $name);
				}

				return $callback();
			}

			$locked = false;
			$deadline = microtime(true) + max(0.0, (float) $timeoutSeconds);

			do {
				if (flock($fp, LOCK_EX | LOCK_NB)) {
					$locked = true;
					break;
				}

				usleep(20000); // 20 мс
			} while (microtime(true) < $deadline);

			if (!$locked) {
				fclose($fp);
				throw new \RuntimeException('Не удалось получить блокировку: ' . (string) $name);
			}

			try {
				return $callback();
			} finally {
				if ($locked) {
					flock($fp, LOCK_UN);
				}

				fclose($fp);
			}
		}
	}
