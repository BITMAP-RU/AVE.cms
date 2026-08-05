<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/DocumentMediaReplacement.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Media\MediaAudit;
	use App\Adminx\Media\Model as MediaModel;
	use App\Content\Fields\DocumentMediaFieldType;
	use App\Content\Fields\FieldRegistry;

	/** Removes superseded document media only after the document transaction succeeds. */
	class DocumentMediaReplacement
	{
		public static function requestedFieldIds($value)
		{
			$value = is_array($value) ? $value : array($value);
			$ids = array();
			foreach ($value as $id) {
				$id = (int) $id;
				if ($id > 0) { $ids[$id] = $id; }
			}

			return array_values($ids);
		}

		/** Capture trusted old paths before field values are saved. */
		public static function capture(array $fieldGroups, array $newValues, array $requestedFieldIds)
		{
			$requested = array_fill_keys(self::requestedFieldIds($requestedFieldIds), true);
			if (!$requested) { return array(); }

			$captured = array();
			foreach ($fieldGroups as $group) {
				foreach (isset($group['items']) && is_array($group['items']) ? $group['items'] : array() as $field) {
					$fieldId = isset($field['Id']) ? (int) $field['Id'] : 0;
					if (!isset($requested[$fieldId]) || !array_key_exists($fieldId, $newValues)) { continue; }
					$typeCode = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
					$type = FieldRegistry::get($typeCode);
					if (!$type instanceof DocumentMediaFieldType) { continue; }

					$oldValue = isset($field['parsed']) ? $field['parsed'] : array();
					$captured[$fieldId] = array(
						'type' => $typeCode,
						'paths' => self::uploadPaths($type->mediaPaths($oldValue)),
					);
				}
			}

			return $captured;
		}

		public static function removedPaths(array $captured, array $newValues)
		{
			$removed = array();
			foreach ($captured as $fieldId => $field) {
				$type = FieldRegistry::get(isset($field['type']) ? (string) $field['type'] : '');
				if (!$type instanceof DocumentMediaFieldType) { continue; }
				$newValue = array_key_exists($fieldId, $newValues) ? $newValues[$fieldId] : array();
				$newPaths = array_fill_keys(self::uploadPaths($type->mediaPaths($newValue)), true);
				foreach (isset($field['paths']) && is_array($field['paths']) ? $field['paths'] : array() as $path) {
					if (!isset($newPaths[$path])) { $removed[$path] = $path; }
				}
			}

			return array_values($removed);
		}

		/** Move currently unused files to the recoverable media trash. */
		public static function cleanup(array $captured, array $newValues)
		{
			$paths = self::removedPaths($captured, $newValues);
			$result = array('checked' => count($paths), 'trashed' => 0, 'kept' => 0, 'errors' => array());
			if (!$paths) { return $result; }

			try {
				$usage = MediaAudit::inspectUsageMany($paths, 1, false);
			} catch (\Throwable $e) {
				$result['errors'][] = $e->getMessage();
				return $result;
			}

			foreach ($paths as $path) {
				if (!isset($usage[$path])) { continue; }
				if (!empty($usage[$path]['use_count'])) {
					$result['kept']++;
					continue;
				}

				try {
					MediaModel::trash($path);
					$result['trashed']++;
				} catch (\Throwable $e) {
					$result['errors'][] = basename($path) . ': ' . $e->getMessage();
				}
			}

			return $result;
		}

		protected static function uploadPaths(array $paths)
		{
			$result = array();
			foreach ($paths as $path) {
				$path = html_entity_decode(trim((string) $path), ENT_QUOTES, 'UTF-8');
				$parsed = parse_url($path, PHP_URL_PATH);
				$path = is_string($parsed) ? rawurldecode($parsed) : $path;
				$path = '/' . ltrim(preg_replace('#/+#', '/', str_replace('\\', '/', $path)), '/');
				if (strpos($path, '/uploads/') === 0 && strpos($path, '/../') === false) { $result[$path] = $path; }
			}

			return array_values($result);
		}
	}
