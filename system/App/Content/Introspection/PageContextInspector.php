<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Introspection/PageContextInspector.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Introspection;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\CacheKey;
	use App\Common\DatabaseSchema;
	use App\Content\ContentPlacementMap;
	use App\Content\ContentTables;
	use App\Content\Documents\RubricSchemaBuilder;
	use App\Content\Fields\FieldValueCodec;
	use App\Frontend\DocumentAccessPolicy;
	use App\Frontend\DocumentRenderCache;
	use App\Frontend\DocumentRouteRepository;
	use App\Frontend\DocumentUrlParser;
	use DB;

	/** Builds a read-only package explaining the composition of one public page. */
	class PageContextInspector
	{
		const MAX_COMPONENTS = 250;

		public function inspect($target)
		{
			$resolved = $this->resolveTarget($target);
			$document = $this->document($resolved['document_id']);
			if (!$document) {
				throw new \InvalidArgumentException('Документ не найден');
			}

			$rubricId = (int) $document['rubric_id'];
			$schema = (new RubricSchemaBuilder())->build($rubricId);
			$fields = $this->fields($document, $schema);
			$requests = $this->requests($rubricId);
			$placements = $this->placements(
				(int) $schema['rubric']['rubric_template_id'],
				$rubricId,
				(int) $document['rubric_tmpl_id']
			);

			return array(
				'contract' => 'ave.page-context',
				'contract_version' => 1,
				'target' => $resolved,
				'document' => $this->documentSummary($document),
				'rubric' => $this->rubricSummary($schema),
				'fields' => $fields,
				'templates' => $this->templates($schema, $document),
				'requests' => $requests,
				'presentations' => $this->presentations($rubricId, $requests),
				'components' => $placements,
				'navigation' => $this->navigation((int) $document['document_linked_navi_id']),
				'cache' => $this->cache($document, $schema),
			);
		}

		protected function resolveTarget($target)
		{
			$target = trim((string) $target);
			if ($target === '') {
				throw new \InvalidArgumentException('Укажите URL или ID документа');
			}

			if (ctype_digit($target)) {
				return array(
					'input' => $target,
					'type' => 'document_id',
					'document_id' => (int) $target,
					'alias' => '',
				);
			}

			$path = parse_url($target, PHP_URL_PATH);
			$path = $path === null || $path === false ? $target : $path;
			$suffix = defined('URL_SUFFIX') ? (string) URL_SUFFIX : '';
			$parsed = (new DocumentUrlParser())->parse((string) $path, '/', $suffix);
			$alias = (new DocumentRouteRepository())->normalizeAlias($parsed['alias']);
			$documentId = $alias === '' ? 1 : (new DocumentRouteRepository())->idByAlias($alias);

			return array(
				'input' => $target,
				'type' => 'public_url',
				'document_id' => (int) $documentId,
				'alias' => $alias,
			);
		}

		protected function document($documentId)
		{
			$row = DB::query(
				'SELECT * FROM %b WHERE Id=%i LIMIT 1',
				ContentTables::table('documents'),
				(int) $documentId
			)->getAssoc();

			return $row ? (array) $row : array();
		}

		protected function documentSummary(array $document)
		{
			$object = (object) $document;
			$notFoundId = defined('PAGE_NOT_FOUND_ID') ? (int) PAGE_NOT_FOUND_ID : 0;
			$status = (new DocumentAccessPolicy())->status($object, $notFoundId);
			$alias = trim((string) $document['document_alias'], '/');

			return array(
				'id' => (int) $document['Id'],
				'title' => $this->decode($document['document_title']),
				'alias' => (string) $document['document_alias'],
				'url' => $alias === '' ? '/' : '/' . $alias,
				'rubric_id' => (int) $document['rubric_id'],
				'alternate_template_id' => (int) $document['rubric_tmpl_id'],
				'parent_id' => (int) $document['document_parent'],
				'status' => $status,
				'published' => (int) $document['document_status'] === 1,
				'deleted' => (int) $document['document_deleted'] === 1,
				'in_search' => (int) $document['document_in_search'] === 1,
				'published_at' => (int) $document['document_published'],
				'expires_at' => (int) $document['document_expire'],
				'changed_at' => (int) $document['document_changed'],
				'meta' => array(
					'description' => IntrospectionSanitizer::value((string) $document['document_meta_description']),
					'keywords' => IntrospectionSanitizer::value((string) $document['document_meta_keywords']),
					'robots' => (string) $document['document_meta_robots'],
				),
				'editor_url' => '/documents/' . (int) $document['Id'] . '/edit',
			);
		}

		protected function rubricSummary(array $schema)
		{
			$rubric = $schema['rubric'];
			return array(
				'id' => (int) $rubric['Id'],
				'title' => $this->decode($rubric['rubric_title']),
				'alias_pattern' => (string) $rubric['rubric_alias'],
				'purpose' => (string) $rubric['rubric_purpose'],
				'documents_enabled' => (int) $rubric['rubric_docs_active'] === 1,
				'template_id' => (int) $rubric['rubric_template_id'],
				'schema_version' => (string) $schema['version'],
				'fields_count' => count($schema['fields']),
				'groups_count' => count($schema['groups']),
				'editor_url' => '/rubrics?edit=' . (int) $rubric['Id'],
			);
		}

		protected function fields(array $document, array $schema)
		{
			$values = array();
			foreach (DB::query(
				'SELECT f.rubric_field_id,f.field_value,t.field_value field_value_more,f.field_number_value'
					. ' FROM %b f LEFT JOIN %b t ON t.document_id=f.document_id'
					. ' AND t.rubric_field_id=f.rubric_field_id WHERE f.document_id=%i',
				ContentTables::table('document_fields'),
				ContentTables::table('document_fields_text'),
				(int) $document['Id']
			)->getAll() ?: array() as $row) {
				$raw = (string) $row['field_value'] . (string) $row['field_value_more'];
				$values[(int) $row['rubric_field_id']] = array(
					'raw' => $raw,
					'numeric' => $row['field_number_value'] === null ? null : (float) $row['field_number_value'],
				);
			}

			$result = array();
			foreach ($schema['fields'] as $field) {
				$fieldId = (int) $field['Id'];
				$stored = isset($values[$fieldId]) ? $values[$fieldId] : array('raw' => '', 'numeric' => null);
				$settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();
				$structured = FieldValueCodec::decodeStructured($stored['raw'], null);
				$value = is_array($structured) ? $structured : $stored['raw'];
				$alias = (string) $field['rubric_field_alias'];
				$title = $this->decode($field['rubric_field_title']);
				$result[] = array(
					'id' => $fieldId,
					'alias' => $alias,
					'title' => $title,
					'type' => (string) $field['rubric_field_type'],
					'group_id' => (int) $field['rubric_field_group'],
					'required' => !empty($settings['required']),
					'numeric' => !empty($field['rubric_field_numeric']),
					'value' => IntrospectionSanitizer::fieldValue($value, $alias, $title),
					'numeric_value' => $stored['numeric'],
					'empty' => $this->emptyValue($value),
					'settings' => IntrospectionSanitizer::value($settings),
				);
			}

			return $result;
		}

		protected function templates(array $schema, array $document)
		{
			$rubric = $schema['rubric'];
			$site = DB::query(
				'SELECT Id,template_title,template_text FROM %b WHERE Id=%i LIMIT 1',
				ContentTables::table('templates'),
				(int) $rubric['rubric_template_id']
			)->getAssoc();
			$alternate = array();
			if ((int) $document['rubric_tmpl_id'] > 0) {
				$alternate = DB::query(
					'SELECT id,title,template FROM %b WHERE id=%i AND rubric_id=%i LIMIT 1',
					ContentTables::table('rubric_templates'),
					(int) $document['rubric_tmpl_id'],
					(int) $rubric['Id']
				)->getAssoc() ?: array();
			}

			return array(
				'site' => $site
					? $this->templateSummary('site', (int) $site['Id'], $site['template_title'], $site['template_text'], '/templates?edit=' . (int) $site['Id'])
					: null,
				'rubric' => $this->templateSummary(
					'rubric',
					(int) $rubric['Id'],
					$rubric['rubric_title'],
					$rubric['rubric_template'],
					'/rubrics?templates=' . (int) $rubric['Id']
				),
				'alternate' => $alternate
					? $this->templateSummary(
						'rubric_template',
						(int) $alternate['id'],
						$alternate['title'],
						$alternate['template'],
						'/rubrics?templates=' . (int) $rubric['Id'] . '&template=' . (int) $alternate['id']
					)
					: null,
				'fragments' => array(
					'header' => $this->contentDescriptor($rubric['rubric_header_template']),
					'og' => $this->contentDescriptor($rubric['rubric_og_template']),
					'footer' => $this->contentDescriptor($rubric['rubric_footer_template']),
				),
			);
		}

		protected function templateSummary($type, $id, $title, $content, $url)
		{
			return array(
				'type' => $type,
				'id' => (int) $id,
				'title' => $this->decode($title),
				'content' => $this->contentDescriptor($content),
				'editor_url' => $url,
			);
		}

		protected function contentDescriptor($content)
		{
			$content = (string) $content;
			return array(
				'empty' => trim($content) === '',
				'length' => mb_strlen($content),
				'hash' => 'sha256:' . hash('sha256', $content),
			);
		}

		protected function requests($rubricId)
		{
			$result = array();
			foreach (DB::query(
				'SELECT Id,request_alias,request_title,request_executor_mode,request_items_per_page,'
					. 'request_show_pagination,request_cache_lifetime,request_template_main,request_template_item'
					. ' FROM %b WHERE rubric_id=%i ORDER BY Id',
				ContentTables::table('request'),
				(int) $rubricId
			)->getAll() ?: array() as $row) {
				$result[] = array(
					'id' => (int) $row['Id'],
					'alias' => (string) $row['request_alias'],
					'title' => $this->decode($row['request_title']),
					'executor' => (string) $row['request_executor_mode'],
					'items_per_page' => (int) $row['request_items_per_page'],
					'pagination' => (int) $row['request_show_pagination'] === 1,
					'cache_lifetime' => (int) $row['request_cache_lifetime'],
					'main_template' => $this->contentDescriptor($row['request_template_main']),
					'item_template' => $this->contentDescriptor($row['request_template_item']),
					'editor_url' => '/requests/' . (int) $row['Id'],
				);
			}

			return $result;
		}

		protected function placements($templateId, $rubricId, $alternateTemplateId)
		{
			$roots = array(
				'template:' . (int) $templateId => true,
				'rubric:' . (int) $rubricId => true,
			);
			if ($alternateTemplateId > 0) {
				$roots['rubric_template:' . (int) $alternateTemplateId] = true;
			}

			$pending = array_keys($roots);
			$selected = array();
			$rows = ContentPlacementMap::snapshot();
			while ($pending && count($selected) < self::MAX_COMPONENTS) {
				$sourceKey = array_shift($pending);
				foreach ($rows as $row) {
					if ($row['source_type'] . ':' . (int) $row['source_id'] !== $sourceKey) {
						continue;
					}

					$key = $sourceKey . ':' . $row['source_part'] . ':' . $row['component_type'] . ':' . $row['component_key'];
					if (isset($selected[$key])) {
						continue;
					}

					$selected[$key] = IntrospectionSanitizer::value($row);
					if (in_array($row['component_type'], array('block', 'request', 'navigation'), true)
						&& (int) $row['component_id'] > 0) {
						$childKey = $row['component_type'] . ':' . (int) $row['component_id'];
						if (!isset($roots[$childKey])) {
							$roots[$childKey] = true;
							$pending[] = $childKey;
						}
					}
				}
			}

			return array_values($selected);
		}

		protected function presentations($rubricId, array $requests)
		{
			$table = ContentTables::table('presentations');
			$assignments = ContentTables::table('presentation_assignments');
			if (!DatabaseSchema::tableExists($table) || !DatabaseSchema::tableExists($assignments)) {
				return array();
			}

			$requestIds = array();
			foreach ($requests as $request) {
				$requestIds[] = (string) $request['id'];
			}

			$requestIds = array_values(array_unique($requestIds));

			$sql = 'SELECT a.id,a.presentation_id,a.context_code,a.target_type,a.target_key,a.mode,'
				. 'p.title,p.code,p.kind,p.is_published,p.version'
				. ' FROM ' . $assignments . ' a INNER JOIN ' . $table . ' p ON p.id=a.presentation_id'
				. ' WHERE (a.target_type=%s AND a.target_key=%s)'
				. ' OR (a.target_type=%s AND a.target_key=%s)';
			$args = array($sql, 'global', '0', 'rubric', (string) (int) $rubricId);
			if ($requestIds) {
				$args[0] .= ' OR (a.target_type=%s AND a.target_key IN %ls)';
				$args[] = 'request';
				$args[] = $requestIds;
			}

			$args[0] .= ' ORDER BY a.context_code,a.target_type,a.target_key';

			return IntrospectionSanitizer::value(call_user_func_array(array('DB', 'query'), $args)->getAll() ?: array());
		}

		protected function navigation($navigationId)
		{
			if ($navigationId <= 0) {
				return null;
			}

			$row = DB::query(
				'SELECT navigation_id,title,alias FROM %b WHERE navigation_id=%i LIMIT 1',
				ContentTables::table('navigation'),
				$navigationId
			)->getAssoc();

			return $row ? array(
				'id' => (int) $row['navigation_id'],
				'title' => $this->decode($row['title']),
				'alias' => (string) $row['alias'],
				'editor_url' => '/navigation?edit=' . (int) $row['navigation_id'],
			) : array('id' => $navigationId, 'missing' => true, 'editor_url' => '/navigation');
		}

		protected function cache(array $document, array $schema)
		{
			$document['rubric_changed'] = (int) $schema['rubric']['rubric_changed'];
			$descriptor = (new DocumentRenderCache())->descriptor(
				(object) $document,
				2,
				(int) $document['rubric_id']
			);
			$files = array();
			foreach ($descriptor ?: array() as $type => $path) {
				$files[$type] = array(
					'path' => $this->relativePath($path),
					'exists' => is_file($path),
					'size' => is_file($path) ? (int) filesize($path) : 0,
				);
			}

			return array(
				'document_key' => CacheKey::document((int) $document['Id']),
				'tags' => CacheKey::tags('content', 'document-' . (int) $document['Id'], array(
					CacheKey::tag('rubric', (int) $document['rubric_id']),
					'template',
					'request',
					'sysblock',
					'navigation',
				)),
				'template_cache_enabled' => defined('CACHE_DOC_TPL') && CACHE_DOC_TPL,
				'page_cache_enabled' => defined('CACHE_DOC_FULL') && CACHE_DOC_FULL,
				'files' => $files,
			);
		}

		protected function relativePath($path)
		{
			$root = rtrim(str_replace('\\', '/', BASEPATH), '/') . '/';
			$path = str_replace('\\', '/', (string) $path);
			return strpos($path, $root) === 0 ? substr($path, strlen($root)) : basename($path);
		}

		protected function emptyValue($value)
		{
			if (is_array($value)) {
				return count(array_filter($value, function ($item) {
					return $item !== '' && $item !== null && $item !== array();
				})) === 0;
			}

			return trim((string) $value) === '';
		}

		protected function decode($value)
		{
			return htmlspecialchars_decode((string) $value, ENT_QUOTES);
		}
	}
