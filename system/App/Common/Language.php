<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Language.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use App\Helpers\Arr;
	use App\Helpers\File;


	class Language
	{
		public static $langdir = '';

		protected static $word = null;

		public static $lang = '';

		protected static $sourceTranslations = array();

		protected static $sourcePhraseTranslations = array();

		protected static $sourceWordTranslations = array();

		protected static $sourceWordPatterns = array();

		protected static $sourceTranslationCache = array();


		protected function __construct()
		{
			//
		}


		public static function get ()
		{
			$arg = func_get_args();

			$key = array_shift($arg);

			//-- Если переменная передана
			if (! empty($key))
			{
				//-- Если аргументов больше 1
				if (count($arg) >= 1)
				{
					return self::$word->$key ? sprintf(self::$word->$key, ...$arg) : 'Missing translate: ' . $key;
				}

				return self::$word->$key ?? 'Missing translate: ' . $key;
			}

			return self::$word;
		}

		/**
		 * Очистить загруженный словарь перед переключением контекста интерфейса.
		 */
		public static function reset()
		{
			self::$word = null;
			self::$langdir = '';
			self::$lang = '';
			self::$sourceTranslations = array();
			self::$sourcePhraseTranslations = array();
			self::$sourceWordTranslations = array();
			self::$sourceWordPatterns = array();
			self::$sourceTranslationCache = array();
		}


		/**
		 * Зарегистрировать перевод исходных статичных фраз интерфейса.
		 *
		 * Карта строится слоем конкретного интерфейса. Ядро использует её только
		 * для служебных сообщений и не знает о расположении словарей Adminx.
		 */
		public static function setSourceTranslations(array $translations)
		{
			uksort($translations, function ($left, $right) {
				return strlen((string) $right) <=> strlen((string) $left);
			});

			self::$sourceTranslations = $translations;
			self::$sourcePhraseTranslations = array();
			self::$sourceWordTranslations = array();
			self::$sourceWordPatterns = array();
			self::$sourceTranslationCache = array();

			foreach ($translations as $source => $target) {
				$startsWithWord = preg_match('/^[\p{L}\p{N}_]/u', (string) $source);
				$endsWithWord = preg_match('/[\p{L}\p{N}_]$/u', (string) $source);
				if ($startsWithWord || $endsWithWord) {
					self::$sourceWordTranslations[$source] = array(
						'target' => $target,
						'start' => (bool) $startsWithWord,
						'end' => (bool) $endsWithWord,
					);
				} else {
					self::$sourcePhraseTranslations[$source] = $target;
				}
			}

			$words = array_keys(self::$sourceWordTranslations);
			foreach (array_chunk($words, 80) as $chunk) {
				$quoted = array_map(function ($word) {
					$config = self::$sourceWordTranslations[$word];
					return (!empty($config['start']) ? '(?<![\p{L}\p{N}_])' : '')
						. preg_quote($word, '~')
						. (!empty($config['end']) ? '(?![\p{L}\p{N}_])' : '');
				}, $chunk);
				self::$sourceWordPatterns[] = '~(?:' . implode('|', $quoted) . ')~u';
			}
		}


		public static function sourceTranslations()
		{
			return self::$sourceTranslations;
		}


		public static function translateSource($text)
		{
			$text = (string) $text;
			if ($text === '' || empty(self::$sourceTranslations) || !preg_match('/[А-Яа-яЁё]/u', $text)) {
				return $text;
			}

			$cacheKey = sha1($text);
			if (isset(self::$sourceTranslationCache[$cacheKey])) {
				return self::$sourceTranslationCache[$cacheKey];
			}

			if (!empty(self::$sourcePhraseTranslations)) {
				$text = strtr($text, self::$sourcePhraseTranslations);
			}

			foreach (self::$sourceWordPatterns as $pattern) {
				$text = preg_replace_callback($pattern, function ($match) {
					return isset(self::$sourceWordTranslations[$match[0]])
						? self::$sourceWordTranslations[$match[0]]['target']
						: $match[0];
				}, $text);
			}

			self::$sourceTranslationCache[$cacheKey] = $text;
			return $text;
		}


		/**
		 * Проверить наличие перевода без генерации строки Missing translate.
		 */
		public static function has($key)
		{
			$key = trim((string) $key);
			return $key !== '' && is_object(self::$word) && property_exists(self::$word, $key);
		}


		/**
		 * Добавить уже разобранный словарь, сохраняя ранее загруженные значения.
		 */
		public static function merge(array $phrases)
		{
			foreach ($phrases as $key => $phrase) {
				$phrases[$key] = (string) str_replace(
					array('\'', '"'),
					array('&apos;', '&quot;'),
					(string) $phrase
				);
			}

			$current = is_object(self::$word) ? Arr::toArray(self::$word) : array();
			self::$word = Arr::toObject(array_merge($phrases, $current));
		}


		public static function dir ()
		{
			$lang_array = glob("" . self::$langdir . "*.xml");

			$data = new \stdClass();

			foreach ($lang_array as $val)
			{
				$pxml = simplexml_load_string(file_get_contents($val));

				foreach ($pxml as $pkey)
				{
					$key = (string)$pkey['data'];

					$data->$key = (string)str_replace(['\'', '"'], ["&apos;", "&quot;"], $pkey);
				}
			}

			if (! empty(self::$word))
			{
				self::$word = Arr::toObject(array_merge(Arr::toArray($data), Arr::toArray(self::$word)));
			}
			else
			{
				self::$word = $data;
			}

			unset ($pxml, $data);
		}


		public static function file ($file)
		{
			$file = File::pathCorrection($file);

			$pxml = simplexml_load_string(file_get_contents($file),'SimpleXMLElement', LIBXML_NOCDATA);

			$data = new \stdClass();

			foreach ($pxml as $pkey)
			{
				$key = (string)$pkey[0]->attributes()->data;

				$value = (string)$pkey;

				$data->$key = html_entity_decode(html_entity_decode($value, ENT_QUOTES), ENT_QUOTES);
			}

			if (! empty(self::$word))
			{
				self::$word = Arr::toObject(array_merge(Arr::toArray($data), Arr::toArray(self::$word)));
			}
			else
			{
				self::$word = $data;
			}

			unset ($pxml, $data);
		}
	}
