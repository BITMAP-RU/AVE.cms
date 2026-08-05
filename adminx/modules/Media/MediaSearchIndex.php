<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/MediaSearchIndex.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use DB;

	class MediaSearchIndex
	{
		public static function table() { return ContentTables::table('media_search_index'); }

		public static function rebuild(array $files)
		{
			if (!DatabaseSchema::tableExists(self::table())) { return 0; }
			DB::query('DELETE FROM ' . self::table());
			$count = 0;
			foreach ($files as $file) { self::put($file); $count++; }
			return $count;
		}

		public static function put(array $file)
		{
			if (!DatabaseSchema::tableExists(self::table()) || empty($file['path'])) { return false; }
			$path = (string) $file['path'];
			DB::query(
				'INSERT INTO ' . self::table() . ' (path_hash,path,name,extension,is_image,size,modified_at) VALUES (%s,%s,%s,%s,%i,%i,%i)'
					. ' ON DUPLICATE KEY UPDATE path=VALUES(path),name=VALUES(name),extension=VALUES(extension),is_image=VALUES(is_image),size=VALUES(size),modified_at=VALUES(modified_at)',
				sha1($path), $path, isset($file['name']) ? $file['name'] : basename($path), isset($file['extension']) ? $file['extension'] : pathinfo($path, PATHINFO_EXTENSION),
				empty($file['is_image']) ? 0 : 1, isset($file['size']) ? (int) $file['size'] : 0, isset($file['modified_at']) ? (int) $file['modified_at'] : (isset($file['mtime']) ? (int) $file['mtime'] : time())
			);
			return true;
		}

		public static function remove($path)
		{
			if (!DatabaseSchema::tableExists(self::table())) { return; }
			$path = Model::normalize($path, '');
			if ($path === '') { return; }
			$children = addcslashes(rtrim($path, '/'), '%_') . '/%';
			DB::query('DELETE FROM ' . self::table() . ' WHERE path_hash=%s OR path LIKE %s', sha1($path), $children);
		}

		public static function indexPath($path)
		{
			$path = Model::normalize($path, ''); $abs = $path !== '' ? Model::abs($path) : '';
			if (is_file($abs)) { $file = Model::file($path); if ($file) { self::put($file); } return; }
			if (!is_dir($abs)) { return; }
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
			$root = Model::abs(Model::ROOT);
			foreach ($iterator as $item) {
				if (!$item->isFile() || $item->isLink()) { continue; }
				$relative = Model::ROOT . '/' . ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($root))), '/');
				$file = Model::file($relative); if ($file) { self::put($file); }
			}
		}

		public static function search($query, $limit)
		{
			if (!DatabaseSchema::tableExists(self::table())) { return array(); }
			$query = trim((string) $query); $limit = max(1, min(20, (int) $limit));
			if ($query === '') { return array(); }
			return DB::query(
				'SELECT path,name,extension,is_image,size,CASE WHEN LOWER(name)=LOWER(%s) THEN 100 WHEN LOWER(name) LIKE LOWER(%s) THEN 70 ELSE 30 END score'
					. ' FROM ' . self::table() . ' WHERE name LIKE %ss OR path LIKE %ss ORDER BY score DESC,modified_at DESC LIMIT ' . $limit,
				$query, $query . '%', $query, $query
			)->getAll() ?: array();
		}
	}
