<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentMediaPath.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldSettings;

	/** Resolves a field-owned upload directory after the document ID is known. */
	class DocumentMediaPath
	{
		const DEFAULT_TEMPLATE = 'documents/%id/%field_alias';

		public static function resolve(array $field, $documentId, array $rubric = array())
		{
			$documentId = (int) $documentId;
			if ($documentId <= 0) {
				throw new \RuntimeException('Нельзя определить папку медиа без ID документа');
			}

			return self::expand(self::template($field), $field, $documentId, $rubric, false);
		}

		public static function describe(array $field, array $rubric = array())
		{
			return self::expand(self::template($field), $field, 0, $rubric, true);
		}

		public static function template(array $field)
		{
			$settings = FieldSettings::effective($field);
			$template = isset($settings['upload_dir']) ? trim((string) $settings['upload_dir']) : '';
			return $template !== '' ? $template : self::DEFAULT_TEMPLATE;
		}

		protected static function expand($template, array $field, $documentId, array $rubric, $describe)
		{
			$fieldAlias = self::slug(isset($field['rubric_field_alias']) ? $field['rubric_field_alias'] : '');
			if ($fieldAlias === '') {
				$fieldAlias = 'field-' . (int) (isset($field['Id']) ? $field['Id'] : 0);
			}

			$rubricAlias = self::slug(isset($rubric['rubric_alias']) ? $rubric['rubric_alias'] : '');
			$document = $describe ? '{ID}' : (string) (int) $documentId;
			$replacements = array(
				'{document_id}' => $document,
				'{doc_id}' => $document,
				'%document_id' => $document,
				'%id' => $document,
				'{rubric_id}' => (string) (int) (isset($field['rubric_id']) ? $field['rubric_id'] : 0),
				'%rubric_id' => (string) (int) (isset($field['rubric_id']) ? $field['rubric_id'] : 0),
				'{rubric_alias}' => $rubricAlias !== '' ? $rubricAlias : 'rubric',
				'%rubric_alias' => $rubricAlias !== '' ? $rubricAlias : 'rubric',
				'{field_id}' => (string) (int) (isset($field['Id']) ? $field['Id'] : 0),
				'%field_id' => (string) (int) (isset($field['Id']) ? $field['Id'] : 0),
				'{field_alias}' => $fieldAlias,
				'%field_alias' => $fieldAlias,
				'%Y' => date('Y'),
				'%m' => date('m'),
				'%d' => date('d'),
			);
			$path = strtr(trim((string) $template), $replacements);
			$path = preg_replace('#/+#', '/', str_replace('\\', '/', $path));
			$path = '/' . trim($path, '/');
			if (strpos($path, '/uploads/') !== 0 && $path !== '/uploads') {
				$path = '/uploads' . $path;
			}

			if (strpos($path, '..') !== false || strpos($path, "\0") !== false || strpos($path, DocumentMediaDraft::ROOT) === 0) {
				throw new \RuntimeException('Некорректный шаблон папки медиа');
			}

			return rtrim($path, '/');
		}

		protected static function slug($value)
		{
			return trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $value), '-');
		}
	}
