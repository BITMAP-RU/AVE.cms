<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Console/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Console;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;

	class Model
	{
		protected static function table()
		{
			return SystemTables::table('console_snippets');
		}

		public static function all()
		{
			$rows = DB::query(
				'SELECT id, name, author_id, created_at, updated_at FROM `' . self::table() . '` ORDER BY updated_at DESC, id DESC'
			)->getAll();
			return $rows ?: array();
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM `' . self::table() . '` WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? (array) $row : null;
		}

		public static function save($id, $name, $code, $authorId)
		{
			$now = time();
			$data = array(
				'name' => trim((string) $name),
				'code' => (string) $code,
				'author_id' => (int) $authorId,
				'updated_at' => $now,
			);
			if ((int) $id > 0) {
				DB::Update(self::table(), $data, 'id = %i', (int) $id);
				return (int) $id;
			}

			$data['created_at'] = $now;
			DB::Insert(self::table(), $data);
			return (int) DB::$insert_id;
		}

		public static function delete($id)
		{
			return DB::Delete(self::table(), 'id = %i', (int) $id);
		}
	}
