<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Common\Lifecycle;
	use DB;

	/** Read model for documents required by the public page runtime. */
	class DocumentRepository
	{
		protected static $documents = array();

		public function find($documentId)
		{
			$documentId = (int) $documentId;
			if ($documentId < 1) {
				return false;
			}

			$loading = Lifecycle::event('content.document.loading', 'document', 'loading', $documentId, array(
				'scope' => 'record',
			), null, array(), 'document_repository');
			if ($loading->cancelled()) { return $loading->result(); }
			if (array_key_exists($documentId, self::$documents)) {
				$loaded = Lifecycle::event('content.document.loaded', 'document', 'loaded', $documentId, array(
					'scope' => 'record',
				), self::$documents[$documentId], array('cache_hit' => true), 'document_repository');
				return $loaded->result();
			}

			$snapshot = (new DocumentSnapshotRepository())->find($documentId);
			$document = is_array($snapshot) && isset($snapshot['record']) && is_array($snapshot['record'])
				? (object) $snapshot['record']
				: DB::query('SELECT * FROM %b WHERE Id = %i LIMIT 1', ContentTables::table('documents'), $documentId)->getObject();
			$document = $this->normalizeBreadcrumbTitle($document);
			$loaded = Lifecycle::event('content.document.loaded', 'document', 'loaded', $documentId, array(
				'scope' => 'record',
			), $document ?: false, array('cache_hit' => false), 'document_repository');
			self::$documents[$documentId] = $loaded->result();
			return self::$documents[$documentId];
		}

		public function data($documentId, $key = '')
		{
			$document = $this->find($documentId);
			if (!$document) {
				return false;
			}

			$data = get_object_vars($document);
			$title = isset($data['document_title'])
				? htmlspecialchars_decode($data['document_title'], ENT_QUOTES)
				: '';
			$data['doc_title'] = $title;
			$data['document_title'] = $title;
			$data['feld'] = array();

			if ((string) $key !== '') {
				return array_key_exists($key, $data) ? $data[$key] : null;
			}

			return $data;
		}

		public static function reset()
		{
			self::$documents = array();
			DocumentSnapshotRepository::reset();
		}

		public function parentId($documentId)
		{
			return (int) DB::query(
				'SELECT document_parent FROM %b WHERE Id = %i LIMIT 1',
				ContentTables::table('documents'),
				(int) $documentId
			)->getValue();
		}

		public function rubricId($documentId)
		{
			return (int) DB::query(
				'SELECT rubric_id FROM %b WHERE Id = %i LIMIT 1',
				ContentTables::table('documents'),
				(int) $documentId
			)->getValue();
		}

		public function exists($documentId)
		{
			return (bool) DB::query(
				'SELECT 1 FROM %b WHERE Id = %i LIMIT 1',
				ContentTables::table('documents'),
				(int) $documentId
			)->getValue();
		}

		public function findForPage($documentId, $userGroupId)
		{
			$document = $this->findWithPageContext($documentId, $userGroupId, true);

			if (!$document) {
				return false;
			}

			if ((int) $document->rubric_tmpl_id !== 0) {
				$document->rubric_template = isset($document->template) && trim((string) $document->template) !== ''
					? $document->template
					: $document->rubric_template;
				unset($document->template);
			}

			return $document;
		}

		public function findNotFoundPage($documentId, $userGroupId)
		{
			return $this->findForPage($documentId, $userGroupId);
		}

		protected function findWithPageContext($documentId, $userGroupId, $includeAlternateTemplate)
		{
			$loading = Lifecycle::event('content.document.loading', 'document', 'loading', (int) $documentId, array(
				'scope' => 'page',
				'user_group_id' => (int) $userGroupId,
				'include_alternate_template' => (bool) $includeAlternateTemplate,
			), null, array(), 'document_repository');
			if ($loading->cancelled()) { return $loading->result(); }
			$rubricLoading = Lifecycle::event('content.rubric.loading', 'rubric', 'loading', null, array(
				'document_id' => (int) $documentId,
			), null, array(), 'document_repository');
			if ($rubricLoading->cancelled()) { return $rubricLoading->result(); }
			$documents = ContentTables::table('documents');
			$rubrics = ContentTables::table('rubrics');
			$templates = ContentTables::table('templates');
			$permissions = ContentTables::table('rubric_permissions');
			$alternateJoin = '';
			$alternateSelect = '';

			if ($includeAlternateTemplate) {
				$rubricTemplates = ContentTables::table('rubric_templates');
				$alternateSelect = ', other.template';
				$alternateJoin = "
                LEFT JOIN {$rubricTemplates} AS other
                    ON doc.rubric_id = other.rubric_id
                    AND doc.rubric_tmpl_id = other.id";
			}

			$document = DB::query("
            SELECT
                doc.*,
                prm.rubric_permission,
                rub.rubric_template,
                rub.rubric_header_template,
                rub.rubric_og_template,
                rub.rubric_footer_template,
                rub.rubric_meta_gen,
                rub.rubric_template_id,
                rub.rubric_start_code,
				rub.rubric_changed,
				rub.rubric_changed_fields,
				rub.rubric_is_technical,
				tpl.template_text
                {$alternateSelect}
            FROM {$documents} AS doc
            JOIN {$rubrics} AS rub ON rub.Id = doc.rubric_id
            JOIN {$templates} AS tpl ON tpl.Id = rub.rubric_template_id
            JOIN {$permissions} AS prm ON doc.rubric_id = prm.rubric_id
            {$alternateJoin}
            WHERE prm.user_group_id = %i
                AND doc.Id = %i
            LIMIT 1
        ", (int) $userGroupId, (int) $documentId)->getObject();

			$document = $this->normalizeBreadcrumbTitle($document);
			$rubricId = is_object($document) && isset($document->rubric_id) ? (int) $document->rubric_id : 0;
			$rubricLoaded = Lifecycle::event('content.rubric.loaded', 'rubric', 'loaded', $rubricId, array(
				'document_id' => (int) $documentId,
			), $document, array(), 'document_repository');
			$loaded = Lifecycle::event('content.document.loaded', 'document', 'loaded', (int) $documentId, array(
				'scope' => 'page',
				'user_group_id' => (int) $userGroupId,
			), $rubricLoaded->result(), array('cache_hit' => false), 'document_repository');
			return $loaded->result();
		}

		/** Keeps public reads working during the one-time schema rename. */
		protected function normalizeBreadcrumbTitle($document)
		{
			if (!is_object($document)) {
				return $document;
			}

			if (!isset($document->document_breadcrumb_title) && isset($document->document_breadcrum_title)) {
				$document->document_breadcrumb_title = $document->document_breadcrum_title;
			}

			if (!isset($document->document_breadcrum_title) && isset($document->document_breadcrumb_title)) {
				$document->document_breadcrum_title = $document->document_breadcrumb_title;
			}

			return $document;
		}
	}
