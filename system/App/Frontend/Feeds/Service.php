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
			$snapshot = function () use ($feed, $force, $path) {
				return $this->synchronized(function () use ($feed, $force, $path) {
					return array(
						'xml' => !$force && $this->isFresh($path, $feed) ? File::getContent($path) : null,
						'revision' => $this->revision($path),
					);
				});
			};
			$cached = $snapshot();
			if (is_string($cached['xml'])) { return $cached['xml']; }

			return Lock::run('feed:' . (string) $feed['alias'], function () use ($feed, $path, $snapshot) {
				$cached = $snapshot();
				if (is_string($cached['xml'])) { return $cached['xml']; }
				$result = $this->generate($feed);
				$this->synchronized(function () use ($path, $cached, $result) {
					// An invalidated in-flight response must never repopulate the cache.
					if ($this->revision($path) !== $cached['revision']) { return; }
					if (!File::putAtomic($path, $result['xml'])) {
						throw new \RuntimeException('Не удалось записать кеш фида');
					}
				});

				return $result['xml'];
			}, 5.0, true);
		}

		protected function generate(array $feed)
		{
			return (new Generator())->generate($feed);
		}

		protected function synchronized(callable $callback)
		{
			return Lock::run('feeds:publication:' . hash('sha256', $this->cacheDirectory()), $callback, 5.0, true);
		}

		protected function revision($path)
		{
			$revision = array();
			foreach (array($this->cacheDirectory() . '/.version', $path . '.version') as $file) {
				clearstatcache(true, $file);
				$value = is_file($file) ? File::getContent($file) : '';
				if ($value === false) {
					throw new \RuntimeException('Unable to read feed cache revision');
				}

				$revision[] = $value;
			}

			return $revision;
		}

		protected function invalidate($file)
		{
			if (!File::putAtomic($file, bin2hex(random_bytes(16)))) {
				throw new \RuntimeException('Unable to invalidate feed cache revision');
			}
		}

		public function clear($alias)
		{
			$path = $this->cachePath($alias);
			return $this->synchronized(function () use ($path) {
				$this->invalidate($path . '.version');
				clearstatcache(true, $path);
				return !is_file($path) || File::delete($path);
			});
		}

		public function clearAll()
		{
			return $this->synchronized(function () {
				Dir::create($this->cacheDirectory());
				$this->invalidate($this->cacheDirectory() . '/.version');
				$cleared = 0;
				foreach (glob($this->cacheDirectory() . '/*.xml') ?: array() as $path) {
					clearstatcache(true, $path);
					if (!is_file($path)) { continue; }
					if (!File::delete($path)) {
						throw new \RuntimeException('Unable to delete feed cache');
					}

					$cleared++;
				}

				return $cleared;
			});
		}

		protected function cachePath($alias)
		{
			$dir = $this->cacheDirectory();
			Dir::create($dir);
			return $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', (string) $alias) . '.xml';
		}

		protected function cacheDirectory()
		{
			return BASEPATH . '/tmp/cache/feeds';
		}

		protected function isFresh($path, array $feed)
		{
			clearstatcache(true, $path);
			if (!is_file($path)) {
				return false;
			}

			$changed = (int) filemtime($path);
			return $changed >= (int) $feed['updated_at']
				&& (time() - $changed) < max(0, (int) $feed['cache_ttl']);
		}
	}
