<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Response.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	class Response
	{
		public const HTTP_STATUSES = [
			100 => 'HTTP/1.1 100 Continue',
			101 => 'HTTP/1.1 101 Switching Protocols',
			200 => 'HTTP/1.1 200 OK',
			201 => 'HTTP/1.1 201 Created',
			202 => 'HTTP/1.1 202 Accepted',
			203 => 'HTTP/1.1 203 Non-Authoritative Information',
			204 => 'HTTP/1.1 204 No Content',
			205 => 'HTTP/1.1 205 Reset Content',
			206 => 'HTTP/1.1 206 Partial Content',
			300 => 'HTTP/1.1 300 Multiple Choices',
			301 => 'HTTP/1.1 301 Moved Permanently',
			302 => 'HTTP/1.1 302 Found',
			303 => 'HTTP/1.1 303 See Other',
			304 => 'HTTP/1.1 304 Not Modified',
			307 => 'HTTP/1.1 307 Temporary Redirect',
			400 => 'HTTP/1.1 400 Bad Request',
			401 => 'HTTP/1.1 401 Unauthorized',
			402 => 'HTTP/1.1 402 Payment Required',
			403 => 'HTTP/1.1 403 Forbidden',
			404 => 'HTTP/1.1 404 Not Found',
			405 => 'HTTP/1.1 405 Method Not Allowed',
			408 => 'HTTP/1.1 408 Request Timeout',
			409 => 'HTTP/1.1 409 Conflict',
			410 => 'HTTP/1.1 410 Gone',
			413 => 'HTTP/1.1 413 Request Entity Too Large',
			415 => 'HTTP/1.1 415 Unsupported Media Type',
			419 => 'HTTP/1.1 419 Authentication Timeout',
			422 => 'HTTP/1.1 422 Unprocessable Entity',
			429 => 'HTTP/1.1 429 Too Many Requests',
			500 => 'HTTP/1.1 500 Internal Server Error',
			501 => 'HTTP/1.1 501 Not Implemented',
			502 => 'HTTP/1.1 502 Bad Gateway',
			503 => 'HTTP/1.1 503 Service Unavailable',
			504 => 'HTTP/1.1 504 Gateway Timeout',
		];

		protected function __construct()
		{
			//
		}

		// ------------------------------------------------------------------ //
		//  Статус
		// ------------------------------------------------------------------ //

		public static function setStatus($status)
		{
			$status = (int)$status;
			$text   = isset(self::HTTP_STATUSES[$status]) ? self::HTTP_STATUSES[$status] : "HTTP/1.1 {$status}";
			Request::setHeader($text, true, $status);
		}

		// ------------------------------------------------------------------ //
		//  Отправка тела
		// ------------------------------------------------------------------ //

		/**
		 * Отправить JSON-ответ и завершить выполнение.
		 *
		 * @param mixed $data
		 * @param int   $status
		 * @param int   $flags  json_encode flags (по умолчанию кириллица без escaping)
		 */
		public static function json($data, $status = 200, $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		{
			echo self::jsonBody($data, $status, $flags);
			Request::shutDown();
		}

		/** Build a JSON response for router callbacks without terminating the request. */
		public static function jsonBody($data, $status = 200, $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $cacheControl = 'no-store')
		{
			self::setStatus($status);
			Request::setHeader('Content-Type: application/json; charset=UTF-8', true);
			if ($cacheControl !== null && $cacheControl !== '') {
				Request::setHeader('Cache-Control: ' . (string) $cacheControl, true);
			}

			return Json::encode($data, $flags);
		}

		/**
		 * Отправить успешный JSON-ответ { success: true, data: ... }
		 */
		public static function jsonSuccess($data = null, $message = '')
		{
			$body = ['success' => true];
			if ($message !== '') {
				$body['message'] = $message;
			}

			if ($data !== null) {
				$body['data'] = $data;
			}

			self::json($body, 200);
		}

		/**
		 * Отправить JSON-ответ об ошибке { success: false, error: ... }
		 */
		public static function jsonError($message, $status = 400, $data = null)
		{
			$body = ['success' => false, 'error' => $message];
			if ($data !== null) {
				$body['data'] = $data;
			}

			self::json($body, $status);
		}

		/**
		 * Отправить HTML-ответ.
		 */
		public static function html($html, $status = 200)
		{
			self::setStatus($status);
			Request::setHeader('Content-Type: text/html; charset=UTF-8', true);
			echo $html;
			Request::shutDown();
		}

		/**
		 * Отправить plain text.
		 */
		public static function text($text, $status = 200)
		{
			self::setStatus($status);
			Request::setHeader('Content-Type: text/plain; charset=UTF-8', true);
			echo $text;
			Request::shutDown();
		}

		/**
		 * Отправить XML-ответ (sitemap и т.п.).
		 */
		public static function xml($xml, $status = 200)
		{
			self::setStatus($status);
			Request::setHeader('Content-Type: application/xml; charset=UTF-8', true);
			echo $xml;
			Request::shutDown();
		}

		/**
		 * Отдать файл как скачивание.
		 *
		 * @param string      $filePath  Путь к файлу на сервере
		 * @param string|null $fileName  Имя файла для клиента (null = basename($filePath))
		 * @param string      $mimeType
		 */
		public static function download($filePath, $fileName = null, $mimeType = 'application/octet-stream')
		{
			if (!file_exists($filePath)) {
				self::notFound('File not found');
			}

			$fileName = $fileName ?? basename($filePath);
			$fileSize = filesize($filePath);

			self::setStatus(200);
			Request::setHeader('Content-Type: ' . $mimeType, true);
			Request::setHeader('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"', true);
			Request::setHeader('Content-Length: ' . $fileSize, true);
			Request::setHeader('Cache-Control: must-revalidate', true);
			Request::setHeader('Pragma: public', true);

			ob_clean();
			flush();
			readfile($filePath);
			Request::shutDown();
		}

		/**
		 * Отдать файл для просмотра в браузере (inline).
		 */
		public static function inline($filePath, $mimeType = null, $fileName = null)
		{
			if (!file_exists($filePath)) {
				self::notFound('File not found');
			}

			$fileName = $fileName ?? basename($filePath);
			$mimeType = $mimeType ?? mime_content_type($filePath) ?: 'application/octet-stream';

			self::setStatus(200);
			Request::setHeader('Content-Type: ' . $mimeType, true);
			Request::setHeader('Content-Disposition: inline; filename="' . addslashes($fileName) . '"', true);
			Request::setHeader('Content-Length: ' . filesize($filePath), true);

			ob_clean();
			flush();
			readfile($filePath);
			Request::shutDown();
		}

		// ------------------------------------------------------------------ //
		//  Шорткаты для статусов
		// ------------------------------------------------------------------ //

		/** 204 No Content */
		public static function noContent()
		{
			self::setStatus(204);
			Request::shutDown();
		}

		/** 404 Not Found */
		public static function notFound($message = 'Not Found')
		{
			self::jsonError($message, 404);
		}

		/** 403 Forbidden */
		public static function forbidden($message = 'Forbidden')
		{
			self::jsonError($message, 403);
		}

		/** 401 Unauthorized */
		public static function unauthorized($message = 'Unauthorized')
		{
			self::jsonError($message, 401);
		}

		/** 429 Too Many Requests */
		public static function tooManyRequests($message = 'Too Many Requests', $retryAfter = null)
		{
			if ($retryAfter !== null) {
				Request::setHeader('Retry-After: ' . (int)$retryAfter, true);
			}

			self::jsonError($message, 429);
		}

		/** 422 Unprocessable Entity (ошибки валидации) */
		public static function unprocessable($errors, $message = 'Validation failed')
		{
			self::json(['success' => false, 'error' => $message, 'errors' => $errors], 422);
		}

		/** 500 Internal Server Error */
		public static function serverError($message = 'Internal Server Error')
		{
			self::jsonError($message, 500);
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
