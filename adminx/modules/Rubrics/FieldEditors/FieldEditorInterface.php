<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldEditors/FieldEditorInterface.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics\FieldEditors;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	interface FieldEditorInterface
	{
		public function supports($type);

		public function describe($type);

		public function normalizeDefault($type, $value);

		public function parseValue($type, $value);

		public function serializeValue($type, $value);

		/**
		 * Отрисовать редактор значения поля документа.
		 *
		 * @param array $field Собранная строка поля (Model::fieldRow): Id,
		 *                     rubric_field_type, editor_kind, parsed, options,
		 *                     media_upload_dir и т.д.
		 * @return string HTML-разметка контрола (без обёртки .ax-document-field).
		 */
		public function renderEdit(array $field);
	}
