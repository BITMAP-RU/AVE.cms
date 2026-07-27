<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentFieldRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldValueCodec;
	use App\Content\Fields\PublicFieldRuntime;

	/** Renders public document fields and structured field elements. */
	class DocumentFieldRenderer
	{
		protected $repository;

		public function __construct(DocumentFieldRepository $repository = null)
		{
			$this->repository = $repository ?: new DocumentFieldRepository();
		}

		public function render($fieldId, $documentId = null, $templateId = null, $maxlength = null)
		{
			if (is_array($fieldId)) {
				$templateId = !$templateId && isset($fieldId[2]) ? $fieldId[2] : $templateId;
				$fieldId = isset($fieldId[1]) ? $fieldId[1] : '';
			}

			$documentId = $documentId ? (int) $documentId : PublicPageContext::documentId();
			$field = $this->repository->field($documentId, $fieldId);
			if (!$field) {
				return '';
			}

			return PublicFieldRuntime::renderDoc(
				$field['rubric_field_type'],
				trim((string) $field['field_value']),
				$field,
				$templateId
			);
		}

		public function element($fieldId, $itemId = 0, $parameter = null, $documentId = null)
		{
			$documentId = $documentId ? (int) $documentId : PublicPageContext::documentId();
			$field = $this->repository->field($documentId, $fieldId);
			if (!$field) {
				return false;
			}

			$items = FieldValueCodec::decodeStructured($field['field_value'], array());
			if (!isset($items[$itemId])) {
				return false;
			}

			$item = $items[$itemId];
			if (is_array($item)) {
				$parts = array_values($item);
			} else {
				$parts = explode('|', (string) $item);
			}

			$index = $parameter === null ? 0 : (int) $parameter;
			return isset($parts[$index]) ? $parts[$index] : false;
		}
	}
