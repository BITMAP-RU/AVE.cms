<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestViewPreview.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Frontend\RequestRenderer;

	/** Executes a saved request and projects rows through its declared result contract. */
	class RequestViewPreview
	{
		public function run($requestId, array $contract, $limit = 10, $rendererCode = 'data_cards')
		{
			$requestId = (int) $requestId;
			$limit = max(1, min(25, (int) $limit));
			$rendererCode = RequestRendererRegistry::normalizePreviewCode($rendererCode);
			$renderer = RequestRendererRegistry::get($rendererCode);
			$execution = (new RequestRenderer())->render($requestId, array(
				'RETURN_DATA' => 1,
				'LIMIT' => $limit,
				'START' => 0,
			));

			if (!is_array($execution) || !isset($execution['rows'])) {
				throw new \RuntimeException('Исполнитель запроса не вернул структурированный результат');
			}

			$items = array();
			$snapshots = new DocumentSnapshotRepository();
			$documentIds = array();
			foreach ((array) $execution['rows'] as $row) {
				$documentId = is_object($row) && isset($row->Id)
					? (int) $row->Id
					: (is_array($row) && isset($row['Id']) ? (int) $row['Id'] : 0);
				if ($documentId > 0) { $documentIds[] = $documentId; }
			}

			$loaded = $snapshots->findMany($documentIds);

			foreach ($documentIds as $documentId) {
				$snapshot = isset($loaded[$documentId]) ? $loaded[$documentId] : null;
				if (!is_array($snapshot)) { continue; }

				$item = RequestResultContract::project($snapshot, $contract);
				$item['document_id'] = $documentId;
				$items[] = $item;
			}

			return array(
				'items' => $items,
				'total' => isset($execution['total']) ? (int) $execution['total'] : count($items),
				'limit' => $limit,
				'query_time' => isset($execution['query_time']) ? (float) $execution['query_time'] : 0.0,
				'sql' => isset($execution['sql']) ? (string) $execution['sql'] : '',
				'executor' => isset($execution['executor']) ? (string) $execution['executor'] : 'legacy',
				'renderer' => array(
					'code' => (string) $renderer['code'],
					'title' => (string) $renderer['title'],
					'presentation' => (string) $renderer['presentation'],
					'formats' => (array) $renderer['formats'],
				),
			);
		}
	}
