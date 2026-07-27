<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Templates/TemplateMutationService.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Templates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\FileCacheInvalidator;
	use App\Content\ContentTables;
	use App\Frontend\PageTemplateRepository;
	use App\Helpers\File;
	use DB;

	/** Domain writer for public page templates. */
	class TemplateMutationService
	{
		public function save($templateId, array $payload, $actorId)
		{
			$templateId = (int) $templateId;
			$title = trim(isset($payload['title']) ? (string) $payload['title'] : (isset($payload['template_title']) ? (string) $payload['template_title'] : ''));
			$text = isset($payload['text']) ? (string) $payload['text'] : (isset($payload['template_text']) ? (string) $payload['template_text'] : '');
			if ($title === '') {
				throw new \InvalidArgumentException('Укажите название шаблона');
			}

			$data = array(
				'template_title' => $title,
				'template_text' => addslashes($text),
			);
			if ($templateId > 0) {
				if (!$this->exists($templateId)) {
					throw new \RuntimeException('Шаблон не найден');
				}

				DB::Update($this->table(), $data, 'Id = %i', $templateId);
			} else {
				$data['template_author_id'] = (int) $actorId > 0 ? (int) $actorId : 1;
				$data['template_created'] = time();
				DB::Insert($this->table(), $data);
				$templateId = (int) DB::insertId();
			}

			if (DB::$transaction_in_progress) {
				$this->removeCache($templateId);
				FileCacheInvalidator::pageTemplates();
			} else {
				$this->writeCache($templateId, $text);
			}

			return $templateId;
		}

		public function delete($templateId)
		{
			$templateId = (int) $templateId;
			if ($templateId === 1) {
				throw new \RuntimeException('Шаблон #1 является системным и не может быть удалён');
			}

			if (!$this->exists($templateId)) {
				return false;
			}

			if ((int) DB::query(
				'SELECT COUNT(*) FROM ' . ContentTables::table('rubrics') . ' WHERE rubric_template_id = %i',
				$templateId
			)->getValue() > 0) {
				throw new \RuntimeException('Шаблон используется рубриками и не может быть удалён');
			}

			DB::Delete($this->table(), 'Id = %i', $templateId);
			$this->removeCache($templateId);
			FileCacheInvalidator::pageTemplates();
			return true;
		}

		public function exists($templateId)
		{
			return (bool) DB::query('SELECT Id FROM ' . $this->table() . ' WHERE Id = %i LIMIT 1', (int) $templateId)->getValue();
		}

		protected function table()
		{
			return ContentTables::table('templates');
		}

		protected function writeCache($templateId, $text)
		{
			$file = PageTemplateRepository::cacheFilePath((int) $templateId);
			PageTemplateRepository::prepareCacheDirectory($file);
			File::putAtomic($file, (string) $text);
			FileCacheInvalidator::pageTemplates();
		}

		protected function removeCache($templateId)
		{
			$file = PageTemplateRepository::cacheFilePath((int) $templateId);
			if (is_file($file)) {
				File::delete($file);
			}
		}
	}
