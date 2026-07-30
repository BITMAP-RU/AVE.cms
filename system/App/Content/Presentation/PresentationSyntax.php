<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationSyntax.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Twig;

	class PresentationSyntax
	{
		public static function check($source, $name = 'Шаблон')
		{
			try {
				if (!Twig::twig()) { Twig::init(); }
				Twig::twig()->createTemplate((string) $source, 'presentation-syntax-check');
				return array('ok' => true, 'message' => $name . ': синтаксис корректен');
			} catch (\Throwable $e) {
				return array('ok' => false, 'message' => $name . ': ' . $e->getMessage());
			}
		}

		public static function checkMany(array $sources)
		{
			foreach ($sources as $name => $source) {
				$result = self::check($source, (string) $name);
				if (!$result['ok']) { return $result; }
			}

			return array('ok' => true, 'message' => 'Twig-синтаксис корректен');
		}
	}
