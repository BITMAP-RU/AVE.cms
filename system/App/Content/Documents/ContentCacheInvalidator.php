<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/ContentCacheInvalidator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ErrorReport;
	use App\Common\FileCacheInvalidator;

	class ContentCacheInvalidator
	{
		protected static $errors=array();

		public static function document($documentId,$rebuild=false)
		{
			$id=(int)$documentId;unset(self::$errors[$id]);FileCacheInvalidator::document($id);DocumentSnapshotRepository::reset($id);$store=new DocumentSnapshotStore();
			if($rebuild){try{return (new DocumentSnapshotBuilder($store))->build($id);}catch(\Throwable $e){self::$errors[$id]=ErrorReport::publicMessage('JSON-снимок документа не обновлён',$e,'DOCSNAP',array('document_id'=>$id));$store->delete($id);return null;}}
			$store->delete($id);return null;
		}

		public static function consumeError($documentId)
		{
			$id=(int)$documentId;$error=isset(self::$errors[$id])?self::$errors[$id]:'';unset(self::$errors[$id]);return $error;
		}

		public static function rubric($rubricId)
		{
			$rubricId=(int)$rubricId;FileCacheInvalidator::rubric($rubricId);(new DocumentSnapshotStore())->deleteSchema($rubricId);RubricSchemaBuilder::reset($rubricId);DocumentSnapshotRepository::reset();
		}

		public static function allDocuments()
		{
			FileCacheInvalidator::publicPresentation();
			(new DocumentSnapshotStore())->clear();
			RubricSchemaBuilder::reset();
			DocumentSnapshotRepository::reset();
		}
	}
