<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/SearchText.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Text normalization used by the stored public search templates. */
	class SearchText
	{
		public static function containsRussian($text)
		{
			$text = (string) $text;
			if ($text === '') {
				return false;
			}

			return (bool) preg_match('/[А-Яа-яЁё]/u', $text);
		}

		public static function toRussian($input)
		{
			return strtr((string) $input, array(
				'a' => 'а', 'b' => 'б', 'v' => 'в', 'g' => 'г', 'd' => 'д',
				'e' => 'е', 'yo' => 'ё', 'j' => 'ї', 'z' => 'з', 'i' => 'и',
				'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о',
				'p' => 'п', 'r' => 'р', 's' => 'с', 't' => 'т', 'y' => 'у',
				'f' => 'ф', 'h' => 'х', 'c' => 'ц', 'ch' => 'ч', 'sh' => 'щ',
				'u' => 'у', 'ya' => 'я', 'ye' => 'є',
				'A' => 'А', 'B' => 'Б', 'V' => 'В', 'G' => 'Ґ', 'D' => 'Д',
				'E' => 'Е', 'Yo' => 'Ё', 'J' => 'Ї', 'Z' => 'З', 'I' => 'І',
				'K' => 'К', 'L' => 'Л', 'M' => 'М', 'N' => 'Н', 'O' => 'О',
				'P' => 'П', 'R' => 'Р', 'S' => 'С', 'T' => 'Т', 'Y' => 'Ю',
				'F' => 'Ф', 'H' => 'Х', 'C' => 'Ц', 'Ch' => 'Ч', 'Sh' => 'Щ',
				'U' => 'У', 'Ya' => 'Я', 'YE' => 'Є', "'" => 'Ь', "''" => 'Ъ',
			));
		}
	}
