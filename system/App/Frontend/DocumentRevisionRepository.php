<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentRevisionRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Documents\DocumentRevisionPayload;
	use DB;

	/** Read access to historical document field values used by public previews. */
	class DocumentRevisionRepository
	{
		public function findById($documentId, $revisionId)
		{
			$row = DB::query(
				'SELECT Id,doc_id,doc_revision,doc_data FROM %b WHERE Id = %i AND doc_id = %i LIMIT 1',
				ContentTables::table('document_rev'),
				(int) $revisionId,
				(int) $documentId
			)->getAssoc();
			if (!$row) {
				return null;
			}

			$data = $this->decode((string) $row['doc_data']);
			return array(
				'id' => (int) $row['Id'],
				'document_id' => (int) $row['doc_id'],
				'created_at' => (int) $row['doc_revision'],
				'fields' => $data['fields'],
				'document' => $data['document'],
			);
		}

		public function fields($documentId, $revision)
		{
			$serialized = DB::query(
				'SELECT doc_data FROM %b WHERE doc_id = %i AND doc_revision = %i LIMIT 1',
				ContentTables::table('document_rev'),
				(int) $documentId,
				(int) $revision
			)->getValue();

			if (!is_string($serialized) || $serialized === '') {
				return array();
			}

			$data = $this->decode($serialized);
			return $data['fields'];
		}

		protected function decode($serialized)
		{
			$data = DocumentRevisionPayload::decodeSerialized($serialized);
			return array('fields' => $data['fields'], 'document' => $data['document']);
		}
	}
