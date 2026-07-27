<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentMediaFinalizer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Fields\DocumentMediaFieldType;
	use App\Content\Fields\FieldRegistry;
	use DB;

	/** Finalizes only media uploaded by the current document form. */
	class DocumentMediaFinalizer
	{
		public static function finalize($token, $actorId, $documentId, $rubricId, array &$values)
		{
			$finalization = new DocumentMediaFinalization($token);
			$fields = self::fields($rubricId);
			$rubric = self::rubric($rubricId);
			$validated = false;

			try {
				foreach ($fields as $field) {
					$fieldId = (int) $field['Id'];
					if (!array_key_exists($fieldId, $values)) {
						continue;
					}

					$type = FieldRegistry::get((string) $field['rubric_field_type']);
					if (!$type instanceof DocumentMediaFieldType) {
						continue;
					}

					$replacements = array();
					foreach ($type->mediaPaths($values[$fieldId]) as $path) {
						if (!DocumentMediaDraft::isDraftPath($path)) {
							continue;
						}

						if (!$validated) {
							DocumentMediaDraft::assertOwned($token, $actorId, $documentId);
							$validated = true;
						}

						if (!DocumentMediaDraft::ownsPath($token, $fieldId, $path)) {
							throw new \RuntimeException('Файл не принадлежит текущему полю или сессии загрузки');
						}

						$replacements[$path] = $finalization->move(
							$path,
							DocumentMediaPath::resolve($field, $documentId, $rubric)
						);
					}

					if (!empty($replacements)) {
						$values[$fieldId] = $type->replaceMediaPaths($values[$fieldId], $replacements);
					}
				}
			} catch (\Throwable $e) {
				$finalization->rollback();
				throw $e;
			}

			return $finalization;
		}

		protected static function fields($rubricId)
		{
			return DB::query(
				'SELECT * FROM ' . ContentTables::table('rubric_fields') . ' WHERE rubric_id = %i ORDER BY Id ASC',
				(int) $rubricId
			)->getAll() ?: array();
		}

		protected static function rubric($rubricId)
		{
			$row = DB::query(
				'SELECT Id, rubric_alias FROM ' . ContentTables::table('rubrics') . ' WHERE Id = %i LIMIT 1',
				(int) $rubricId
			)->getAssoc();
			return is_array($row) ? $row : array();
		}
	}
