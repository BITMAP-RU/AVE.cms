<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestListReadModel.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Preloads document data used by request-item templates without changing their contract. */
	class RequestListReadModel
	{
		public function prepare(array $rows, $itemTemplate = '', RequestItemContext $context = null)
		{
			$documentIds = $this->documentIds($rows);
			if (!$documentIds) {
				return $rows;
			}

			$context = $context ?: new RequestItemContext();
			$snapshotIds = array();
			$fieldIds = array();
			foreach ($rows as $row) {
				$documentId = $this->documentId($row);
				if ($documentId <= 0) { continue; }

				if (!is_object($row) || !isset($row->rubric_id, $row->document_title)) {
					$snapshotIds[$documentId] = $documentId;
				}

				if (RequestItemRenderer::requiresDocumentData($documentId, $context)) {
					$fieldIds[$documentId] = $documentId;
				}
			}

			if ($snapshotIds) {
				(new \App\Content\Documents\DocumentSnapshotRepository())->findMany(array_values($snapshotIds));
			}

			if ($fieldIds) {
				(new DocumentFieldRepository())->preload(array_values($fieldIds));
			}

			if (strpos((string) $itemTemplate, 'item_list') !== false
				&& class_exists('App\\Modules\\Products\\ProductCardRepository')) {
				\App\Modules\Products\ProductCardRepository::preload($documentIds);
			}

			return $rows;
		}

		public function documentIds(array $rows)
		{
			$ids = array();
			foreach ($rows as $row) {
				$documentId = $this->documentId($row);
				if ($documentId > 0) {
					$ids[$documentId] = $documentId;
				}
			}

			return array_values($ids);
		}

		protected function documentId($row)
		{
			if (is_object($row)) {
				return isset($row->Id) ? (int) $row->Id : 0;
			}

			if (is_array($row)) {
				return isset($row['Id']) ? (int) $row['Id'] : 0;
			}

			return (int) $row;
		}
	}
