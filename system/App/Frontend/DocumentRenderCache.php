<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentRenderCache.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Documents\DocumentSnapshotStore;
	use App\Content\Documents\FieldTemplateManifest;
	use App\Common\Auth;
	use App\Helpers\File;
	use App\Helpers\Request;

	/** File cache for compiled rubric content and complete public pages. */
	class DocumentRenderCache
	{
		public function descriptor($document, $userGroupId, $rubricId)
		{
			if (!$document || empty($document->Id)) {
				return false;
			}

			$documentId = (int) $document->Id;
			$bucket = (int) floor($documentId / 1000);
			$templateId = isset($document->rubric_tmpl_id) ? (int) $document->rubric_tmpl_id : 0;
			$documentChanged = isset($document->document_changed) ? (int) $document->document_changed : 0;
			$rubricChanged = isset($document->rubric_changed) ? (int) $document->rubric_changed : 0;
			$snapshot = (new DocumentSnapshotStore())->read($documentId);
			$snapshotVersion = is_array($snapshot)
				? (isset($snapshot['rubric_schema_version']) ? (string) $snapshot['rubric_schema_version'] : '')
					. ':' . (isset($snapshot['template_set_version']) ? (string) $snapshot['template_set_version'] : '')
				: '';
			$hash = md5('private-fragments-v1:g-' . (int) $userGroupId . 'r-' . (int) $rubricId . 't-' . $templateId
				. 'd-' . $documentChanged . 'rc-' . $rubricChanged . 'f-' . FieldTemplateManifest::version() . 's-' . $snapshotVersion);
			$root = rtrim(str_replace('\\', '/', BASEPATH), '/') . '/tmp/cache/sql/';

			return array(
				'template' => $root . 'documents/' . $bucket . '/' . $documentId . '/' . $hash . '.compiled',
				'page' => $root . 'compile/' . $bucket . '/' . $documentId . '/' . $hash . '.php',
			);
		}

		public function readTemplate($document, $userGroupId, $rubricId)
		{
			if (!defined('CACHE_DOC_TPL') || !CACHE_DOC_TPL) {
				return false;
			}

			return $this->read($this->path($document, $userGroupId, $rubricId, 'template'), $document);
		}

		public function writeTemplate($document, $userGroupId, $rubricId, $content)
		{
			$path = $this->path($document, $userGroupId, $rubricId, 'template');
			if ($path === null) {
				return false;
			}

			if (is_file($path)) {
				File::delete($path);
			}

			if ((defined('DEV_MODE') && DEV_MODE) || !defined('CACHE_DOC_TPL') || !CACHE_DOC_TPL) {
				return false;
			}

			return $this->write($path, (string) $content);
		}

		public function readPage($document, $userGroupId, $rubricId, array $request, $isAjax, $page)
		{
			if (!defined('CACHE_DOC_FULL') || !CACHE_DOC_FULL || !$this->accepts($request, $isAjax, $page)) {
				return false;
			}

			$content = $this->read($this->path($document, $userGroupId, $rubricId, 'page'), $document);
			if ($content !== false) {
				PublicProfiler::record(array('COMPILE', 'GET'), true);
			}

			return $content;
		}

		public function writePage($document, $userGroupId, $rubricId, array $request, $isAjax, $page, $content)
		{
			if (!$this->accepts($request, $isAjax, $page)
				|| (defined('DEV_MODE') && DEV_MODE)
				|| !defined('CACHE_DOC_FULL') || !CACHE_DOC_FULL) {
				return false;
			}

			$path = $this->path($document, $userGroupId, $rubricId, 'page');
			if ($path === null) {
				return false;
			}

			if (is_file($path)) {
				File::delete($path);
			}

			if (!$this->write($path, (string) $content)) {
				return false;
			}

			PublicProfiler::record(array('COMPILE', 'SET'), true);
			return true;
		}

		public function accepts(array $request, $isAjax, $page)
		{
			if ($isAjax || (int) $page !== 1 || Request::method() !== 'GET' || Auth::publicCheck()) {
				return false;
			}

			foreach ($request as $key => $value) {
				$key = (string) $key;
				if (in_array($key, array('id', 'doc'), true) || $this->isTrackingParameter($key)) {
					continue;
				}

				return false;
			}

			return true;
		}

		protected function isTrackingParameter($key)
		{
			return strpos($key, 'utm_') === 0
				|| in_array($key, array('gclid', 'yclid', 'fbclid', '_openstat'), true);
		}

		protected function path($document, $userGroupId, $rubricId, $type)
		{
			$descriptor = $this->descriptor($document, $userGroupId, $rubricId);
			return $descriptor && isset($descriptor[$type]) ? $descriptor[$type] : null;
		}

		protected function read($path, $document)
		{
			if ($path === null || !is_file($path)) {
				return false;
			}

			$changed = isset($document->rubric_changed) ? (int) $document->rubric_changed : 0;
			$modified = (int) filemtime($path);
			if ($changed <= 0 || $changed > $modified) {
				File::delete($path);
				return false;
			}

			$content = File::getContent($path);
			return $content === false ? false : $content;
		}

		protected function write($path, $content)
		{
			return File::putAtomic($path, $content);
		}
	}
