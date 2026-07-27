<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Db/Core/QueryCache.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Db\Core;

	use App\Helpers\File;
	use App\Helpers\Dir;
	use App\Helpers\Arr;
	use App\Helpers\Json;
	use DB_Result;
	use DB;

	/**
	 * Файловый кеш SQL-запросов
	 * Поддерживает форматы php/json/serialize, тег-инвалидацию и шифрование AES-128.
	 */
	class QueryCache
	{
		/** @var int|null TTL в секундах (null или 0 = кеш выключен) */
		protected static $_ttl = null;

		/** @var string|null Идентификатор для подпапок */
		protected static $_cache_id = null;

		/** @var string Корневая директория кеша */
		protected static $_cache_dir = BASEPATH . '/tmp/cache/sql/';

		/** @var string|null Расширение файла */
		protected static $_extension = null;

		/** @var array Теги для текущего запроса */
		protected static $_tags = [];

		/** @var string Формат хранения: php | json | serialize */
		protected static $_format = 'php';

		/** @var string|null Ключ шифрования AES-128-CBC */
		protected static $_encryption_key = null;

		/** @var int Макс. возраст файла (в днях) при сборке мусора */
		protected static $_gc_max_age_days = 30;

		/** @var int GC запускается с вероятностью 1/N при каждом save() */
		const GC_PROBABILITY = 100;

		/**
		 * Общая конфигурация кеша
		 * @param array $config ['format' => 'php|json|serialize', 'key' => 'aes-secret', 'gc_days' => 30]
		 */
		public static function setConfig($config = [])
		{
			if (isset($config['format']) && in_array($config['format'], ['php', 'json', 'serialize'])) {
				self::$_format = $config['format'];
			}

			if (isset($config['key'])) {
				self::$_encryption_key = $config['key'];
			}

			if (isset($config['gc_days']) && $config['gc_days'] > 0) {
				self::$_gc_max_age_days = (int) $config['gc_days'];
			}
		}

		public static function setTtl($ttl = 0)
		{
			self::$_ttl = $ttl;
		}

		public static function getTtl()
		{
			return self::$_ttl;
		}

		public static function setCache($cache_id = '', $extension = '')
		{
			self::$_cache_id  = $cache_id;
			self::$_extension = $extension;
		}

		public static function setTags($tags = [])
		{
			self::$_tags = is_string($tags) ? [$tags] : (array) $tags;
		}

		/** Преобразует __-разделители в подпапки */
		protected static function cacheId()
		{
			if (self::$_cache_id && substr_count(self::$_cache_id, '__') > 0) {
				return str_replace('__', '/', self::$_cache_id);
			}

			return self::$_cache_id;
		}

		/**
		 * Вернуть путь к файлу кеша (не создаёт директорию — это делает save())
		 */
		protected static function getCachePath($query)
		{
			$cache_id = self::cacheId();

			$ext_map     = ['php' => '.php', 'json' => '.json', 'serialize' => '.cache'];
			$default_ext = isset($ext_map[self::$_format]) ? $ext_map[self::$_format] : '.php';
			$ext         = self::$_extension ?: $default_ext;

			$cache_file = md5($query) . $ext;

			$cache_dir = trim($cache_id) > ''
				? trim($cache_id) . '/'
				: substr($cache_file, 0, 2) . '/' . substr($cache_file, 2, 2) . '/' . substr($cache_file, 4, 2) . '/';

			return File::pathCorrection(self::$_cache_dir . $cache_dir . $cache_file);
		}

		protected static function encrypt($data)
		{
			if (!self::$_encryption_key)
				return $data;
			$iv_len    = openssl_cipher_iv_length('AES-128-CBC');
			$iv        = openssl_random_pseudo_bytes($iv_len);
			$encrypted = openssl_encrypt($data, 'AES-128-CBC', self::$_encryption_key, 0, $iv);
			return base64_encode($iv . $encrypted);
		}

		protected static function decrypt($data)
		{
			if (!self::$_encryption_key)
				return $data;
			$data      = base64_decode($data);
			$iv_len    = openssl_cipher_iv_length('AES-128-CBC');
			$iv        = substr($data, 0, $iv_len);
			$encrypted = substr($data, $iv_len);
			return openssl_decrypt($encrypted, 'AES-128-CBC', self::$_encryption_key, 0, $iv);
		}

		protected static function getTagsDir()
		{
			$dir = self::$_cache_dir . '_tags/';
			if (!Dir::exists($dir)) {
				Dir::create($dir, 0766);
			}

			return $dir;
		}

		protected static function getTagVersion($tag)
		{
			$file = self::getTagsDir() . md5($tag) . '.tag';
			return File::exists($file) ? (float) File::getContent($file) : 0.0;
		}

		public static function clearTags($tags)
		{
			if (is_string($tags))
				$tags = [$tags];
			$time = microtime(true);
			foreach ($tags as $tag) {
				File::putAtomic(self::getTagsDir() . md5($tag) . '.tag', (string) $time);
			}
		}

		/**
		 * Получить результат из кеша.
		 * Единственная проверка свежести: mtime файла vs TTL.
		 * Теги проверяются из конверта внутри файла.
		 */
		public static function get($query)
		{
			// Кешируем только SELECT
			$TTL = stripos(trim($query), 'SELECT') === 0 ? self::$_ttl : null;

			if (!$TTL || (defined('DEV_MODE') && DEV_MODE)) {
				self::resetPerQuery();
				return null;
			}

			$cache_path = self::getCachePath($query);
			$start_time = microtime(true);

			if (!File::exists($cache_path)) {
				QueryDebug::logCacheHit($query, false, microtime(true) - $start_time);
				self::resetPerQuery();
				return null;
			}

			// Быстрая проверка по mtime — избавляет от чтения файла
			if ($TTL !== -1 && (time() - filemtime($cache_path) >= $TTL)) {
				File::delete($cache_path);
				QueryDebug::logCacheHit($query, false, microtime(true) - $start_time);
				self::resetPerQuery();
				return null;
			}

			$data = self::readCacheFile($cache_path);
			if ($data === null) {
				File::delete($cache_path);
				QueryDebug::logCacheHit($query, false, microtime(true) - $start_time);
				self::resetPerQuery();
				return null;
			}

			// Извлекаем данные из конверта (если есть)
			if (is_array($data) && isset($data['__data'])) {
				// Проверка тегов
				if (!self::tagsValid($data)) {
					File::delete($cache_path);
					QueryDebug::logCacheHit($query, false, microtime(true) - $start_time);
					self::resetPerQuery();
					return null;
				}

				$result = $data['__data'];
			} else {
				// Старый формат (без конверта)
				$result = $data;
			}

			QueryDebug::logCacheHit($query, true, microtime(true) - $start_time);
			self::resetPerQuery();

			return new DB_Result($result);
		}

		protected static function readCacheFile($cache_path)
		{
			if (self::$_format === 'php' && !self::$_encryption_key) {
				try {
					return include $cache_path;
				} catch (\Throwable $e) {
					return null;
				}
			}

			$content = File::getContent($cache_path);
			if ($content === false)
				return null;

			if (self::$_encryption_key) {
				$content = self::decrypt($content);
				if ($content === false)
					return null;
			}

			if (self::$_format === 'json') {
				try {
					$decoded = Json::decode($content);
					return is_array($decoded) ? $decoded : null;
				} catch (\RuntimeException $e) {
					return null;
				}
			}

			return Arr::unserialToArray($content);
		}

		protected static function tagsValid($data)
		{
			if (!isset($data['__tags']) || !is_array($data['__tags'])) {
				// Если текущий запрос требует тегов, а конверт их не имеет — промах
				return empty(self::$_tags);
			}

			// Ни один сохранённый тег не должен быть инвалидирован
			foreach ($data['__tags'] as $tag => $saved_version) {
				if (self::getTagVersion($tag) > $saved_version) {
					return false;
				}
			}

			// Все запрошенные теги должны присутствовать в конверте
			foreach (self::$_tags as $req_tag) {
				if (!isset($data['__tags'][$req_tag])) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Сохранить результат запроса в файл кеша
		 */
		public static function save($query, $result_array)
		{
			$TTL = stripos(trim($query), 'SELECT') === 0 ? self::$_ttl : null;

			if (!$TTL || (defined('DEV_MODE') && DEV_MODE)) {
				self::resetPerQuery();
				return;
			}

			// Вероятностная сборка мусора
			if (mt_rand(1, self::GC_PROBABILITY) === 1) {
				self::cleanOldFiles();
			}

			$cache_path = self::getCachePath($query);
			$cache_dir  = dirname($cache_path);

			if (!Dir::exists($cache_dir)) {
				Dir::create($cache_dir, 0766);
			}

			// Снимаем версии тегов в момент записи
			$tags_meta = [];
			foreach (self::$_tags as $tag) {
				$tags_meta[$tag] = self::getTagVersion($tag);
			}

			$payload = [
				'__data' => $result_array,
				'__tags' => $tags_meta,
			];

			$content = self::encodePayload($payload);
			if ($content !== null) {
				File::putAtomic($cache_path, $content);
			}

			self::resetPerQuery();
		}

		protected static function encodePayload($payload)
		{
			if (self::$_format === 'php' && !self::$_encryption_key) {
				return "<?php\nreturn " . var_export($payload, true) . ";\n";
			}

			$content = self::$_format === 'json'
				? Json::encode($payload)
				: Arr::arrayToSerial($payload);

			if (self::$_encryption_key) {
				$content = self::encrypt($content);
			}

			return $content ?: null;
		}

		/** Сбросить per-query настройки после каждого запроса */
		protected static function resetPerQuery()
		{
			self::setTtl();
			self::setCache();
			self::setTags();
			DB::setTtl(0);
		}

		/**
		 * Очистить файлы кеша старше $_gc_max_age_days дней
		 */
		public static function cleanOldFiles()
		{
			self::scanAndClean(self::$_cache_dir);
		}

		protected static function scanAndClean($dir)
		{
			if (!is_dir($dir))
				return;

			$max_age = self::$_gc_max_age_days * 86400;

			foreach (scandir($dir) as $file) {
				if ($file === '.' || $file === '..')
					continue;

				$path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;

				if (is_dir($path)) {
					self::scanAndClean($path);
					@rmdir($path);
				} elseif (strpos($path, '_tags') === false && (time() - filemtime($path) > $max_age)) {
					File::delete($path);
				}
			}
		}
	}
