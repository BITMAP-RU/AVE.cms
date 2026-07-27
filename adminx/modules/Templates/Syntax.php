<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Templates/Syntax.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Templates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class Syntax
	{
		public static function check($code)
		{
			$code = (string) $code;
			if (!self::hasPhp($code)) {
				return array('ok' => true, 'checked' => false, 'message' => 'PHP-код не найден, проверка не нужна.', 'line' => 0, 'output' => '');
			}

			$insidePhp = self::endsInsidePhp($code);
			$wrapped = 'if (false) { ?>' . $code . ($insidePhp ? ' }' : '<?php }');
			try {
				eval($wrapped);
				return array(
					'ok' => true,
					'checked' => true,
					'message' => 'Синтаксис PHP без ошибок.',
					'line' => 0,
					'output' => '',
				);
			} catch (\ParseError $e) {
				$line = max(1, (int) $e->getLine() - 1);
				$message = preg_replace('/\s+in .*$/', '', (string) $e->getMessage());
				return array(
					'ok' => false,
					'checked' => true,
					'message' => 'В PHP-коде есть синтаксическая ошибка.',
					'line' => $line,
					'output' => $message . ' (строка ' . $line . ')',
				);
			}
		}

		public static function hasPhp($code)
		{
			return preg_match('/<\\?(php|=|\\s)/i', (string) $code) === 1;
		}

		protected static function endsInsidePhp($code)
		{
			$inside = false;
			foreach (token_get_all((string) $code) as $token) {
				if (!is_array($token)) {
					continue;
				}

				if ($token[0] === T_OPEN_TAG || $token[0] === T_OPEN_TAG_WITH_ECHO) {
					$inside = true;
				} elseif ($token[0] === T_CLOSE_TAG) {
					$inside = false;
				}
			}

			return $inside;
		}
	}
