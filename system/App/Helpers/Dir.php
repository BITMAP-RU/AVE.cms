<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Dir.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	class Dir
	{
		protected function __construct()
		{
			//
		}

		/**
		 * Создать директорию
		 *
		 * Рекурсивно создает директорию по указанному пути, если она не существует.
		 *
		 * @param string $dir Полный путь к создаваемой директории.
		 * @param int $chmod Права доступа для директории в восьмеричном формате (по умолчанию 0775).
		 * @return bool Возвращает true в случае успеха или если директория уже существует, иначе false.
		 *
		 * @example
		 * <code>
		 * Dir::create(BASEPATH . '/uploads/new_folder');
		 * </code>
		 */
		public static function create($dir, $chmod = 0775)
		{
			$dir = (string) $dir;
			return self::exists($dir) || mkdir($dir, $chmod, true) || is_dir($dir);
		}

		/**
		 * Проверить существование директории
		 *
		 * Проверяет, существует ли по указанному пути директория.
		 *
		 * @param string $dir Полный путь к директории для проверки.
		 * @return bool Возвращает true, если директория существует, иначе false.
		 *
		 * @example
		 * <code>
		 * if (Dir::exists(BASEPATH . '/uploads')) {
		 *     // Директория существует
		 * }
		 * </code>
		 */
		public static function exists($dir)
		{
			$dir = (string) $dir;
			return file_exists($dir) && is_dir($dir);
		}

		/**
		 * Проверить права доступа к директории
		 *
		 * Возвращает права доступа к директории в виде четырехзначной восьмеричной строки (например, '0775').
		 *
		 * @param string $dir Полный путь к директории.
		 * @return string|false Права доступа в виде строки или false в случае ошибки.
		 *
		 * @example
		 * <code>
		 * $permissions = Dir::checkPerm(BASEPATH . '/uploads');
		 * // $permissions будет '0775' или аналогичным
		 * </code>
		 */
		public static function checkPerm($dir)
		{
			$dir = (string) $dir;
			clearstatcache();
			return substr(sprintf('%o', fileperms($dir)), -4);
		}

		/**
		 * Удалить директорию
		 *
		 * Рекурсивно удаляет директорию со всем ее содержимым.
		 *
		 * @param string $dir Полный путь к удаляемой директории.
		 * @return void
		 *
		 * @example
		 * <code>
		 * Dir::delete(BASEPATH . '/uploads/old_folder');
		 * </code>
		 */
		public static function delete($dir)
		{
			$dir = (string) $dir;
			if (is_dir($dir)) {
				$ob = scandir($dir);
				foreach ($ob as $o) {
					if ($o != '.' && $o != '..') {
						if (filetype($dir . '/' . $o) == 'dir') {
							self::delete($dir . '/' . $o);
						} else {
							unlink($dir . '/' . $o);
						}
					}
				}

				reset($ob);
				rmdir($dir);
			}
		}

		/**
		 * Сканировать директорию
		 *
		 * Возвращает массив с именами вложенных директорий. Файлы и системные директории '.' и '..' игнорируются.
		 *
		 * @param string $dir Полный путь к сканируемой директории.
		 * @return array|false Массив с именами директорий или false в случае ошибки.
		 *
		 * @example
		 * <code>
		 * $subfolders = Dir::scan(BASEPATH . '/uploads');
		 * </code>
		 */
		public static function scan($dir)
		{
			$dir = (string) $dir;
			if (is_dir($dir) && $dh = opendir($dir)) {
				$f = [];
				while ($fn = readdir($dh)) {
					if ($fn != '.' && $fn != '..' && is_dir($dir . '/' . $fn)) {
						$f[] = $fn;
					}
				}

				return $f;
			}

			return false;
		}

		/**
		 * Проверить права на запись в директорию
		 *
		 * Проверяет, можно ли создавать файлы в указанной директории.
		 *
		 * @param string $path Полный путь к директории.
		 * @return bool Возвращает true, если директория доступна для записи, иначе false.
		 *
		 * @example
		 * <code>
		 * if (Dir::writable(BASEPATH . '/uploads')) {
		 *     // Можно создавать файлы
		 * }
		 * </code>
		 */
		public static function writable($path)
		{
			$path = (string) $path;
			$file = tempnam($path, 'writable');
			if ($file !== false) {
				File::delete($file);
				return true;
			}

			return false;
		}

		/**
		 * Получить размер директории
		 *
		 * Рекурсивно вычисляет общий размер всех файлов в директории и ее поддиректориях.
		 *
		 * @param string $path Полный путь к директории.
		 * @return int Общий размер в байтах.
		 *
		 * @example
		 * <code>
		 * $size = Dir::size(BASEPATH . '/uploads');
		 * echo 'Размер: ' . round($size / 1024 / 1024, 2) . ' MB';
		 * </code>
		 */
		public static function size($path)
		{
			$path = (string) $path;
			$total_size = 0;
			$files = scandir($path);
			$clean_path = rtrim($path, '/') . '/';
			foreach ($files as $t) {
				if ($t <> "." && $t <> "..") {
					$current_file = $clean_path . $t;
					if (is_dir($current_file)) {
						$total_size += self::size($current_file);
					} else {
						$total_size += filesize($current_file);
					}
				}
			}

			return $total_size;
		}

		/**
		 * Скопировать директорию
		 *
		 * Рекурсивно копирует содержимое одной директории в другую.
		 *
		 * @param string $src Исходный путь к директории.
		 * @param string $dst Целевой путь.
		 * @return void
		 *
		 * @example
		 * <code>
		 * Dir::copy(BASEPATH . '/source', BASEPATH . '/destination');
		 * </code>
		 */
		public static function copy($src, $dst)
		{
			$dir = opendir($src);
			self::create($dst);
			while (false !== ($file = readdir($dir))) {
				if (($file != '.') && ($file != '..')) {
					if (is_dir($src . '/' . $file)) {
						self::copy($src . '/' . $file, $dst . '/' . $file);
					} else {
						copy($src . '/' . $file, $dst . '/' . $file);
					}
				}
			}

			closedir($dir);
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
