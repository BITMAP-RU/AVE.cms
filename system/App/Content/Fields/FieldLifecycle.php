<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldLifecycle.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Lifecycle;

	/** Shared normalization and persistence events for every document writer. */
	class FieldLifecycle
	{
		public static function normalizeForStorage($value, array $field, array $document = array(), $source = 'runtime')
		{
			$id = isset($field['Id']) ? (int) $field['Id'] : 0;
			$typeCode = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
			$normalizing = Lifecycle::event('content.field.normalizing', 'field', 'normalizing', $id, array(
				'type' => $typeCode,
				'value' => $value,
				'field' => $field,
				'document' => $document,
			), null, array(), $source);
			if ($normalizing->cancelled()) {
				return $normalizing->result();
			}

			$value = $normalizing->value('value', $value);
			$field = $normalizing->value('field', $field);
			$plugin = FieldRegistry::get($typeCode);
			if ($plugin) {
				$decoded = FieldValueCodec::decodeStructured($value, null);
				$context = new FieldContext(is_array($decoded) ? $decoded : $value, $field, 'save');
				$context->document = $document;
				$value = $plugin->save($context);
			}

			$value = FieldValueCodec::normalizeForStorage($value);

			$normalized = Lifecycle::event('content.field.normalized', 'field', 'normalized', $id, array(
				'type' => $typeCode,
				'field' => $field,
				'document' => $document,
			), $value, array(), $source);
			return $normalized->result();
		}

		public static function saving($value, array $field, $documentId, $source = 'runtime')
		{
			return Lifecycle::event('content.field.saving', 'field', 'saving', isset($field['Id']) ? (int) $field['Id'] : 0, array(
				'type' => isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '',
				'field' => $field,
				'document_id' => (int) $documentId,
				'value' => $value,
			), $value, array(), $source);
		}

		public static function saved($value, array $field, $documentId, $source = 'runtime')
		{
			return Lifecycle::event('content.field.saved', 'field', 'saved', isset($field['Id']) ? (int) $field['Id'] : 0, array(
				'type' => isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '',
				'field' => $field,
				'document_id' => (int) $documentId,
			), $value, array(), $source);
		}
	}
