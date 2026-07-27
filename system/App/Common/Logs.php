<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Logs.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || exit('Direct access to this location is not allowed.');

	use App\Helpers\Arr;
	use App\Helpers\Date;
	use App\Helpers\File;

	class Logs
	{
		public const MESSAGE    = 0;
		public const SUCCESS    = 1;
		public const NOTICE     = 2;
		public const WARNING    = 3;
		public const ERROR      = 4;

		private static $_log_dir = null;

		protected static $instance = null;


		public function __construct ()
		{
			$_log_dir = BASEPATH . '/tmp/logs/';
			self::$_log_dir = File::pathCorrection($_log_dir);
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function init ()
		{
			if (! isset(self::$instance))
			{
				self::$instance = new self();
			}

			return self::$instance;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function getLogName ($date)
		{
			return self::$_log_dir . date('d_m_Y', Date::dateToTimestamp($date)) . '.log.csv';
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function write ($message, int $status, bool $notify)
		{
			//-- Хост
			$_host = Arr::get($_SERVER, 'HTTP_HOST', 'unknown');
			//-- Страница
			$_page = Arr::get($_SERVER, 'REQUEST_SCHEME', 'http') . '://' . $_host . Arr::get($_SERVER, 'REQUEST_URI');
			//-- Ip пользовотеля
			$_user_ip = Arr::get($_SERVER, 'REMOTE_ADDR', '127.0.0.1');
			//-- ID пользовотеля
			$_user_id = Session::get('user_id') || 'Anonymous';

			//-- Имя файла
			$fname = self::getLogName(date('Y-m-d'));

			//-- Удаляем старые логи
			if (defined('LOG_DAYS_LIMIT') && !is_file($fname) && is_dir(self::$_log_dir))
			{
				self::deleteOldLogs();
			}

			$_date = Date::timestampToTime(time());

			$_write = [
				$_date,
				$message,
				$status,
				$_page,
				$_user_id,
				$_user_ip
			];

			//-- Если файл есть, но запрещен доступ к редактированию - удаляем
			if (File::exists($fname) && !File::writable($fname))
			{
				@unlink($fname);
			}

			//-- Записываем в файл
			if ($f_log = @fopen($fname, 'ab'))
			{
				if (flock($f_log, LOCK_EX))
				{
					fputcsv($f_log, $_write);
					flock($f_log, LOCK_UN);
				}

				fclose($f_log);
			}

			//-- Уведомление по email
			if ($notify && defined('MAIL_EVENTS_STATUS') && $status >= MAIL_EVENTS_STATUS)
			{
				//
			}

			return self::$instance;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		protected static function deleteOldLogs ()
		{
			if ($handle = @opendir(self::$_log_dir))
			{
				while (FALSE !== ($file = @readdir($handle)))
				{
					if ($file != '.' && $file != '..' && File::ext($file) == 'csv')
					{
						$aFileName = explode('.', $file);

						if (isset($aFileName[0]))
						{
							$aFileTime = explode('_', $aFileName[0]);

							if (count($aFileTime) == 3)
							{
								$file_date = mktime(23, 59, 59, (int)$aFileTime[1], (int)$aFileTime[0], (int)$aFileTime[2]);

								if ($file_date < (time() - LOG_DAYS_LIMIT * 86400))
								{
									@unlink(self::$_log_dir . $file);
								}
							}
						}
					}
				}

				@closedir($handle);
			}

			return self::$instance;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function getStatus ($status)
		{
			switch ($status)
			{
				case '0':
					$status = 'gray';
					break;
				case '1':
					$status = 'success';
					break;
				case '2':
					$status = 'info';
					break;
				case '3':
					$status = 'warning';
					break;
				case '4':
					$status = 'danger';
					break;
			}

			return $status;
		}


		/*
		|--------------------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------------------
		|
		*/
		public static function sql ($log)
		{
			if ($log)
			{
				return $log;
			}
		}
	}
