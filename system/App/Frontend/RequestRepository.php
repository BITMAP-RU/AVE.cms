<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Common\Lifecycle;
	use DB;

	/** Read access to public request/listing definitions. */
	class RequestRepository
	{
		protected static $cache = array();

		public function find($identifier)
		{
			if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
				$id = (int) $identifier;
				$cacheKey = 'id:' . $id;
				$sql = 'SELECT * FROM %b WHERE Id = %i LIMIT 1';
				$value = $id;
			} else {
				$alias = trim((string) $identifier);
				if ($alias === '' || preg_match('/^[a-z0-9_-]{1,64}$/i', $alias) !== 1) {
					return false;
				}

				$cacheKey = 'alias:' . strtolower($alias);
				$sql = 'SELECT * FROM %b WHERE request_alias = %s LIMIT 1';
				$value = $alias;
			}

			$loading = Lifecycle::event('content.query.loading', 'query', 'loading', $identifier, array(
				'cache_key' => $cacheKey,
			), null, array(), 'request_repository');
			if ($loading->cancelled()) {
				return $loading->result();
			}

			if (array_key_exists($cacheKey, self::$cache)) {
				$loaded = Lifecycle::event('content.query.loaded', 'query', 'loaded', $identifier, array(
					'cache_key' => $cacheKey,
				), self::$cache[$cacheKey], array('cache_hit' => true), 'request_repository');
				return $loaded->result();
			}

			$row = DB::query($sql, ContentTables::table('request'), $value)->getAssoc();
			$loaded = Lifecycle::event('content.query.loaded', 'query', 'loaded', $identifier, array(
				'cache_key' => $cacheKey,
			), $row ?: false, array('cache_hit' => false), 'request_repository');
			self::$cache[$cacheKey] = $loaded->result();
			return self::$cache[$cacheKey];
		}

		public static function reset()
		{
			self::$cache = array();
		}
	}
