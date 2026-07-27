<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/PhpCode.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class PhpCode
	{
		public static function source($code)
		{
			$code = trim((string) $code);
			if (preg_match('/^<\?(?:php|=)?/i', $code)) {
				return $code;
			}

			return "<?php\n" . $code . "\n";
		}

		public static function lint($code)
		{
			$directory = BASEPATH . '/tmp/console';
			if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
				return array('ok' => false, 'message' => 'Не удалось создать временный файл.', 'output' => '', 'line' => 0);
			}

			$file = $directory . '/lint-' . bin2hex(random_bytes(8)) . '.php';
			if (@file_put_contents($file, self::source($code)) === false) {
				@unlink($file);
				return array('ok' => false, 'message' => 'Не удалось записать временный файл.', 'output' => '', 'line' => 0);
			}

			$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
			$output = array();
			$status = 0;
			@exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
			@unlink($file);
			$text = trim(implode("\n", $output));
			$text = str_replace($file, 'код', $text);
			$line = 0;
			if (preg_match('/ on line ([0-9]+)/i', $text, $match)) {
				$line = (int) $match[1];
			}

			return array(
				'ok' => $status === 0 && $text !== '',
				'message' => $status === 0 ? 'Синтаксис PHP без ошибок.' : 'В PHP-коде есть синтаксическая ошибка.',
				'output' => $text,
				'line' => $line,
			);
		}
	}
