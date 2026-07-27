<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicDocumentApi.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Native document access exposed to administrator-managed PHP. */
	class PublicDocumentApi
	{
		public static function data($documentId, $key = '')
		{
			if ((int) $documentId < 1) {
				return array();
			}

			return (new DocumentRepository())->data((int) $documentId, (string) $key);
		}

		public static function find($documentId)
		{
			return (new DocumentRepository())->find((int) $documentId);
		}

		public static function currentId()
		{
			$documentId = PublicPageContext::documentId();
			if ($documentId < 1 && isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
				$documentId = (int) $_REQUEST['id'];
			}

			return $documentId > 0 ? $documentId : 1;
		}

		public static function parentId($documentId = null)
		{
			$documentId = $documentId === null ? self::currentId() : (int) $documentId;
			return (new DocumentRepository())->parentId($documentId);
		}

		public static function paginate($content)
		{
			return (new DocumentPaginationRenderer())->render((string) $content);
		}
	}
