<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/RuntimeConstantSchema.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Typed metadata and fallback values for native runtime constants. */
	class RuntimeConstantSchema
	{
		protected static $schemas = array();

		public static function all($resolveOptions = true)
		{
			$cacheKey = $resolveOptions ? 'resolved' : 'defaults';
			if (isset(self::$schemas[$cacheKey])) {
				return self::$schemas[$cacheKey];
			}

			$themes = $resolveOptions ? self::directoryOptions(BASEPATH . '/templates') : array('public');
			$defaultTheme = in_array('public', $themes, true) ? 'public' : (isset($themes[0]) ? $themes[0] : 'public');
			$codeMirrorThemes = $resolveOptions
				? self::fileOptions(AdminLocation::path('assets/vendor/codemirror/theme'), 'css')
				: array('dracula');

			self::$schemas[$cacheKey] = array(
				'PUBLIC_SITE_URL' => self::item('_CONST_URL', '', 'string', 'Канонический публичный URL сайта для проверки Host и защищённых ссылок.'),
				'REWRITE_MODE' => self::item('_CONST_URL', true, 'bool', 'Использовать человекопонятные URL.'),
				'URL_SUFF' => self::item('_CONST_URL', '', 'string', 'Суффикс публичных URL, например .html.'),
				'TRANSLIT_URL' => self::item('_CONST_URL', true, 'bool', 'Транслитерировать кириллицу в публичных URL.'),
				'DEFAULT_THEME_FOLDER' => self::item('_CONST_THEMES', $defaultTheme, 'select', 'Тема публичной части.', $themes),
				'CODEMIRROR_THEME' => self::item('_CONST_THEMES', 'dracula', 'select', 'Цветовая схема редактора кода.', $codeMirrorThemes),
				'ATTACH_DIR' => self::item('_CONST_FOLDERS', 'attachments', 'path', 'Каталог вложений внутри tmp.'),
				'UPLOAD_DIR' => self::item('_CONST_FOLDERS', 'uploads', 'path', 'Корневой каталог загружаемых файлов.'),
				'THUMBNAIL_DIR' => self::item('_CONST_THUMBS', 'th', 'path', 'Имя каталога с производными изображениями.'),
				'THUMBNAIL_SIZES' => self::item('_CONST_THUMBS', implode(',', self::thumbnailSizes()), 'tags', 'Белый список разрешённых режимов и размеров миниатюр.'),
				'JPG_QUALITY' => self::item('_CONST_THUMBS', 90, 'int', 'Качество JPEG-миниатюр от 1 до 100.'),
				'JPG_PROGRESSIVE' => self::item('_CONST_THUMBS', true, 'bool', 'Создавать progressive JPEG.'),
				'THUMBNAIL_IPTC' => self::item('_CONST_THUMBS', false, 'bool', 'Переносить IPTC в JPEG-миниатюры.'),
				'THUMBNAIL_CACHE_LIFETIME' => self::item('_CONST_THUMBS', 1209600, 'int', 'Срок браузерного кеша миниатюр в секундах.'),
				'WATERMARKS_DIR' => self::item('_CONST_WATERMARKS', 'source', 'path', 'Каталог оригиналов для водяных знаков.'),
				'WATERMARKS_FILE' => self::item('_CONST_WATERMARKS', 'watermark.png', 'path', 'Файл водяного знака.'),
				'SESSION_SAVE_HANDLER' => self::item('_CONST_SESSIONS', 'mysql', 'select', 'Хранилище сессий.', array('mysql', 'files', 'memcached')),
				'SESSION_LIFETIME' => self::item('_CONST_SESSIONS', 86400, 'int', 'Время жизни сессии в секундах.'),
				'COOKIE_LIFETIME' => self::item('_CONST_SESSIONS', 1209600, 'int', 'Время жизни cookie автоматического входа в секундах.'),
				'DEV_MODE' => self::item('_CONST_DEV', false, 'bool', 'Режим разработки без SQL-кеша.'),
				'PROFILING' => self::item('_CONST_DEV', 'off', 'select', 'Режим публичной панели отладки: off полностью выключает панель.', array('off', 'light', 'full', 'dev')),
				'SQL_PROFILING' => self::item('_CONST_DEV', true, 'bool', 'Собирать статистику SQL-запросов.'),
				'PHP_DEBUGGING' => self::item('_CONST_DEV', false, 'bool', 'Собирать ошибки PHP средствами framework.'),
				'PHP_DEBUGGING_FILE' => self::item('_CONST_DEV', false, 'bool', 'Записывать ошибки PHP в системный журнал.'),
				'SEND_SQL_ERROR' => self::item('_CONST_DEV', false, 'bool', 'Отправлять письма об ошибках SQL.'),
				'SQL_QUERY_SANITIZE' => self::item('_CONST_DEV', false, 'bool', 'Включить дополнительную проверку SQL.'),
				'MEMORY_LIMIT_PANIC' => self::item('_CONST_DEV', '-1', 'select', 'Порог аварийной очистки памяти в мегабайтах.', array('-1', '6', '12', '28', '54', '100')),
				'CACHE_DOC_TPL' => self::item('_CONST_CACHE', false, 'bool', 'Кешировать скомпилированные шаблоны документов.'),
				'CACHE_DOC_FILE' => self::item('_CONST_CACHE', true, 'bool', 'Кешировать данные документа и его полей.'),
				'CACHE_DOC_FULL' => self::item('_CONST_CACHE', false, 'bool', 'Кешировать полную публичную страницу.'),
				'CACHE_DOC_FULL_ADMIN' => self::item('_CONST_CACHE', false, 'bool', 'Разрешить полный кеш для администратора.'),
				'HTML_COMPRESSION' => self::item('_CONST_COMPRESSION', false, 'bool', 'Сжимать HTML перед отправкой.'),
				'GZIP_COMPRESSION' => self::item('_CONST_COMPRESSION', false, 'bool', 'Сжимать HTTP-ответ gzip.'),
				'OUTPUT_EXPIRE' => self::item('_CONST_COMPRESSION', false, 'bool', 'Отправлять заголовок срока кеширования страницы.'),
				'OUTPUT_EXPIRE_OFFSET' => self::item('_CONST_COMPRESSION', 0, 'int', 'Срок клиентского кеша страницы в секундах.'),
				'MEMCACHED_SERVER' => self::item('_CONST_MEMCACHED', '', 'string', 'Адрес сервера Memcached.'),
				'MEMCACHED_PORT' => self::item('_CONST_MEMCACHED', '', 'string', 'Порт сервера Memcached.'),
				'REQUEST_ETC' => self::item('_CONST_REQUEST', '...', 'string', 'Окончание обрезанного значения поля запроса.'),
				'REQUEST_BREAK_WORDS' => self::item('_CONST_REQUEST', false, 'bool', 'Разрывать слова при обрезке полей запроса.'),
				'REQUEST_STRIP_TAGS' => self::item('_CONST_REQUEST', '', 'tags', 'HTML-теги, сохраняемые при очистке полей запроса.'),
				'USE_GET_FIELDS' => self::item('_CONST_OTHER', false, 'bool', 'Проверять пустоту по исходному значению поля.'),
				'USE_STATIC_DATA' => self::item('_CONST_OTHER', true, 'bool', 'Хранить данные документов в памяти запроса.'),
				'USE_ENCODE_SERIALIZE' => self::item('_CONST_OTHER', false, 'bool', 'Кодировать сериализованные кеш-данные.'),
				'LOG_DAYS_LIMIT' => self::item('_CONST_OTHER', 282240, 'int', 'Срок хранения старых системных событий.'),
			);
			return self::$schemas[$cacheKey];
		}

		public static function defaults()
		{
			$defaults = array();
			foreach (self::all(false) as $name => $definition) {
				$defaults[$name] = $definition['default'];
			}

			return $defaults;
		}

		public static function thumbnailSizes()
		{
			return array('t128x128', 'f128x128', 'c320x320', 'f480x360', 't450x450');
		}

		protected static function item($group, $default, $type, $description, array $options = array())
		{
			return array(
				'group_code' => $group,
				'description' => $description,
				'default' => $default,
				'type' => $type,
				'options' => $options,
			);
		}

		protected static function directoryOptions($path)
		{
			$options = array();
			foreach (glob(rtrim($path, '/') . '/*', GLOB_ONLYDIR) ?: array() as $directory) {
				$options[] = basename($directory);
			}

			sort($options, SORT_NATURAL | SORT_FLAG_CASE);
			return $options;
		}

		protected static function fileOptions($path, $extension)
		{
			$options = array('default');
			foreach (glob(rtrim($path, '/') . '/*.' . $extension) ?: array() as $file) {
				$options[] = pathinfo($file, PATHINFO_FILENAME);
			}

			$options = array_values(array_unique($options));
			sort($options, SORT_NATURAL | SORT_FLAG_CASE);
			return $options;
		}
	}
