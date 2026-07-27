<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Json.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	if (! defined("BASEPATH"))
	{
		die ('Direct access to this location is not allowed.');
	}

	use RuntimeException;




	class Json
	{

		protected function __construct()
		{
			//
		}


		/**
		 * Кодирует массив в формат JSON.
		 *
		 * Является оберткой для `json_encode` с базовой обработкой ошибок.
		 * В случае ошибки кодирования, возвращает JSON-объект с информацией об ошибке.
		 *
		 * @param array $array Ассоциативный или индексированный массив для кодирования.
		 * @return string Возвращает строку в формате JSON.
		 *
		 * @example
		 * <code>
		 * $data = ['name' => 'John', 'age' => 30];
		 * $json_string = Json::encode($data);
		 * // $json_string будет '{"name":"John","age":30}'
		 * </code>
		 */
		public static function encode ($data, $flags = 0)
		{
			$json = json_encode($data, $flags);

			if ($json === false)
			{
				$json = json_encode(array('jsonError', json_last_error_msg()), $flags);
			}

			if ($json === false)
			{
				$json = '{"jsonError": "unknown"}';
			}

			return $json;
		}


		/**
		 * Кодирует nullable payload для хранения в БД.
		 *
		 * Пустые значения сохраняются как NULL, готовая JSON-строка не кодируется повторно.
		 *
		 * @param mixed $value Значение для кодирования.
		 * @param int $flags Флаги json_encode.
		 * @return string|null JSON-строка или NULL.
		 */
		public static function payload ($value, $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		{
			if ($value === null || $value === '' || $value === array())
			{
				return null;
			}

			if (is_string($value))
			{
				return $value;
			}

			return self::encode($value, $flags);
		}


		/**
		 * Декодирует строку JSON.
		 *
		 * Является оберткой для `json_decode`. В случае ошибки парсинга выбрасывает исключение `RuntimeException`.
		 *
		 * @param string $json Строка в формате JSON.
		 * @param bool $assoc Если true, вернет ассоциативный массив; если false, вернет объект `stdClass`.
		 * @return array|object|null Массив или объект, полученный из JSON, или null в случае ошибки.
		 * @throws RuntimeException Если не удалось распарсить JSON.
		 *
		 * @example
		 * <code>
		 * $json = '{"name":"John","age":30}';
		 * $user_array = Json::decode($json); // вернет массив
		 * $user_object = Json::decode($json, false); // вернет объект
		 * </code>
		 */
		public static function decode ($json, $assoc = true)
		{
			$data = json_decode($json, $assoc, 512);

			if (JSON_ERROR_NONE !== json_last_error())
			{
				throw new RuntimeException('Unable to parse response body into JSON: ' . json_last_error());
			}

			return $data;
		}


		/**
		 * Декодирует JSON в массив без исключения для пустых/некорректных значений.
		 *
		 * @param mixed $value JSON-строка или уже готовый массив.
		 * @param array $default Значение по умолчанию.
		 * @return array
		 */
		public static function toArray ($value, array $default = array())
		{
			if (is_array($value))
			{
				return $value;
			}

			if ($value === null || $value === '')
			{
				return $default;
			}

			$data = json_decode((string) $value, true);

			if (JSON_ERROR_NONE !== json_last_error() || ! is_array($data))
			{
				return $default;
			}

			return $data;
		}


		/**
		 * Выводит массив в формате JSON с правильными HTTP-заголовками.
		 *
		 * Устанавливает HTTP-заголовки для JSON-ответа и выводит закодированную строку.
		 *
		 * @param array $array Массив для вывода.
		 * @param bool $shutdown Если true, завершает выполнение скрипта после вывода.
		 * @param bool $cyrillic Если true, заменяет \uXXXX последовательности на кириллические символы.
		 * @return void
		 */
		public static function show (array $array, $shutdown = false, $cyrillic = false)
		{
			$headers = [
				'Pragma: no-cache',
				'Cache-Control: private, no-cache',
				'Content-Disposition: inline; filename="json.json"',
				'Vary: Accept',
			];

			if (is_null(Arr::get($_SERVER, 'HTTP_ACCEPT')) || strpos(Arr::get($_SERVER, 'HTTP_ACCEPT', ''), 'application/json') !== false)
			{
				$add = ['Content-type: application/json; charset=utf-8'];
			}
			else
			{
				$add = [
					'X-Content-Type-Options: nosniff',
					'Content-type: text/plain; charset=utf-8'
				];
			}

			$headers = array_merge($headers, $add);

			Request::setHeaders($headers);

			$json = self::encode($array);

			if ($cyrillic)
			{
				$json = self::cyrillic($json);
			}

			echo $json;

			if ($shutdown)
			{
				Request::shutDown();
			}
		}


		/**
		 * Заменяет UTF-8 escape-последовательности кириллицы на символы.
		 *
		 * Преобразует `\uXXXX` последовательности в читаемые кириллические символы в строке JSON.
		 *
		 * @param string $data Строка в формате JSON.
		 * @return string JSON-строка с неэкранированной кириллицей.
		 */
		public static function cyrillic ($data)
		{
			//-- UTF8
			$_utf = [
				'\u0410', '\u0430','\u0411','\u0431','\u0412','\u0432',
				'\u0413','\u0433','\u0414','\u0434','\u0415','\u0435','\u0401','\u0451','\u0416',
				'\u0436','\u0417','\u0437','\u0418','\u0438','\u0419','\u0439','\u041a','\u043a',
				'\u041b','\u043л','\u041c','\u043м','\u041d','\u043d','\u041e','\u043о','\u041f',
				'\u043f','\u0420','\u0440','\u0421','\u0441','\u0422','\u0442','\u0423','\u0443',
				'\u0424','\u0444','\u0425','\u0445','\u0426','\u0446','\u0427','\u0447','\u0428',
				'\u0448','\u0429','\u0449','\u042a','\u044a','\u042b','\u044b','\u042c','\u044c',
				'\u042d','\u044d','\u042e','\u044e','\u042f','\u044f'
			];

			//-- Кирилица
			$_cyr = [
				'А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е',
				'Ё', 'ё', 'Ж','ж','З','з','И','и','Й','й','К','к','Л','л','М','м','Н','н','О','о',
				'П','п','Р','р','С','с','Т','т','У','у','Ф','ф','Х','х','Ц','ц','Ч','ч','Ш','ш',
				'Щ','щ','Ъ','ъ','Ы','ы','Ь','ь','Э','э','Ю','ю','Я','я'
			];

			return str_replace ($_utf, $_cyr, $data);
		}


		/**
		 * Пытается исправить некорректную JSON строку.
		 *
		 * Использует набор регулярных выражений для исправления распространенных ошибок
		 * в строках JSON (например, пропущенные кавычки). Может не работать в сложных случаях.
		 *
		 * @param string $json Потенциально некорректная JSON-строка.
		 * @return string|null Исправленная или оригинальная JSON-строка.
		 */
		public static function fix ($json)
		{
			$patterns     = [];
			/** garbage removal */
			$patterns[0]  = "/([\s:,\{}\[\]])\s*'([^:,\{}\[\]]*)'\s*([\s:,\{}\[\]])/"; //Find any character except colons, commas, curly and square brackets surrounded or not by spaces preceded and followed by spaces, colons, commas, curly or square brackets...
			$patterns[1]  = '/([^\s:,\{}\[\]]*)\{([^\s:,\{}\[\]]*)/'; //Find any left curly brackets surrounded or not by one or more of any character except spaces, colons, commas, curly and square brackets...
			$patterns[2]  =  "/([^\s:,\{}\[\]]+)}/"; //Find any right curly brackets preceded by one or more of any character except spaces, colons, commas, curly and square brackets...
			$patterns[3]  = "/(}),\s*/"; //JSON.parse() doesn't allow trailing commas
			/** reformatting */
			$patterns[4]  = '/([^\s:,\{}\[\]]+\s*)*[^\s:,\{}\[\]]+/'; //Find or not one or more of any character except spaces, colons, commas, curly and square brackets followed by one or more of any character except spaces, colons, commas, curly and square brackets...
			$patterns[5]  = '/["\']+([^"\':,\{}\[\]]*)["\']+/'; //Find one or more of quotation marks or/and apostrophes surrounding any character except colons, commas, curly and square brackets...
			$patterns[6]  = '/(")([^\s:,\{}\[\]]+)(")(\s+([^\s:,\{}\[\]]+))/'; //Find or not one or more of any character except spaces, colons, commas, curly and square brackets surrounded by quotation marks followed by one or more spaces and  one or more of any character except spaces, colons, commas, curly and square brackets...
			$patterns[7]  = "/(')([^\s:,\{}\[\]]+)(')(\s+([^\s:,\{}\[\]]+))/"; //Find or not one or more of any character except spaces, colons, commas, curly and square brackets surrounded by apostrophes followed by one or more spaces and  one or more of any character except spaces, colons, commas, curly and square brackets...
			$patterns[8]  = '/(})(")/'; //Find any right curly brackets followed by quotation marks...
			$patterns[9]  = '/,\s+(})/'; //Find any comma followed by one or more spaces and a right curly bracket...
			$patterns[10] = '/\s+/'; //Find one or more spaces...
			$patterns[11] = '/^\s+/'; //Find one or more spaces at start of string...

			$replacements     = [];
			/** garbage removal */
			$replacements[0]  = '$1 "$2" $3'; //...and put quotation marks surrounded by spaces between them;
			$replacements[1]  = '$1 { $2'; //...and put spaces between them;
			$replacements[2]  = '$1 }'; //...and put a space between them;
			$replacements[3]  = '$1'; //...so, remove trailing commas of any right curly brackets;
			/** reformatting */
			$replacements[4]  = '"$0"'; //...and put quotation marks surrounding them;
			$replacements[5]  = '"$1"'; //...and replace by single quotation marks;
			$replacements[6]  = '\\$1$2\\$3$4'; //...and add back slashes to its quotation marks;
			$replacements[7]  = '\\$1$2\\$3$4'; //...and add back slashes to its apostrophes;
			$replacements[8]  = '$1, $2'; //...and put a comma followed by a space character between them;
			$replacements[9]  = ' $1'; //...and replace by a space followed by a right curly bracket;
			$replacements[10] = ' '; //...and replace by one space;
			$replacements[11] = ''; //...and remove it.

			return preg_replace($patterns, $replacements, $json);
		}


		protected function __clone ()
		{
			//
		}


		protected function __wakeup ()
		{
			//
		}
	}
