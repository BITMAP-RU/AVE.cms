<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/MediaUsageIndex.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Lock;
	use App\Helpers\Dir;
	use App\Helpers\File;

	/** Compact, path-addressable companion index for the large media audit report. */
	class MediaUsageIndex
	{
		const VERSION = 1;

		public static function rebuild(array $files, $generatedAt)
		{
			return Lock::run('media-usage-index', function () use ($files, $generatedAt) {
				$root = self::root();
				$building = $root . '.building-' . substr(sha1(uniqid('', true)), 0, 10);
				self::removeTree($building);
				if (!Dir::create($building)) {
					throw new \RuntimeException('Не удалось подготовить индекс использования медиа');
				}

				$count = 0;
				foreach ($files as $file) {
					$path = isset($file['path']) ? self::normalize($file['path']) : '';
					if ($path === '' || empty($file['uses'])) { continue; }
					$hash = sha1($path);
					$dir = $building . '/' . substr($hash, 0, 2);
					if (!Dir::create($dir) || !File::putAtomic($dir . '/' . $hash . '.json', json_encode(array(
						'path' => $path,
						'use_count' => count($file['uses']),
						'uses' => array_values($file['uses']),
					), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
						self::removeTree($building);
						throw new \RuntimeException('Не удалось записать индекс использования медиа');
					}

					$count++;
				}

				File::putAtomic($building . '/meta.json', json_encode(array(
					'version' => self::VERSION,
					'generated_at' => (int) $generatedAt,
					'generated_label' => date('d.m.Y H:i:s', (int) $generatedAt),
					'used_files' => $count,
				), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

				$old = $root . '.old-' . substr(sha1(uniqid('', true)), 0, 8);
				if (is_dir($root) && !@rename($root, $old)) {
					self::removeTree($building);
					throw new \RuntimeException('Не удалось заменить индекс использования медиа');
				}

				if (!@rename($building, $root)) {
					if (is_dir($old)) { @rename($old, $root); }
					self::removeTree($building);
					throw new \RuntimeException('Не удалось активировать индекс использования медиа');
				}

				self::removeTree($old);
				return self::meta();
			});
		}

		public static function usage($path)
		{
			$path = self::normalize($path);
			$meta = self::meta();
			if ($path === '' || !$meta['available']) {
				return array('checked' => false, 'path' => $path, 'use_count' => 0, 'uses' => array(), 'report' => $meta);
			}

			$file = self::entryPath($path);
			$data = is_file($file) ? json_decode((string) File::getContent($file), true) : array();
			if (!is_array($data) || !isset($data['path']) || $data['path'] !== $path) {
				$data = array('path' => $path, 'use_count' => 0, 'uses' => array());
			}

			$data['checked'] = true;
			$data['report'] = $meta;
			return $data;
		}

		public static function usageForPath($path, $limit = 50)
		{
			$path = self::normalize($path);
			$abs = $path !== '' ? Model::abs($path) : '';
			if ($path === '' || !is_dir($abs)) { return self::usage($path); }

			$meta = self::meta();
			$result = array('checked' => $meta['available'], 'path' => $path, 'use_count' => 0, 'uses' => array(), 'report' => $meta);
			if (!$meta['available']) { return $result; }

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
			$seen = array();
			foreach ($iterator as $item) {
				if (!$item->isFile() || $item->isLink()) { continue; }
				$relative = '/uploads/' . ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen(Model::abs(Model::ROOT)))), '/');
				$usage = self::usage($relative);
				$result['use_count'] += (int) $usage['use_count'];
				foreach ($usage['uses'] as $use) {
					$key = md5(json_encode($use));
					if (isset($seen[$key])) { continue; }
					$seen[$key] = true;
					if (count($result['uses']) < max(1, (int) $limit)) { $result['uses'][] = $use; }
				}
			}

			return $result;
		}

		public static function meta()
		{
			$file = self::root() . '/meta.json';
			$data = is_file($file) ? json_decode((string) File::getContent($file), true) : array();
			$available = is_array($data) && isset($data['version']) && (int) $data['version'] === self::VERSION;
			return array(
				'available' => $available,
				'generated_at' => $available && isset($data['generated_at']) ? (int) $data['generated_at'] : 0,
				'generated_label' => $available && isset($data['generated_label']) ? (string) $data['generated_label'] : '',
				'used_files' => $available && isset($data['used_files']) ? (int) $data['used_files'] : 0,
			);
		}

		protected static function entryPath($path)
		{
			$hash = sha1($path);
			return self::root() . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
		}

		protected static function normalize($path)
		{
			$path = Model::normalize($path, '');
			return $path !== '' && Model::isAllowedPath($path) ? $path : '';
		}

		protected static function root()
		{
			return rtrim(BASEPATH, '/\\') . '/storage/reports/media-usage';
		}

		protected static function removeTree($path)
		{
			if (!is_dir($path)) { return; }
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($iterator as $item) {
				$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
			}

			@rmdir($path);
		}
	}
