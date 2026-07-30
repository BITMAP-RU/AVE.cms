<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/DocumentPresentationBridge.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Helpers\Hooks;

	/**
	 * Adapts ordinary documents to the shared presentation contract.
	 *
	 * The presentation is resolved before snapshots are loaded, so legacy output
	 * does not pay for data it does not use.
	 */
	class DocumentPresentationBridge
	{
		public static function renderList($contextCode, array $rows, array $targets = array(), array $context = array())
		{
			$ids = self::documentIds($rows);
			$payload = Hooks::filter('catalog.product_cards.rendering', array(
				'context' => (string) $contextCode,
				'product_ids' => $ids,
				'items' => $rows,
				'html' => '',
				'handled' => false,
				'presentation_targets' => $targets,
				'presentation_context' => $context,
			));
			if (is_array($payload)
				&& !empty($payload['handled'])
				&& !empty($payload['public_presentation'])
				&& isset($payload['html'])) {
				return (string) $payload['html'];
			}

			return self::renderRows($contextCode, $rows, $targets, $context);
		}

		public static function renderRows($contextCode, array $rows, array $targets = array(), array $context = array())
		{
			$contextCode = strtolower(trim((string) $contextCode));
			$targets = self::targets($rows, $targets);
			$presentation = PresentationRenderer::resolve($contextCode, $targets);
			if (!$presentation) { return null; }

			$items = self::items($rows, $contextCode);
			if ($rows && count($items) !== count($rows)) {
				return null;
			}

			$context['code'] = $contextCode;
			$context['source'] = isset($context['source']) ? (string) $context['source'] : 'documents';
			return PresentationRenderer::renderResolved($presentation, $items, $context);
		}

		public static function items(array $rows, $contextCode = '')
		{
			$ids = self::documentIds($rows);

			if (!$ids) { return array(); }

			$snapshots = (new DocumentSnapshotRepository())->findMany($ids);
			$items = array();
			foreach ($rows as $row) {
				$row = self::row($row);
				if (!$row) { continue; }
				$id = self::documentId($row);
				$snapshot = isset($snapshots[$id]) ? $snapshots[$id] : null;
				if ($id <= 0 || !is_array($snapshot)) { continue; }
				$items[] = self::enrich(self::item($snapshot), $row, $contextCode);
			}

			return $items;
		}

		public static function item(array $snapshot)
		{
			$document = isset($snapshot['document']) && is_array($snapshot['document'])
				? $snapshot['document']
				: array();
			$record = isset($snapshot['record']) && is_array($snapshot['record'])
				? $snapshot['record']
				: array();
			$fields = array();
			$image = '';
			foreach (isset($snapshot['fields']) && is_array($snapshot['fields']) ? $snapshot['fields'] : array() as $field) {
				if (!is_array($field)) { continue; }
				$key = isset($field['alias']) ? trim((string) $field['alias']) : '';
				if ($key === '') { $key = isset($field['id']) ? (string) (int) $field['id'] : ''; }
				if ($key === '') { continue; }
				$value = array_key_exists('value', $field) ? $field['value'] : null;
				$fields[$key] = $value;
				if ($image === '') { $image = self::imageUrl($value); }
			}

			$alias = isset($document['alias']) ? trim((string) $document['alias']) : '';
			$meta = isset($document['meta']) && is_array($document['meta']) ? $document['meta'] : array();
			$excerpt = isset($document['excerpt']) ? (string) $document['excerpt'] : '';
			return array(
				'id' => isset($document['id']) ? (int) $document['id'] : 0,
				'rubric_id' => isset($document['rubric_id']) ? (int) $document['rubric_id'] : 0,
				'title' => isset($document['title']) ? (string) $document['title'] : '',
				'url' => self::url($alias),
				'alias' => $alias,
				'excerpt' => $excerpt,
				'description' => isset($meta['description']) && trim((string) $meta['description']) !== ''
					? (string) $meta['description']
					: $excerpt,
				'status' => isset($document['status']) ? (int) $document['status'] : 0,
				'published_at' => isset($document['published_at']) ? (int) $document['published_at'] : 0,
				'changed_at' => isset($document['changed_at']) ? (int) $document['changed_at'] : 0,
				'author_id' => isset($document['author_id']) ? (int) $document['author_id'] : 0,
				'views' => isset($record['document_count_view']) ? (int) $record['document_count_view'] : 0,
				'image' => $image,
				'thumb' => $image,
				'meta' => $meta,
				'fields' => $fields,
			);
		}

		protected static function enrich(array $item, array $row, $contextCode)
		{
			foreach (array('image', 'thumb', 'description', 'source') as $key) {
				if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
					$item[$key] = (string) $row[$key];
				}
			}

			foreach (array('views', 'matches') as $key) {
				if (isset($row[$key])) { $item[$key] = (int) $row[$key]; }
			}

			foreach (array('relevance', 'score') as $key) {
				if (isset($row[$key])) { $item[$key] = (float) $row[$key]; }
			}

			if (isset($row['url']) && trim((string) $row['url']) !== '') {
				$item['url'] = (string) $row['url'];
			}

			$contextCode = strtolower(trim((string) $contextCode));
			if ($contextCode === 'search_results') {
				$item['search'] = array(
					'relevance' => isset($item['relevance']) ? (float) $item['relevance'] : 0.0,
				);
			} elseif ($contextCode === 'related') {
				$item['related'] = array(
					'score' => isset($item['score']) ? (float) $item['score'] : 0.0,
					'matches' => isset($item['matches']) ? (int) $item['matches'] : 0,
					'source' => isset($item['source']) ? (string) $item['source'] : '',
				);
			}

			return $item;
		}

		protected static function targets(array $rows, array $targets)
		{
			if (isset($targets['rubric'])) { return $targets; }
			$rubrics = array();
			foreach ($rows as $row) {
				$row = self::row($row);
				if (!$row || empty($row['rubric_id'])) { continue; }
				$rubrics[(int) $row['rubric_id']] = (int) $row['rubric_id'];
			}

			if (count($rubrics) === 1) {
				$ordered = array();
				if (isset($targets['catalog'])) { $ordered['catalog'] = $targets['catalog']; }
				if (isset($targets['request'])) { $ordered['request'] = $targets['request']; }
				$ordered['rubric'] = (string) reset($rubrics);
				foreach ($targets as $type => $key) {
					if (!isset($ordered[$type])) { $ordered[$type] = $key; }
				}

				$targets = $ordered;
			}

			return $targets;
		}

		protected static function documentIds(array $rows)
		{
			$ids = array();
			foreach ($rows as $row) {
				$row = self::row($row);
				if (!$row) { continue; }
				$id = self::documentId($row);
				if ($id > 0) { $ids[] = $id; }
			}

			return $ids;
		}

		protected static function row($row)
		{
			if (is_object($row)) { return get_object_vars($row); }
			return is_array($row) ? $row : array('document_id' => (int) $row);
		}

		protected static function documentId(array $row)
		{
			foreach (array('document_id', 'id', 'Id') as $key) {
				if (isset($row[$key]) && (int) $row[$key] > 0) { return (int) $row[$key]; }
			}

			return 0;
		}

		protected static function imageUrl($value)
		{
			if (!is_array($value)) { return ''; }
			if (isset($value['url']) && is_scalar($value['url'])) {
				return trim((string) $value['url']);
			}

			foreach ($value as $item) {
				$url = self::imageUrl($item);
				if ($url !== '') { return $url; }
			}

			return '';
		}

		protected static function url($alias)
		{
			$alias = trim((string) $alias);
			return $alias === '' || $alias === '/' ? '/' : '/' . ltrim($alias, '/');
		}
	}
