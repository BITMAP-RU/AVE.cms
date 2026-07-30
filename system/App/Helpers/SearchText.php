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
		public static function normalize($text, $maxLength = 190)
		{
			$text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $text);
			$text = trim((string) preg_replace('/\s+/u', ' ', $text));
			$maxLength = max(1, (int) $maxLength);

			return mb_substr($text, 0, $maxLength, 'UTF-8');
		}

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

		public static function toLatin($input)
		{
			return Locales::transliterate((string) $input);
		}

		/**
		 * Исправляет текст, набранный не в той раскладке клавиатуры.
		 *
		 * Это отдельный вариант от транслитерации: «ДБ-11» превращается в
		 * «DB-11» транслитерацией, а «ВИ-11» — сменой раскладки.
		 */
		public static function switchKeyboardLayout($input)
		{
			$input = (string) $input;
			if ($input === '') {
				return '';
			}

			return strtr($input, self::containsRussian($input)
				? self::russianKeyboardMap()
				: self::englishKeyboardMap());
		}

		/** Возвращает варианты фразы без изменения её смысла. */
		public static function variants($text)
		{
			$text = self::normalize($text);
			if ($text === '') {
				return array();
			}

			$variants = array($text);
			$variants[] = self::containsRussian($text) ? self::toLatin($text) : self::toRussian($text);

			$keyboard = self::switchKeyboardLayout($text);
			$variants[] = $keyboard;
			if ($keyboard !== '') {
				$variants[] = self::containsRussian($keyboard)
					? self::toLatin($keyboard)
					: self::toRussian($keyboard);
			}

			return self::unique($variants);
		}

		/**
		 * Группы вариантов отдельных слов. Внутри группы действует OR,
		 * между группами поисковый слой может применять AND.
		 */
		public static function termGroups($text, $limit = 8)
		{
			$text = self::normalize($text);
			preg_match_all(
				'/[\p{L}\p{N}]+(?:[-_.\/][\p{L}\p{N}]+)*/u',
				$text,
				$matches
			);

			$words = array_slice(array_values(array_unique(
				isset($matches[0]) ? $matches[0] : array()
			)), 0, max(1, (int) $limit));
			$stemmer = new RussianStemmer();
			$groups = array();
			foreach ($words as $word) {
				if (mb_strlen($word, 'UTF-8') < 2) {
					continue;
				}

				$variants = self::variants($word);
				foreach ($variants as $variant) {
					if (!self::containsRussian($variant)) {
						continue;
					}

					$stem = $stemmer->stemWord($variant);
					if (mb_strlen($stem, 'UTF-8') >= 3) {
						$variants[] = $stem;
					}
				}

				$variants = array_slice(self::unique($variants), 0, 6);
				if ($variants) {
					$groups[] = $variants;
				}
			}

			return $groups;
		}

		protected static function unique(array $values)
		{
			$result = array();
			$seen = array();
			foreach ($values as $value) {
				$value = trim((string) $value);
				if ($value === '') {
					continue;
				}

				$key = mb_strtolower($value, 'UTF-8');
				if (isset($seen[$key])) {
					continue;
				}

				$seen[$key] = true;
				$result[] = $value;
			}

			return $result;
		}

		protected static function englishKeyboardMap()
		{
			static $map = null;
			if ($map !== null) {
				return $map;
			}

			$map = self::keyboardMap(
				'qwertyuiop[]asdfghjkl;\'zxcvbnm,.',
				'йцукенгшщзхъфывапролджэячсмитьбю'
			);

			return $map;
		}

		protected static function russianKeyboardMap()
		{
			static $map = null;
			if ($map === null) {
				$map = array_flip(self::englishKeyboardMap());
			}

			return $map;
		}

		protected static function keyboardMap($from, $to)
		{
			$from = preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY);
			$to = preg_split('//u', $to, -1, PREG_SPLIT_NO_EMPTY);
			$map = array_combine($from, $to);
			foreach ($from as $index => $character) {
				if (!preg_match('/\p{L}/u', $character)) {
					continue;
				}

				$map[mb_strtoupper($character, 'UTF-8')]
					= mb_strtoupper($to[$index], 'UTF-8');
			}

			return $map;
		}
	}
