<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/File.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || exit('Direct access to this location is not allowed.');




	class File
	{
		public static $mime_types = [
			'aac'		 => 'audio/aac',
			'atom'		 => 'application/atom+xml',
			'avi'		 => 'video/avi',
			'bmp'		 => 'image/x-ms-bmp',
			'c'			 => 'text/x-c',
			'class'		 => 'application/octet-stream',
			'css'		 => 'text/css',
			'csv'		 => 'text/csv',
			'deb'		 => 'application/x-deb',
			'dll'		 => 'application/x-msdownload',
			'dmg'		 => 'application/x-apple-diskimage',
			'doc'		 => 'application/msword',
			'docx'		 => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'exe'		 => 'application/octet-stream',
			'flv'		 => 'video/x-flv',
			'gif'		 => 'image/gif',
			'gz'		 => 'application/x-gzip',
			'h'			 => 'text/x-c',
			'htm'		 => 'text/html',
			'html'		 => 'text/html',
			'ini'		 => 'text/plain',
			'jar'		 => 'application/java-archive',
			'java'		 => 'text/x-java',
			'jpeg'		 => 'image/jpeg',
			'jpg'		 => 'image/jpeg',
			'js'		 => 'text/javascript',
			'json'		 => 'application/json',
			'mid'		 => 'audio/midi',
			'midi'		 => 'audio/midi',
			'mka'		 => 'audio/x-matroska',
			'mkv'		 => 'video/x-matroska',
			'mp3'		 => 'audio/mpeg',
			'mp4'		 => 'application/mp4',
			'mpeg'		 => 'video/mpeg',
			'mpg'		 => 'video/mpeg',
			'odt'		 => 'application/vnd.oasis.opendocument.text',
			'ogg'		 => 'audio/ogg',
			'pdf'		 => 'application/pdf',
			'php'		 => 'text/x-php',
			'png'		 => 'image/png',
			'psd'		 => 'image/vnd.adobe.photoshop',
			'py'		 => 'application/x-python',
			'ra'		 => 'audio/vnd.rn-realaudio',
			'ram'		 => 'audio/vnd.rn-realaudio',
			'rar'		 => 'application/x-rar-compressed',
			'rss'		 => 'application/rss+xml',
			'safariextz' => 'application/x-safari-extension',
			'sh'		 => 'text/x-shellscript',
			'shtml'		 => 'text/html',
			'swf'		 => 'application/x-shockwave-flash',
			'tar'		 => 'application/x-tar',
			'tif'		 => 'image/tiff',
			'tiff'		 => 'image/tiff',
			'torrent'	 => 'application/x-bittorrent',
			'txt'		 => 'text/plain',
			'wav'		 => 'audio/wav',
			'webp'		 => 'image/webp',
			'wma'		 => 'audio/x-ms-wma',
			'xls'		 => 'application/vnd.ms-excel',
			'xml'		 => 'text/xml',
			'zip'		 => 'application/zip',
		];


		/**
		 * Защищенный конструктор класса.
		 * Предотвращает создание экземпляра класса напрямую.
		 */
		protected function __construct()
		{
			//
		}


		/**
		 * Проверяет существование файла.
		 *
		 * @param string $filename Полный путь к файлу
		 * @return bool Возвращает true, если файл существует и является файлом, иначе false
		 */
		public static function exists ($filename)
		{
			$filename = (string) $filename;

			return (file_exists($filename) && is_file($filename));
		}


		/**
		 * Удаляет файл или массив файлов.
		 *
		 * @param string|array $filename Полный путь к файлу или массив путей к файлам
		 * @return bool Возвращает true при успешном удалении, иначе false
		 */
		public static function delete ($filename)
		{
			if (is_array($filename))
			{
				foreach ($filename as $file)
				{
					@unlink((string) $file);
				}

			}
			else
				{
					return @unlink((string) $filename);
				}

		}


		/**
		 * Переименовывает файл.
		 *
		 * @param string $from Полный путь к исходному файлу
		 * @param string $to Полный путь к новому файлу
		 * @return bool Возвращает true при успешном переименовании, иначе false
		 */
		public static function rename ($from, $to)
		{
			$from = (string) $from;
			$to	  = (string) $to;

			if ( ! self::exists($to))
				return rename($from, $to);

			return false;
		}


		/**
		 * Копирует файл.
		 *
		 * @param string $from Полный путь к исходному файлу
		 * @param string $to Полный путь к целевому файлу
		 * @return bool Возвращает true при успешном копировании, иначе false
		 */
		public static function copy ($from, $to)
		{
			$from = (string) $from;
			$to	  = (string) $to;

			if ( ! File::exists($from) || self::exists($to))
				return false;

			return copy($from, $to);
		}


		/**
		 * Получает расширение файла.
		 *
		 * @param string $filename Имя файла
		 * @return string Возвращает расширение файла
		 */
		public static function ext ($filename)
		{
			$filename = (string) $filename;

			return substr(strrchr($filename, '.'), 1);
		}


		/**
		 * Получает имя файла без расширения.
		 *
		 * @param string $filename Имя файла с расширением
		 * @return string Возвращает имя файла без расширения
		 */
		public static function name ($filename)
		{
			$filename = (string) $filename;

			return basename($filename, '.' . self::ext($filename));
		}


		/**
		 * Безопасное имя файла для пользовательской загрузки.
		 *
		 * @param string $name Имя без расширения или полный filename
		 * @return string
		 */
		public static function slugName ($name)
		{
			$name = Str::lower((string) $name);
			$name = preg_replace('/[^a-z0-9а-яё_-]+/iu', '-', $name);
			$name = trim($name, '-_');

			return $name !== '' ? $name : 'file';
		}


		/**
		 * Подобрать filename, которого ещё нет в каталоге.
		 *
		 * @param string $dir Абсолютный путь к каталогу
		 * @param string $base Имя без расширения
		 * @param string $ext Расширение без точки
		 * @return string
		 */
		public static function uniqueName ($dir, $base, $ext)
		{
			$base = self::slugName($base);
			$ext = Str::lower(trim((string) $ext, '.'));
			$filename = $ext !== '' ? $base . '.' . $ext : $base;
			$n = 2;

			while (is_file(rtrim((string) $dir, '/') . '/' . $filename))
			{
				$filename = $ext !== '' ? $base . '-' . $n . '.' . $ext : $base . '-' . $n;
				$n++;
			}

			return $filename;
		}


		/**
		 * Сканирует папку на наличие файлов.
		 *
		 * @param string $folder Путь к папке для сканирования
		 * @param mixed $type Тип файлов для поиска (необязательно)
		 * @return array|bool Возвращает массив найденных файлов или false в случае ошибки
		 */
		public static function scan ($folder, $type = null)
		{
			$data = array();

			if (is_dir($folder))
			{
				$iterator = new RecursiveDirectoryIterator($folder);

				foreach (new RecursiveIteratorIterator($iterator) as $file)
				{
					if ($type !== null)
					{
						if (is_array($type))
						{
							$file_ext = substr(strrchr($file->getFilename(), '.'), 1);

							if (in_array($file_ext, $type))
								if (strpos($file->getFilename(), $file_ext, 1))
									$data[] = $file->getFilename();
						}
						else
						{
							if (strpos($file->getFilename(), $type, 1))
								$data[] = $file->getFilename();
						}
					}
					else
					{
						if ($file->getFilename() !== '.' && $file->getFilename() !== '..')
							$data[] = $file->getFilename();
					}
				}

				return $data;
			}
			else
				{
					return false;
				}
		}


		/**
		 * Получает содержимое файла.
		 *
		 * @param string $filename Полный путь к файлу
		 * @return string|bool Возвращает содержимое файла или false в случае ошибки
		 */
		public static function getContent ($filename)
		{
			$filename = (string) $filename;

			if (self::exists($filename))
			{
				return file_get_contents($filename);
			}
		}


		/**
		 * Получает содержимое файла через CURL.
		 *
		 * @param string $sourceFileName URL или путь к файлу
		 * @return string|bool Возвращает содержимое файла или false в случае ошибки
		 */
		public static function curlGetContent ($sourceFileName)
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $sourceFileName);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$st = curl_exec($ch);
			curl_close($ch);

			return ($st);
		}


		/**
		 * Записывает информацию в файл.
		 *
		 * @param string $filename Полный путь к файлу
		 * @param string $content Содержимое для записи
		 * @param bool $create_file Создавать файл, если он не существует (по умолчанию true)
		 * @param bool $append Добавлять содержимое в конец файла (по умолчанию false)
		 * @param int $chmod Права доступа к файлу (по умолчанию 0666)
		 * @return bool Возвращает true при успешной записи, иначе false
		 */
		public static function setContent ($filename, $content, $create_file = true, $append = false, $chmod = 0666)
		{
			$filename	 = (string) $filename;
			$content	 = (string) $content;
			$create_file = (bool) $create_file;
			$append		 = (bool) $append;

			if ( ! $create_file && File::exists($filename))
				throw new \RuntimeException(vsprintf("%s(): The file '{$filename}' doesn't exist", array(__METHOD__)));

			Dir::create(dirname($filename));

			$handler = ($append)
				? @fopen($filename, 'a')
				: @fopen($filename, 'w');

			if ($handler === false)
				throw new \RuntimeException(vsprintf("%s(): The file '{$filename}' could not be created. Check if PHP has enough permissions.", array(__METHOD__)));

			$level = error_reporting();

			error_reporting(0);

			$write = fwrite($handler, $content);

			if($write === false)
				throw new \RuntimeException(vsprintf("%s(): The file '{$filename}' could not be created. Check if PHP has enough permissions.", array(__METHOD__)));

			fclose($handler);

			chmod($filename, $chmod);

			error_reporting($level);

			return true;
		}


		/**
		 * Получает дату последнего изменения файла.
		 *
		 * @param string $filename Полный путь к файлу
		 * @return int|bool Возвращает timestamp последнего изменения или false в случае ошибки
		 */
		public static function lastChange ($filename)
		{
			$filename = (string) $filename;

			if (self::exists($filename))
			{
				return filemtime($filename);
			}

			return false;
		}


		/**
		 * Получает дату последнего доступа к файлу.
		 *
		 * @param string $filename Полный путь к файлу
		 * @return int|bool Возвращает timestamp последнего доступа или false в случае ошибки
		 */
		public static function lastAccess ($filename)
		{
			$filename = (string) $filename;

			if (self::exists($filename))
			{
				return fileatime($filename);
			}

			return false;
		}


		/**
		 * Получает MIME-тип файла.
		 *
		 * @param string $file Полный путь к файлу
		 * @param bool $guess Использовать угадывание по расширению (по умолчанию true)
		 * @return string|bool Возвращает MIME-тип файла или false в случае ошибки
		 */
		public static function mime ($file, $guess = true)
		{
			$file  = (string) $file;
			$guess = (bool) $guess;

			if (function_exists('finfo_open'))
			{
				$info = finfo_open(FILEINFO_MIME_TYPE);

				$mime = finfo_file($info, $file);

				finfo_close($info);

				return $mime;

			}
			else
			{
				if ($guess === true)
				{
					$mime_types = self::$mime_types;

					$extension = pathinfo($file, PATHINFO_EXTENSION);

					return isset($mime_types[$extension])
						? $mime_types[$extension]
						: false;
				}
				else
					{
						return false;
					}
			}
		}


		/**
		 * Скачивает файл.
		 *
		 * @param string $file Полный путь к файлу
		 * @param string|null $content_type MIME-тип файла (необязательно)
		 * @param string|null $filename Имя файла для скачивания (необязательно)
		 * @param int $kbps Скорость загрузки в килобайтах в секунду (по умолчанию 0 - максимальная скорость)
		 * @return void
		 */
		public static function download ($file, $content_type = null, $filename = null, $kbps = 0)
		{
			$file		  = (string) $file;
			$content_type = ($content_type === null) ? null : (string) $content_type;
			$filename	  = ($filename === null) ? null : (string) $filename;
			$kbps		  = (int) $kbps;

			if (file_exists($file) === false || is_readable($file) === false)
			{
				throw new \RuntimeException(vsprintf("%s(): Failed to open stream.", array(__METHOD__)));
			}

			while (ob_get_level() > 0)
				ob_end_clean();

			if ($content_type === null)
				$content_type = self::mime($file);

			if ($filename === null)
				$filename = basename($file);

			header('Content-type: ' . $content_type);
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Length: ' . filesize($file));

			@set_time_limit(0);

			if ($kbps === 0)
			{
				readfile($file);
			}
			else
			{
				$handle = fopen($file, 'r');

				while ( ! feof($handle) && !connection_aborted())
				{
					$s = microtime(true);

					echo fread($handle, round($kbps * 1024));

					if (($wait = 1e6 - (microtime(true) - $s)) > 0)
						usleep($wait);
				}

				fclose($handle);
			}

			exit();
		}


		/**
		 * Выводит содержимое файла в браузер.
		 *
		 * @param string $file Полный путь к файлу
		 * @param string|null $content_type MIME-тип файла (необязательно)
		 * @param string|null $filename Имя файла для отображения (необязательно)
		 * @return void
		 */
		public static function display ($file, $content_type = null, $filename = null)
		{
			$file = (string) $file;

			$content_type = ($content_type === null)
				? null
				: (string) $content_type;

			$filename = ($filename === null)
				? null
				: (string) $filename;

			if (file_exists($file) === false || is_readable($file) === false)
				throw new \RuntimeException(vsprintf("%s(): Failed to open stream.", array(__METHOD__)));

			while (ob_get_level() > 0)
				ob_end_clean();

			if ($content_type === null)
				$content_type = self::mime($file);

			if ($filename === null)
				$filename = basename($file);

			header('Content-type: ' . $content_type);
			header('Content-Disposition: inline; filename="' . $filename . '"');
			header('Content-Length: ' . filesize($file));

			readfile($file);

			exit();
		}


		/**
		 * Проверяет, доступен ли файл для записи.
		 *
		 * @param string $file Полный путь к файлу
		 * @return bool Возвращает true, если файл доступен для записи, иначе false
		 */
		public static function writable ($file)
		{
			$file = (string) $file;

			if ( ! file_exists($file)) {
				throw new \RuntimeException(vsprintf("%s(): The file '{$file}' doesn't exist", [__METHOD__]));
			}

			$perms = fileperms($file);

			if (is_writable($file) || ($perms & 0x0080) || ($perms & 0x0010) || ($perms & 0x0002)) {
				return true;
			}
		}


		/**
		 * Сохраняет файл по URL в указанное место.
		 *
		 * @param string $url URL файла для скачивания
		 * @param string $saveto Имя файла для сохранения
		 * @param string $dir Директория для сохранения файла
		 * @return void
		 */
		public static function grab ($url, $saveto, $dir)
		{
			// Если папки не существует, создаем ее
			if( ! is_dir($dir)) {
				mkdir($dir, 0766, true);
			}

			// Если такой файл уже есть, пропускаем
			if (file_exists($dir . $saveto))
				// Если файл не пустой
			{
				if (filesize($dir . $saveto)) {
					return;
				}

				unlink($dir . $saveto);
			}

			$ch = curl_init ($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			$raw = curl_exec($ch);
			curl_close ($ch);

			$fp = fopen($dir . $saveto, 'x');

			fwrite($fp, $raw);
			fclose($fp);
		}


		/**
		 * Возвращает имя файла без расширения.
		 *
		 * @param string $filename Имя файла с расширением
		 * @return string Возвращает имя файла без расширения
		 */
		public static function stripExt ($filename)
		{
			if (strpos($filename, ".") === false)
				return ucwords($filename);
			else
				return substr(ucwords($filename), 0, strrpos($filename, "."));
		}


		/**
		 * Меняет кодировку файла.
		 *
		 * @param string $path Полный путь к файлу
		 * @param string $to Целевая кодировка ('utf' или 'cp1251')
		 * @return void
		 */
		public static function fileEncoding ($path, $to = 'utf')
		{
			$f = file_get_contents($path);

			$f = mb_convert_encoding($f, $to == 'utf'
				? 'UTF-8'
				: 'CP1251',
				$to == 'utf'
					? 'CP1251'
					: 'UTF-8'
			);

			file_put_contents($path, $f);
		}


		/**
		 * Атомарно записывает содержимое в файл.
		 *
		 * Пишет во временный файл в той же директории и делает rename() — в пределах
		 * одной ФС это атомарная операция, поэтому читатель всегда видит либо старую,
		 * либо новую версию файла целиком (без полузаписанного/пустого состояния) и
		 * не нуждается в разделяемой блокировке.
		 *
		 * @param string $path    Полный путь к целевому файлу
		 * @param string $content Содержимое
		 * @param int    $mode    Права на итоговый файл (по умолчанию 0664)
		 * @return bool  true при успешной записи
		 */
		public static function putAtomic ($path, $content, $mode = 0664)
		{
			$path      = (string) $path;
			$directory = dirname($path);

			if ( ! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory))
			{
				return false;
			}

			$temporary = @tempnam($directory, '.tmp-');

			if ($temporary === false)
			{
				return false;
			}

			if (@file_put_contents($temporary, $content) === false || ! @rename($temporary, $path))
			{
				@unlink($temporary);
				return false;
			}

			@chmod($path, $mode);

			return true;
		}


		/**
		 * Исправляет путь к файлу или директории.
		 *
		 * @param string $path Путь для исправления
		 * @return string Исправленный путь
		 */
		public static function pathCorrection ($path)
		{
			$path = str_replace(['/', '\\', "\r", "\n", "\0"], [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '', '', ''], $path);

			do {
				$path = str_replace([
					DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR,
					DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR,
					DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
					//DIRECTORY_SEPARATOR . '..',
					//DIRECTORY_SEPARATOR . '.'
				], [
					DIRECTORY_SEPARATOR, //'',
					DIRECTORY_SEPARATOR, //'',
					DIRECTORY_SEPARATOR,
					//DIRECTORY_SEPARATOR,
					//DIRECTORY_SEPARATOR
				], $path, $count);
			}
			while ($count);

			return ($path == '.') ? '' : $path;
		}


		/**
		 * Обрезает корневой путь из указанного пути.
		 *
		 * @param string $path Путь для обрезки
		 * @return string Обрезанный путь
		 */
		public static function cutRootPath ($path)
		{
			$path = self::pathCorrection($path);
			$base = self::pathCorrection(BASEPATH);

			if (strpos($path, dirname($base)) === 0)
			{
				$path = substr($path, strlen($base));
			}

			return $path;
		}


		/**
		 * Обрезает корневой путь из указанной строки.
		 *
		 * @param string $string Строка для обрезки
		 * @return string Обрезанная строка
		 */
		public static function cutRootPathAll ($string)
		{
			$base = self::pathCorrection(BASEPATH);

			return str_replace($base, '', $string);
		}


		protected function __clone ()
		{
			//
		}


		/**
		 * Защищенный метод восстановления класса File.
		 * Предотвращает восстановление экземпляра класса через unserialize.
		 */
		protected function __wakeup ()
		{
			//
		}
	}
