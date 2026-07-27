<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicFieldApi.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Native entry points used by compiled public field templates. */
	class PublicFieldApi
	{
		protected static $fieldIds = array();

		public static function fieldId($rubricId, $identifier)
		{
			$key = (int) $rubricId . ':' . (string) $identifier;
			if (!array_key_exists($key, self::$fieldIds)) {
				self::$fieldIds[$key] = (int) \DB::query(
					'SELECT Id FROM %b WHERE (rubric_field_alias = %s OR Id = %i) AND rubric_id = %i LIMIT 1',
					\App\Content\ContentTables::table('rubric_fields'),
					(string) $identifier,
					(int) $identifier,
					(int) $rubricId
				)->getValue();
			}

			return self::$fieldIds[$key];
		}

		public static function raw($fieldId, $documentId = null, $parameter = null)
		{
			$documentId = $documentId ? (int) $documentId : PublicPageContext::documentId();
			if ($documentId <= 0) {
				return false;
			}

			$field = (new DocumentFieldRepository())->field($documentId, $fieldId);
			if (!$field) {
				return false;
			}

			$value = (string) $field['field_value'];
			if ($parameter === null) {
				return $value;
			}

			$parts = array_values(array_diff(explode('|', $value), array('')));
			$index = (int) $parameter;
			return isset($parts[$index]) ? $parts[$index] : false;
		}

		public static function render($fieldId, $documentId = null, $templateId = null, $maxlength = null)
		{
			return (new DocumentFieldRenderer())->render($fieldId, $documentId, $templateId, $maxlength);
		}

		public static function renderRequest($fieldId, $documentId, $maxlength = null, $rubricId = 0)
		{
			return (new RequestFieldRenderer())->render($fieldId, $documentId, $maxlength, $rubricId);
		}

		public static function requestValue($fieldId, $documentId, $maxlength = 0)
		{
			return (new RequestFieldRenderer())->value($fieldId, $documentId, $maxlength);
		}
	}
