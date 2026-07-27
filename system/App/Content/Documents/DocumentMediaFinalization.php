<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentMediaFinalization.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Frontend\Media\ThumbnailStorage;
	use App\Helpers\Dir;

	/** Reversible filesystem part of one document media finalization. */
	class DocumentMediaFinalization
	{
		protected $token;
		protected $moves = array();
		protected $completed = false;

		public function __construct($token)
		{
			$this->token = (string) $token;
		}

		public function move($sourceUrl, $targetDir)
		{
			$sourceUrl = self::urlPath($sourceUrl);
			$source = BASEPATH . $sourceUrl;
			if (!is_file($source)) {
				throw new \RuntimeException('Загруженный файл не найден: ' . basename($sourceUrl));
			}

			$targetDir = rtrim((string) $targetDir, '/');
			$absoluteTargetDir = BASEPATH . $targetDir;
			if (!Dir::create($absoluteTargetDir)) {
				throw new \RuntimeException('Не удалось создать папку медиа документа');
			}

			$name = self::targetName($source, $absoluteTargetDir, basename($sourceUrl));
			$targetUrl = $targetDir . '/' . $name;
			$target = BASEPATH . $targetUrl;
			ThumbnailStorage::removeForSource($source);

			if (!self::moveFile($source, $target)) {
				throw new \RuntimeException('Не удалось переместить медиа документа: ' . basename($sourceUrl));
			}

			$this->moves[] = array($source, $target);

			try {
				$this->moveWebpCompanion($sourceUrl, $targetUrl);
			} catch (\Throwable $e) {
				$this->rollback();
				throw $e;
			}

			return $targetUrl;
		}

		public function commit()
		{
			if ($this->completed) {
				return;
			}

			$this->completed = true;
			DocumentMediaDraft::finish($this->token);
		}

		public function rollback()
		{
			if ($this->completed) {
				return;
			}

			for ($i = count($this->moves) - 1; $i >= 0; $i--) {
				$source = $this->moves[$i][0];
				$target = $this->moves[$i][1];
				if (!is_file($target)) {
					continue;
				}

				Dir::create(dirname($source));
				self::moveFile($target, $source);
			}

			$this->moves = array();
		}

		protected function moveWebpCompanion($sourceUrl, $targetUrl)
		{
			$extension = strtolower(pathinfo($sourceUrl, PATHINFO_EXTENSION));
			if (!in_array($extension, array('jpg', 'jpeg', 'png'), true)) {
				return;
			}

			$sourceWebp = BASEPATH . dirname($sourceUrl) . '/' . pathinfo($sourceUrl, PATHINFO_FILENAME) . '.webp';
			if (!is_file($sourceWebp)) {
				return;
			}

			$targetWebp = BASEPATH . dirname($targetUrl) . '/' . pathinfo($targetUrl, PATHINFO_FILENAME) . '.webp';
			if (is_file($targetWebp)) {
				throw new \RuntimeException('WebP-копия с таким именем уже существует');
			}

			ThumbnailStorage::removeForSource($sourceWebp);
			if (!self::moveFile($sourceWebp, $targetWebp)) {
				throw new \RuntimeException('Не удалось переместить WebP-копию файла');
			}

			$this->moves[] = array($sourceWebp, $targetWebp);
		}

		protected static function targetName($source, $targetDir, $name)
		{
			$name = basename((string) $name);
			if ($name === '' || $name === '.' || $name === '..') {
				$name = 'file';
			}

			$target = $targetDir . '/' . $name;
			$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			$webpTarget = $targetDir . '/' . pathinfo($name, PATHINFO_FILENAME) . '.webp';
			if (!file_exists($target) && (!in_array($extension, array('jpg', 'jpeg', 'png'), true) || !file_exists($webpTarget))) {
				return $name;
			}

			$info = pathinfo($name);
			$base = isset($info['filename']) && $info['filename'] !== '' ? $info['filename'] : 'file';
			$suffix = '-' . substr(sha1($source), 0, 8);
			$ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
			$candidate = $base . $suffix . $ext;
			$counter = 2;
			while (file_exists($targetDir . '/' . $candidate)
				|| (in_array($extension, array('jpg', 'jpeg', 'png'), true)
					&& file_exists($targetDir . '/' . pathinfo($candidate, PATHINFO_FILENAME) . '.webp'))) {
				$candidate = $base . $suffix . '-' . $counter . $ext;
				$counter++;
			}

			return $candidate;
		}

		protected static function moveFile($source, $target)
		{
			if (@rename($source, $target)) {
				return true;
			}

			if (!@copy($source, $target)) {
				return false;
			}

			if (!@unlink($source)) {
				@unlink($target);
				return false;
			}

			return true;
		}

		protected static function urlPath($path)
		{
			$parsed = parse_url(html_entity_decode(trim((string) $path), ENT_QUOTES, 'UTF-8'), PHP_URL_PATH);
			$path = is_string($parsed) ? rawurldecode($parsed) : (string) $path;
			return '/' . ltrim(preg_replace('#/+#', '/', str_replace('\\', '/', $path)), '/');
		}
	}
