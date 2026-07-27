<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Blocks/Syntax.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Blocks;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class Syntax
	{
		public static function check($code, $eval)
		{
			if ((string) $eval !== '1') {
				return array(
					'ok' => true,
					'checked' => false,
					'message' => 'Проверка PHP не нужна: Eval отключён.',
					'line' => 0,
					'output' => '',
				);
			}

			$tmp = tempnam(sys_get_temp_dir(), 'ave-sysblock-');
			if ($tmp === false) {
				return self::unavailable('Не удалось создать временный файл для проверки.');
			}

			$file = $tmp . '.php';
			@rename($tmp, $file);
			$written = @file_put_contents($file, '?>' . (string) $code . '<?php ');
			if ($written === false) {
				@unlink($file);
				return self::unavailable('Не удалось записать временный файл для проверки.');
			}

			$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
			$cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1';
			$output = array();
			$status = 0;
			@exec($cmd, $output, $status);
			@unlink($file);

			$text = trim(implode("\n", $output));
			if ($text === '') {
				return self::unavailable('PHP CLI недоступен для проверки синтаксиса.');
			}

			$clean = str_replace($file, 'код блока', $text);
			$line = 0;
			if (preg_match('/ on line ([0-9]+)/i', $clean, $m)) {
				$line = (int) $m[1];
			}

			return array(
				'ok' => (int) $status === 0,
				'checked' => true,
				'message' => (int) $status === 0 ? 'Синтаксис PHP без ошибок.' : 'В PHP-коде есть синтаксическая ошибка.',
				'line' => $line,
				'output' => $clean,
			);
		}

		protected static function unavailable($message)
		{
			return array(
				'ok' => true,
				'checked' => false,
				'message' => $message,
				'line' => 0,
				'output' => '',
			);
		}
	}
