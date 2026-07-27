<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/migrations/006_configure_product_media_paths.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Helpers\Json;

	return function (array $context) {
		$fieldsTable = ContentTables::table('rubric_fields');
		$rubricsTable = ContentTables::table('rubrics');
		$rows = \DB::query(
			'SELECT Id, rubric_field_settings FROM `' . $fieldsTable . '`'
			. ' WHERE rubric_id = %i AND Id IN (22, 215, 217)'
			. " AND rubric_field_type IN ('image_single', 'image_multi', 'image_mega')",
			6
		)->getAll() ?: array();
		$queries = 1;

		foreach ($rows as $row) {
			$settings = Json::toArray(isset($row['rubric_field_settings']) ? $row['rubric_field_settings'] : '');
			$settings['upload_dir'] = '/articles/doc_%id';
			\DB::Update(
				$fieldsTable,
				array('rubric_field_settings' => Json::encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
				'Id = %i',
				(int) $row['Id']
			);
			$queries++;
		}

		\DB::query(
			'UPDATE `' . $rubricsTable . '` SET rubric_code_end = %s'
			. ' WHERE Id = %i AND SHA1(rubric_code_end) = %s',
			'',
			6,
			'e2fc38f19784135911dbb7e4fb78862da21e0977'
		);

		return $queries + 1;
	};
