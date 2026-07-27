<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/TagParser.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	use App\Common\Exceptions;
	use DB;

	if (! defined("BASEPATH"))
	{
		die ('Direct access to this location is not allowed.');
	}



	/**
	 * Класс для парсинга и обработки тегов с поддержкой пользовательских паттернов и приоритетов
	 *
	 * Поддерживаемые теги:
	 * - [tag:module:name:action] - вызов модуля
	 * - [tag:field:name] - поле документа
	 * - [tag:document:id] - информация о документе
	 */
	class TagParser
	{
		/**
		 * Массив зарегистрированных обработчиков тегов с приоритетами
		 * @var array
		 */
		private static $handlers = [];

		/**
		 * Кэш для хранения результатов парсинга
		 * @var array
		 */
		private static $cache = [];

		/**
		 * Массив доступных модулей
		 * @var array|null
		 */
		private static $modules = null;

		/**
		 * Текущий обрабатываемый документ
		 * @var object|null
		 */
		private static $currentDocument = null;

		/**
		 * Регистрация обработчика для конкретного тега с приоритетом
		 *
		 * @param string $tagName Имя тега
		 * @param callable|string $handler Функция-обработчик (может быть именем функции или анонимной функцией)
		 * @param int $priority Приоритет выполнения (чем меньше число, тем выше приоритет)
		 * @param string|null $pattern Регулярное выражение для поиска тега (если не указано - используется стандартный паттерн)
		 * @return void
		 */
		public static function registerHandler($tagName, $handler, $priority = 0, $pattern = null)
		{
			// Проверяем, что обработчик является вызываемым
			if (is_string($handler) && !function_exists($handler)) {
				throw new TagParserException('Функция %handler% для тега %tag% не существует', ['handler' => $handler, 'tag' => $tagName]);
			}

			if (!is_callable($handler)) {
				throw new TagParserException('Обработчик для тега %tag% должен быть вызываемым', ['tag' => $tagName]);
			}

			self::$handlers[$tagName] = [
				'handler' => $handler,
				'priority' => $priority,
				'pattern' => $pattern
			];
		}

		/**
		 * Парсинг текста и замена тегов
		 *
		 * @param string $text Текст для парсинга
		 * @return string Обработанный текст
		 */
		public static function parse($text)
		{
			// Сортируем обработчики по приоритету
			uasort(self::$handlers, function($a, $b) {
				return $a['priority'] <=> $b['priority'];
			});

			// Применяем все зарегистрированные обработчики в порядке приоритета
			foreach (self::$handlers as $tagName => $handlerData) {
				$handler = $handlerData['handler'];
				$pattern = $handlerData['pattern'];

				// Если паттерн не задан, используем универсальный паттерн
				if ($pattern === null) {
					$pattern = '/\[tag:' . $tagName . ':([^\]]+)\]/';
				}

				$text = preg_replace_callback($pattern, function($matches) use ($handler) {
					// Если это имя функции, вызываем её с параметрами
					if (is_string($handler)) {
						return call_user_func($handler, $matches);
					} else {
						// Если это анонимная функция или объект
						return call_user_func($handler, $matches);
					}
				}, $text);
			}

			return $text;
		}

		/**
		 * Получение списка всех зарегистрированных тегов
		 *
		 * @return array
		 */
		public static function getRegisteredTags()
		{
			return array_keys(self::$handlers);
		}

		/**
		 * Установка текущего документа для парсинга
		 *
		 * @param object $document Объект документа
		 * @return void
		 */
		public static function setCurrentDocument($document)
		{
			self::$currentDocument = $document;
		}

		/**
		 * Получение текущего документа
		 *
		 * @return object|null
		 */
		public static function getCurrentDocument()
		{
			return self::$currentDocument;
		}

		/**
		 * Очистка кэша парсера
		 *
		 * @return void
		 */
		public static function clearCache()
		{
			self::$cache = [];
		}

		/**
		 * Парсинг модульных тегов в шаблоне
		 *
		 * Паттерн: [tag:module:name:action] или [tag:module:name]
		 * Пример: [tag:module:catalog:show] - вызов модуля catalog с действием show
		 *
		 * @param string $template Шаблон для обработки
		 * @param array $installModules Список установленных модулей
		 * @return string Обработанный шаблон
		 */
		public static function parseModuleTags($template, $installModules = null): string
		{
			if (empty($template)) {
				return $template;
			}

				// Получаем список модулей из единого lifecycle-реестра, если не передан.
				if ($installModules === null) {
					if (isset(self::$cache['modules'])) {
						$installModules = self::$cache['modules'];
					} else {
						$installModules = [];
						foreach (\App\Common\ModuleManager::all() as $module) {
							if (empty($module['enabled']) || empty($module['code'])) {
								continue;
							}

							$installModules[(string) $module['code']] = isset($module['name'])
								? (string) $module['name']
								: (string) $module['code'];
						}

						self::$cache['modules'] = $installModules;
				}
			}

			// Паттерн для модульных тегов [tag:module:name:action] или [tag:module:name]
			$pattern = '/\[tag:module:([a-zA-Z0-9_\-]+?)(?::([a-zA-Z0-9_\-]+?))?\]/is';

			return preg_replace_callback($pattern, function($matches) use ($installModules) {
				$moduleName = $matches[1];
				$action = $matches[2] ?? 'index';

				// Проверяем, установлен ли модуль
				if (!isset($installModules[$moduleName])) {
					return '<!-- Модуль ' . htmlspecialchars($moduleName) . ' не установлен -->';
				}

				// Возвращаем тег для последующей обработки модулями
				return '{module_' . $moduleName . ' action="' . $action . '"}';
			}, $template);
		}

		/**
		 * Парсинг полей документа в шаблоне
		 *
		 * Паттерн: [tag:field:name]
		 * Пример: [tag:field:title] - значение поля title текущего документа
		 *
		 * @param string $content Контент для обработки
		 * @param object $document Объект документа (если не передан, используется текущий)
		 * @return string Обработанный контент
		 */
		public static function parseDocumentFields($content, $document = null): string
		{
			if (empty($content)) {
				return $content;
			}

			// Используем переданный документ или текущий
			$doc = $document ?: self::$currentDocument;

			// Если документ не найден, возвращаем контент без изменений
			if (!$doc || !is_object($doc)) {
				return $content;
			}

			// Паттерн для тегов полей [tag:field:name]
			$pattern = '/\[tag:field:([a-zA-Z0-9_\-]+?)\]/is';

			return preg_replace_callback($pattern, function($matches) use ($doc) {
				$field = $matches[1];

				// Пробуем получить значение поля из документа
				if (isset($doc->$field)) {
					return $doc->$field;
				}

				// Если поле не найдено, возвращаем пустую строку
				return '';
			}, $content);
		}

		/**
		 * Парсинг тегов информации о документе
		 *
		 * Паттерны:
		 * - [tag:document:id] - ID документа
		 * - [tag:document:title] - Заголовок
		 * - [tag:document:alias] - Алиас
		 * - [tag:document:date] - Дата публикации
		 * - [tag:document:author] - Автор
		 *
		 * @param string $content Контент для обработки
		 * @param object $document Объект документа (если не передан, используется текущий)
		 * @return string Обработанный контент
		 */
		public static function parseDocumentTags($content, $document = null): string
		{
			if (empty($content)) {
				return $content;
			}

			$doc = $document ?: self::$currentDocument;

			if (!$doc || !is_object($doc)) {
				return $content;
			}

			// Карта тегов документов
			$documentTags = [
				'id' => 'Id',
				'title' => 'document_title',
				'alias' => 'document_alias',
				'date' => 'document_time',
				'author' => 'document_author',
				'rubric' => 'rubric_id',
				'template' => 'document_tmpl_id',
				'active' => 'document_active',
				'deleted' => 'document_deleted',
			];

			foreach ($documentTags as $tag => $property) {
				$pattern = '/\[tag:document:' . $tag . '\]/is';
				$value = isset($doc->$property) ? $doc->$property : '';
				$content = preg_replace($pattern, $value, $content);
			}

			return $content;
		}
	}

	/**
	 * Функция для удобного использования парсера
	 *
	 * @param string $text Текст для обработки
	 * @return string Обработанный текст
	 */
	function parse_tags($text)
	{
		return TagParser::parse($text);
	}

	/**
	 * Функция для регистрации обработчика тега с приоритетом и пользовательским паттерном
	 *
	 * @param string $tagName Имя тега
	 * @param callable|string $handler Функция-обработчик (может быть именем функции или анонимной функцией)
	 * @param int $priority Приоритет выполнения (чем меньше число, тем выше приоритет)
	 * @param string|null $pattern Регулярное выражение для поиска тега (если не указано - используется стандартный паттерн)
	 * @return void
	 */
	function register_tag_handler($tagName, $handler, $priority = 0, $pattern = null)
	{
		TagParser::registerHandler($tagName, $handler, $priority, $pattern);
	}

	// Примеры внешних функций для использования
	function custom_field_handler($matches) {
		$field = $matches[1];
		return "[Пользовательское поле: $field]";
	}

	function uppercase_handler($matches) {
		return strtoupper($matches[1]);
	}

	function watermark_handler($matches) {
		$image = $matches[1];
		$type = $matches[2];
		$size = $matches[3];
		return (new \App\Frontend\Media\WatermarkService())->apply($image, $type, $size);
	}

	function thumbnail_handler($matches) {
		$type = $matches[1];
		$param = $matches[2];
		return \App\Frontend\Media\ThumbnailUrl::make(array('size' => $type, 'link' => $param));
	}

	/**
	 * Исключение для ошибок парсера тегов
	 */
	class TagParserException extends Exceptions
	{
		/**
		 * Конструктор для исключений парсера тегов с включенной трассировкой и логированием по умолчанию
		 *
		 * @param string|null $message Сообщение исключения, может содержать плейсхолдеры для значений
		 * @param array $values Массив значений для замены плейсхолдеров в сообщении (ключ => значение)
		 * @param int|string $code Код исключения (числовой) или пользовательская строка кода
		 * @param bool $_trace Включать ли трассировку отладки в сообщение (по умолчанию true)
		 * @param int $status Уровень статуса лога для исключения (по умолчанию 0)
		 * @param bool $log Записывать ли исключение в логи (по умолчанию true)
		 */
		public function __construct($message = null, array $values = [], $code = 0, $_trace = true, $status = 0, $log = true)
		{
			parent::__construct($message, $values, $code, $_trace, $status, $log);
		}
	}
