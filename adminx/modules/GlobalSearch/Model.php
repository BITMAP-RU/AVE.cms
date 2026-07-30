<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/GlobalSearch/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\GlobalSearch;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;
	use App\Adminx\Support\GlobalSearchRegistry;
	use App\Adminx\Support\ModuleExtensions;
	use App\Content\ContentTables;
	use App\Content\Documents\DocumentSearch;
	use DB;

	class Model
	{
		protected static $registered = false;

		public static function search($query, $limit = 30)
		{
			self::registerProviders();
			ModuleExtensions::boot();
			return GlobalSearchRegistry::search($query, $limit);
		}

		public static function documents($query, $limit = 8)
		{
			$table = ContentTables::table('documents');
			if (!self::available($table)) { return array(); }
			$criteria = DocumentSearch::criteria($query, 'd');
			$sql = 'SELECT d.Id,d.document_title,d.document_alias,d.document_status'
				. ',(' . $criteria['score'] . ') AS search_relevance'
				. ' FROM ' . $table . ' d'
				. " WHERE d.document_deleted!='1' AND " . $criteria['where']
				. ' ORDER BY search_relevance DESC,d.document_status DESC,d.document_changed DESC'
				. ' LIMIT ' . max(1, min(12, (int) $limit));
			$rows = call_user_func_array(
				array('DB', 'query'),
				array_merge(array($sql), $criteria['score_args'], $criteria['where_args'])
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$item = self::item(
					'document',
					'Документы',
					self::decode($row['document_title']),
					'#' . (int) $row['Id'] . ' · /' . (string) $row['document_alias'],
					'/documents/' . (int) $row['Id'] . '/edit',
					'ti ti-file-text'
				);
				$item['score'] = (float) $row['search_relevance'];
				$out[] = $item;
			}

			return $out;
		}

		public static function rubrics($query, $limit = 6)
		{
			$table = ContentTables::table('rubrics');
			if (!self::available($table)) { return array(); }
			$rows = DB::query(
				'SELECT Id,rubric_title,rubric_alias FROM ' . $table
					. ' WHERE rubric_title LIKE %ss OR rubric_alias LIKE %ss OR Id=%i ORDER BY rubric_title LIMIT ' . max(1, min(12, (int) $limit)),
				$query,
				$query,
				(int) $query
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::item('rubric', 'Рубрики', self::decode($row['rubric_title']), '#' . (int) $row['Id'] . ' · ' . (string) $row['rubric_alias'], '/rubrics?edit=' . (int) $row['Id'], 'ti ti-forms');
			}

			return $out;
		}

		public static function blocks($query, $limit = 6)
		{
			$table = ContentTables::table('sysblocks');
			if (!self::available($table)) { return array(); }
			$rows = DB::query(
				'SELECT id,sysblock_name,sysblock_alias FROM ' . $table
					. ' WHERE sysblock_name LIKE %ss OR sysblock_alias LIKE %ss OR id=%i ORDER BY sysblock_name LIMIT ' . max(1, min(12, (int) $limit)),
				$query,
				$query,
				(int) $query
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::item('block', 'Блоки', self::decode($row['sysblock_name']), '#' . (int) $row['id'] . ' · ' . (string) $row['sysblock_alias'], '/blocks?edit=' . (int) $row['id'], 'ti ti-blockquote');
			}

			return $out;
		}

		public static function requests($query, $limit = 6)
		{
			$table = ContentTables::table('request');
			if (!self::available($table)) { return array(); }
			$rows = DB::query(
				'SELECT Id,request_title,request_alias FROM ' . $table
					. ' WHERE request_title LIKE %ss OR request_alias LIKE %ss OR Id=%i ORDER BY request_title LIMIT ' . max(1, min(12, (int) $limit)),
				$query,
				$query,
				(int) $query
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::item('request', 'Запросы', self::decode($row['request_title']), '#' . (int) $row['Id'] . ' · ' . (string) $row['request_alias'], '/requests/' . (int) $row['Id'], 'ti ti-list-search');
			}

			return $out;
		}

		public static function navigation($query, $limit = 5)
		{
			$table = ContentTables::table('navigation_items');
			if (!self::available($table)) { return array(); }
			$rows = DB::query(
				'SELECT navigation_item_id,navigation_id,title,alias FROM ' . $table
					. ' WHERE title LIKE %ss OR alias LIKE %ss OR navigation_item_id=%i ORDER BY title LIMIT ' . max(1, min(12, (int) $limit)),
				$query,
				$query,
				(int) $query
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::item(
					'navigation',
					'Навигация',
					self::decode($row['title']),
					'#' . (int) $row['navigation_item_id'] . ' · ' . (string) $row['alias'],
					'/navigation?navigation=' . (int) $row['navigation_id'] . '&item=' . (int) $row['navigation_item_id'],
					'ti ti-menu-2'
				);
			}

			return $out;
		}

		public static function users($query, $limit = 6)
		{
			$table = SystemTables::table('users');
			if (!self::available($table)) { return array(); }
			// У таблицы пользователей смешанные коллации (email обычно ascii,
			// name/login — utf8mb4), поэтому приводим сравниваемые колонки к
			// charset соединения (utf8 = utf8mb3, см. dbchar): иначе
			// кириллический запрос роняет провайдер на «illegal mix of
			// collations». Ведущий подстановочный знак и так исключает
			// использование индекса, так что CONVERT ничего не стоит.
			$rows = DB::query(
				'SELECT id,name,login,email,is_active FROM ' . $table
					. ' WHERE CONVERT(name USING utf8) LIKE %ss'
					. ' OR CONVERT(login USING utf8) LIKE %ss'
					. ' OR CONVERT(email USING utf8) LIKE %ss OR id=%i'
					. ' ORDER BY is_active DESC,name LIMIT ' . max(1, min(12, (int) $limit)),
				$query,
				$query,
				$query,
				(int) $query
			)->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$name = trim((string) $row['name']);
				$login = trim((string) $row['login']);
				$email = trim((string) $row['email']);
				$title = $name !== '' ? $name : ($login !== '' ? '@' . $login : $email);
				$parts = array('#' . (int) $row['id']);
				if ($email !== '') { $parts[] = $email; }
				if (empty($row['is_active'])) { $parts[] = 'выключен'; }
				$out[] = self::item(
					'user',
					'Пользователи',
					self::decode($title !== '' ? $title : ('#' . (int) $row['id'])),
					implode(' · ', $parts),
					'/users/' . (int) $row['id'],
					'ti ti-user'
				);
			}

			return $out;
		}

		public static function resetRuntime()
		{
			self::$registered = false;
			GlobalSearchRegistry::resetRuntime();
		}

		protected static function registerProviders()
		{
			if (self::$registered) {
				return;
			}

			self::$registered = true;
			foreach (array(
				'documents' => array('provider' => array(self::class, 'documents'), 'permission' => 'view_documents', 'priority' => 10, 'limit' => 8),
				'rubrics' => array('provider' => array(self::class, 'rubrics'), 'permission' => 'view_rubrics', 'priority' => 20, 'limit' => 6),
				'blocks' => array('provider' => array(self::class, 'blocks'), 'permission' => 'view_blocks', 'priority' => 30, 'limit' => 6),
				'requests' => array('provider' => array(self::class, 'requests'), 'permission' => 'view_requests', 'priority' => 40, 'limit' => 6),
				'navigation' => array('provider' => array(self::class, 'navigation'), 'permission' => 'view_navigation', 'priority' => 50, 'limit' => 5),
				'users' => array('provider' => array(self::class, 'users'), 'permission' => 'view_users', 'priority' => 60, 'limit' => 6),
			) as $code => $definition) {
				GlobalSearchRegistry::register('core.' . $code, $definition);
			}
		}

		protected static function item($type, $group, $title, $subtitle, $url, $icon)
		{
			return array(
				'type' => (string) $type,
				'group' => (string) $group,
				'title' => (string) $title,
				'subtitle' => (string) $subtitle,
				'url' => (string) $url,
				'icon' => (string) $icon,
			);
		}

		protected static function available($table)
		{
			try { return DatabaseSchema::tableExists($table); }
			catch (\Throwable $e) { return false; }
		}

		protected static function decode($value)
		{
			return html_entity_decode(stripcslashes((string) $value), ENT_QUOTES, 'UTF-8');
		}
	}
