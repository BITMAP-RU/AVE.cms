<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Documents/migrations/008_seed_clean_not_found_document.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Content\Documents\DocumentAliasRegistry;
	use App\Content\PublicShellTables;

	return function (array $context) {
		$documents = ContentTables::table('documents');
		$starter = \DB::query('SELECT Id, rubric_id, document_title FROM ' . $documents . ' WHERE Id = 1 LIMIT 1')->getAssoc();
		$queries = 1;
		if (!is_array($starter) || (int) $starter['rubric_id'] !== 1 || strpos((string) $starter['document_title'], 'AVE.cms') !== 0) {
			return $queries;
		}

		$notFound = \DB::query('SELECT Id, document_alias, document_title FROM ' . $documents . ' WHERE Id = 2 LIMIT 1')->getAssoc();
		$queries++;
		if (is_array($notFound)) {
			$isExpected = DocumentAliasRegistry::normalize($notFound['document_alias']) === '404'
				|| (string) $notFound['document_title'] === 'Страница не найдена';
			if (!$isExpected) { return $queries; }
		} else {
			if (DocumentAliasRegistry::conflict('404', 2)) { return $queries + 1; }

			$now = time();
			\DB::Insert($documents, array(
				'Id' => 2, 'rubric_id' => 1, 'rubric_tmpl_id' => 0, 'document_parent' => 0,
				'document_alias' => '404', 'document_alias_header' => 301, 'document_alias_history' => 0,
				'document_short_alias' => '', 'document_title' => 'Страница не найдена',
				'document_breadcrumb_title' => 'Ошибка 404', 'document_published' => $now,
				'document_expire' => 0, 'document_changed' => $now, 'document_author_id' => 1,
				'document_in_search' => 0, 'document_meta_keywords' => '',
				'document_meta_description' => 'Запрошенная страница не найдена.',
				'document_meta_robots' => 'noindex,nofollow', 'document_sitemap_freq' => 3,
				'document_sitemap_pr' => 0, 'document_status' => '1', 'document_deleted' => '0',
				'document_count_print' => 0, 'document_count_view' => 0, 'document_linked_navi_id' => 0,
				'document_excerpt' => '', 'document_tags' => '', 'document_property' => '',
				'document_position' => 2, 'module_catalog' => '', 'guid' => '',
			));
			\DB::Insert(ContentTables::table('document_fields'), array(
				'rubric_field_id' => 1, 'document_id' => 2, 'field_number_value' => 0,
				'field_value' => '', 'document_in_search' => 0,
			));
			$fieldId = (int) \DB::insertId();
			\DB::Insert(ContentTables::table('document_fields_text'), array(
				'Id' => $fieldId, 'rubric_field_id' => 1, 'document_id' => 2,
				'field_value' => '<main><h1>Страница не найдена</h1><p>Запрошенная страница не существует или была перемещена.</p><p><a href="/">Перейти на главную</a></p></main>',
			));
			$queries += 3;
		}

		\DB::Update(SystemTables::table('settings'), array('value' => '2', 'type' => 'int'), 'param = %s', 'page_not_found_id');
		\DB::Update(PublicShellTables::table('settings'), array('page_not_found_id' => 2), 'Id = %i', 1);
		return $queries + 2;
	};
