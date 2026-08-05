<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PageTemplateRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Common\Lifecycle;
	use App\Common\RuntimeDirectoryGuard;
	use App\Helpers\File;
	use DB;

	/** Loads public page templates and maintains their compatibility file cache. */
	class PageTemplateRepository
	{
		public function findText($templateId)
		{
			$templateId = (int) $templateId;
			if ($templateId <= 0) {
				return null;
			}

			$loading = Lifecycle::event('content.template.loading', 'template', 'loading', $templateId, array(), null, array(), 'page_template_repository');
			if ($loading->cancelled()) {
				return $loading->result();
			}

			$cacheFile = $this->cacheFile($templateId);
			if ($cacheFile !== null && is_file($cacheFile) && filesize($cacheFile) > 0) {
				$loaded = Lifecycle::event('content.template.loaded', 'template', 'loaded', $templateId, array(
					'cache_file' => $cacheFile,
				), (string) File::getContent($cacheFile), array('cache_hit' => true), 'page_template_repository');
				return $loaded->result();
			}

			$text = DB::query(
				'SELECT template_text FROM %b WHERE Id = %i LIMIT 1',
				ContentTables::table('templates'),
				$templateId
			)->getValue();

			if ($text === null || $text === false) {
				return null;
			}

			$text = stripslashes((string) $text);
			if ($cacheFile !== null) {
				self::prepareCacheDirectory($cacheFile);
				File::putAtomic($cacheFile, $text);
			}

			$loaded = Lifecycle::event('content.template.loaded', 'template', 'loaded', $templateId, array(
				'cache_file' => $cacheFile,
			), $text, array('cache_hit' => false), 'page_template_repository');
			return $loaded->result();
		}

		public static function cacheFilePath($templateId)
		{
			$theme = defined('DEFAULT_THEME_FOLDER') && DEFAULT_THEME_FOLDER !== ''
				? (string) DEFAULT_THEME_FOLDER
				: (defined('THEME_FOLDER') && THEME_FOLDER !== '' ? (string) THEME_FOLDER : 'public');
			return BASEPATH . '/templates/' . $theme
				. '/templates/' . (int) $templateId . '.inc';
		}

		public static function prepareCacheDirectory($cacheFile)
		{
			$directory = dirname((string) $cacheFile);
			if (!is_dir($directory)) {
				@mkdir($directory, 0775, true);
			}

			if (is_dir($directory)) {
				RuntimeDirectoryGuard::protect($directory);
			}
		}

		protected function cacheFile($templateId)
		{
			if (defined('DEV_MODE') && DEV_MODE) {
				return null;
			}

			return self::cacheFilePath($templateId);
		}
	}
