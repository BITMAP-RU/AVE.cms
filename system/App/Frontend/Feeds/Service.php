<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/Service.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Lock;
	use App\Helpers\Dir;
	use App\Helpers\File;

	class Service
	{
		public function output(array $feed, $force = false)
		{
			$path = $this->cachePath($feed['alias']);
			if (!$force && $this->isFresh($path, $feed)) {
				return (string) File::getContent($path);
			}

			$generate = function () use ($feed, $force, $path) {
				if (!$force && $this->isFresh($path, $feed)) {
					return (string) File::getContent($path);
				}

				$result = (new Generator())->generate($feed);
				if (!File::putAtomic($path, $result['xml'])) {
					throw new \RuntimeException('Не удалось записать кеш фида');
				}

				return $result['xml'];
			};
			try {
				return Lock::run('feed:' . (string) $feed['alias'], $generate);
			} catch (\RuntimeException $e) {
				return $generate();
			}
		}

		public function clear($alias)
		{
			$path = $this->cachePath($alias);
			return !is_file($path) || File::delete($path);
		}

		protected function cachePath($alias)
		{
			$dir = BASEPATH . '/tmp/cache/feeds';
			Dir::create($dir);
			return $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', (string) $alias) . '.xml';
		}

		protected function isFresh($path, array $feed)
		{
			if (!is_file($path)) {
				return false;
			}

			$changed = (int) filemtime($path);
			return $changed >= (int) $feed['updated_at']
				&& (time() - $changed) < max(0, (int) $feed['cache_ttl']);
		}
	}
