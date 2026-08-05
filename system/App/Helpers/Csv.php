<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Csv.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Streaming CSV response with spreadsheet-formula protection. */
	class Csv
	{
		public static function download($filename, array $headers, callable $producer)
		{
			$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $filename));
			Request::setHeader('Content-Type: text/csv; charset=UTF-8', true);
			Request::setHeader('Content-Disposition: attachment; filename="' . $filename . '"', true);
			Request::setHeader('Cache-Control: no-store, must-revalidate', true);
			Request::setHeader('Pragma: public', true);
			$stream = fopen('php://output', 'wb');
			if ($stream === false) { throw new \RuntimeException('Не удалось открыть поток CSV'); }
			fwrite($stream, "\xEF\xBB\xBF");
			self::write($stream, $headers);
			$writer = function (array $row) use ($stream) { self::write($stream, $row); };
			$producer($writer);
			fclose($stream);
			Request::shutDown();
		}

		public static function safeValue($value)
		{
			if ($value === null) { return ''; }
			if (is_bool($value)) { return $value ? '1' : '0'; }
			$value = str_replace(array("\r\n", "\r"), "\n", (string) $value);
			$trimmed = ltrim($value);
			if ($trimmed !== '' && is_numeric($trimmed)) { return $value; }
			if ($trimmed !== '' && in_array($trimmed[0], array('=', '+', '-', '@'), true)) { return "'" . $value; }
			return $value;
		}

		protected static function write($stream, array $row)
		{
			fputcsv($stream, array_map(array(__CLASS__, 'safeValue'), array_values($row)), ';', '"');
		}
	}
