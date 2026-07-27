<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Errors.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use App\Helpers\Debug;
	use App\Helpers\File;

	class Errors
	{
		/** @var bool Защита от рекурсии (ошибка внутри обработчика) */
		protected static $handling = false;

		public function __construct ()
		{
			if (PHP_DEBUGGING_FILE)
			{
				ini_set('display_errors', 'Off');
				set_error_handler([$this, 'scriptError'], E_ALL);
				register_shutdown_function([$this, 'shutDown']);
			}
		}


		/**
		 * Обработчик не-фатальных ошибок.
		 * Уважает @-подавление и error_reporting; всегда пишет в лог;
		 * «красивый» блок рендерит только в dev и не в AJAX/JSON.
		 */
		public function scriptError ($errno, $errstr, $errfile, $errline)
		{
			// Уважать @ и текущий уровень error_reporting
			if (! (error_reporting() & $errno))
			{
				return false;
			}

			$severity = self::severityName($errno);

			$this->logError($severity, $errstr, $errfile, $errline);

			// HTML-блок — только в режиме отладки и не в AJAX/JSON
			// (иначе разметка попадёт в JSON-ответ и сломает его)
			if (defined('PHP_DEBUGGING') && PHP_DEBUGGING && ! self::isAjax())
			{
				echo $this->renderBox($severity, $errno, $errstr, $errfile, $errline);
			}

			return true;
		}


		/**
		 * Обработчик фатальных ошибок при завершении скрипта.
		 */
		public function shutDown ()
		{
			if (! $error = error_get_last())
			{
				return;
			}

			$fatalTypes = [
				E_ERROR,
				E_PARSE,
				E_CORE_ERROR,
				E_CORE_WARNING,
				E_COMPILE_ERROR,
				E_COMPILE_WARNING,
				E_USER_ERROR,
				E_RECOVERABLE_ERROR,
			];

			if (! in_array($error['type'], $fatalTypes, true))
			{
				return;
			}

			$severity = self::severityName($error['type']);
			$this->logError($severity, $error['message'], $error['file'], $error['line']);

			if (PHP_SAPI === 'cli')
			{
				fwrite(STDERR, 'Fatal: ' . $error['message'] . ' (' . $error['file'] . ':' . $error['line'] . ")\n");
				return;
			}

			if (! headers_sent())
			{
				header('HTTP/1.1 500 Internal Server Error');
			}

			if (self::isAjax())
			{
				if (! headers_sent())
				{
					header('Content-Type: application/json; charset=UTF-8');
				}

				echo json_encode(['success' => false, 'error' => 'Внутренняя ошибка сервера']);
				return;
			}

			if (defined('PHP_DEBUGGING') && PHP_DEBUGGING)
			{
				echo $this->renderBox($severity, $error['type'], $error['message'], $error['file'], $error['line']);
				return;
			}

			echo '<!doctype html><meta charset="utf-8">'
				. '<div style="font:15px/1.5 sans-serif;padding:48px;text-align:center;color:#475569">'
				. 'Внутренняя ошибка. Попробуйте позже.</div>';
		}


		/**
		 * Записать ошибку в лог-файл (с защитой от рекурсии).
		 */
		protected function logError ($severity, $message, $file, $line)
		{
			if (self::$handling)
			{
				return;
			}

			self::$handling = true;

			$context = ['message' => $message, 'file' => $file, 'line' => $line];

			if (class_exists('Logger'))
			{
				try
				{
					(new \Logger())->error('PHP ' . $severity, $context);
				}
				catch (\Throwable $e)
				{
					error_log('PHP ' . $severity . ': ' . $message . ' in ' . $file . ':' . $line);
				}
			}
			else
			{
				error_log('PHP ' . $severity . ': ' . $message . ' in ' . $file . ':' . $line);
			}

			self::$handling = false;
		}


		/**
		 * AJAX/JSON-запрос? (нельзя печатать HTML в такой ответ)
		 */
		protected static function isAjax ()
		{
			if (PHP_SAPI === 'cli')
			{
				return false;
			}

			if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
			{
				return true;
			}

			return isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
		}


		/**
		 * Имя и цвет уровня ошибки.
		 */
		protected static function severityName ($errno)
		{
			$map = [
				E_ERROR             => 'Error',
				E_WARNING           => 'Warning',
				E_NOTICE            => 'Notice',
				E_CORE_ERROR        => 'Core Error',
				E_CORE_WARNING      => 'Core Warning',
				E_COMPILE_ERROR     => 'Compile Error',
				E_COMPILE_WARNING   => 'Compile Warning',
				E_USER_ERROR        => 'User Error',
				E_USER_WARNING      => 'User Warning',
				E_USER_NOTICE       => 'User Notice',
				E_STRICT            => 'Strict Standards',
				E_PARSE             => 'Parse Error',
				E_RECOVERABLE_ERROR => 'Recoverable Error',
				E_DEPRECATED        => 'Deprecated',
				E_USER_DEPRECATED   => 'User Deprecated',
			];
			return isset($map[$errno]) ? $map[$errno] : 'Error';
		}


		/**
		 * Отрисовать HTML-блок ошибки (только для dev).
		 */
		protected function renderBox ($severity, $errno, $errstr, $errfile, $errline)
		{
			$red    = ['Error', 'Core Error', 'Compile Error', 'User Error', 'Parse Error', 'Recoverable Error'];
			$blue   = ['Notice', 'User Notice'];
			$color  = in_array($severity, $red, true) ? '#f05050' : (in_array($severity, $blue, true) ? '#23b7e5' : '#fad733');

			$out  = '<div style="border: 1px solid ' . $color . '; margin: 10px; font-size: 11px; font-family: Consolas, Inconsolta, Verdana, Arial; border-radius: 5px; box-shadow: 0 2px 4px rgb(0 0 0 / 8%);">';
			$out .= '<div style="background: ' . $color . '; color: #fff; margin: 0; padding: 10px 15px; text-shadow: 0 1px 1px rgba(0, 0, 0, 0.75);">';
			$out .= '<strong>' . $severity . '</strong> Line <strong>' . $errline . '</strong>: ' . File::cutRootPath($errfile);
			$out .= '</div>';
			$out .= '<div style="background: #eaedf7; color: #242a2f; margin: 0; padding: 10px; text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);">';
			$out .= '<div style="background: #fff; color: #242a2f; margin: 0; padding: 10px;">';
			$out .= Debug::fileLines($errfile, $errline);
			$out .= '</div>';
			$out .= '<pre style="background:#fff; color: #242a2f; margin: 0; padding: 10px; border: 0; font-size: 11px; font-family: Consolas, Inconsolta, Verdana, Arial;">';
			$out .= '[' . $errno . '] ' . File::cutRootPathAll($errstr);
			$out .= '</pre>';
			$out .= '</div>';
			$out .= '</div>';

			return $out;
		}
	}
