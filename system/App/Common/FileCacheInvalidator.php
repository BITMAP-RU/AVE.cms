<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/FileCacheInvalidator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Db\Core\QueryCache;
	use App\Helpers\Dir;

	/** Centralized invalidation for the public file-cache dependency graph. */
	class FileCacheInvalidator
	{
		public static function sysblock($id, $alias = '')
		{
			self::removeDir(self::sqlPath('sysblocks/' . (int) $id));
			$alias = trim((string) $alias);
			if ($alias !== '') {
				self::removeDir(self::sqlPath('sysblocks/' . $alias));
			}

			self::forgetTags(array('sysblock', CacheKey::tag('sysblock', $id), CacheKey::tag('sysblock', $alias)));
			QueryCache::clearTags(array('sysblocks'));
			self::requestElements();
		}

		public static function rubric($id)
		{
			self::removeDir(self::sqlPath('rubrics/' . (int) $id));
			self::forgetTags(array('rubric', CacheKey::tag('rubric', $id), 'document', 'document-render', 'request', 'catalog'));
			QueryCache::clearTags(array('rubrics', 'documents', 'requests', 'catalog'));
			self::requestElements();
		}

		public static function request($id, $alias = '')
		{
			self::removeDir(self::sqlPath('requests/settings/' . (int) $id));
			$alias = trim((string) $alias);
			if ($alias !== '') {
				self::removeDir(self::sqlPath('requests/settings/' . $alias));
			}

			self::forgetTags(array('request', CacheKey::tag('request', $id), CacheKey::tag('request', $alias)));
			QueryCache::clearTags(array('requests'));
			self::requestElements();
		}

		public static function requestElements()
		{
			self::removeDir(self::sqlPath('requests/elements'));
			self::forgetTags(array('request', 'catalog'));
		}

		public static function navigation($id, $alias = '')
		{
			self::removeDir(self::sqlPath('navigations/' . (int) $id));
			$alias = trim((string) $alias);
			if ($alias !== '') {
				self::removeDir(self::sqlPath('navigations/' . $alias));
			}

			self::forgetTags(array('navigation', CacheKey::tag('navigation', $id), CacheKey::tag('navigation', $alias)));
			QueryCache::clearTags(array('navigations'));
			self::publicPresentation();
		}

		public static function document($id)
		{
			$id = (int) $id;
			$bucket = (int) floor($id / 1000);
			self::removeDir(self::sqlPath('documents/' . $bucket . '/' . $id));
			self::removeDir(self::sqlPath('requests/elements/' . $bucket . '/' . $id));
			// A cached page may contain this document through a request, catalog
			// listing or another collection. Their complete dependency graph is
			// not available when the cached page is read, so invalidate only the
			// full-page layer after any document mutation.
			self::removeDir(self::sqlPath('compile'));
			self::forgetTags(array(
				'document',
				CacheKey::tag('document', $id),
				'document-render',
				CacheKey::tag('document-render', $id),
				'request',
				'catalog',
				CacheKey::tag('catalog-product', $id),
			));
			QueryCache::clearTags(array('documents', 'requests', 'catalog'));
		}

		public static function pageTemplates()
		{
			self::removeDir(self::sqlPath('documents'));
			self::removeDir(self::sqlPath('compile'));
			self::forgetTags(array('template', 'document-render'));
			QueryCache::clearTags(array('templates', 'documents'));
		}

		/** Invalidate cached fragments and complete pages after a shared presentation changes. */
		public static function publicPresentation()
		{
			self::requestElements();
			self::pageTemplates();
		}

		protected static function sqlPath($path)
		{
			return rtrim(str_replace('\\', '/', BASEPATH), '/') . '/tmp/cache/sql/' . trim((string) $path, '/');
		}

		protected static function removeDir($path)
		{
			if (is_dir($path)) {
				Dir::delete($path);
			}
		}

		protected static function forgetTags(array $tags)
		{
			foreach (array_unique(array_filter($tags, 'strlen')) as $tag) {
				Cache::forgetTag($tag);
			}
		}
	}
