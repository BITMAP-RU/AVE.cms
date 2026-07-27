<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestListRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Url;
	use App\Helpers\Debug;
	use App\Common\Lifecycle;
	use App\Common\StoredPhpRuntime;

	/** Renders a request template for an explicit, already selected document list. */
	class RequestListRenderer
	{
		public function render($requestId, array $params)
		{
			$requestId = (int) $requestId;
			$requestData = (new RequestRepository())->find($requestId);
			if (!$requestData) {
				return '';
			}

			$currentDocument = PublicPageContext::document();
			$rendering = Lifecycle::event('content.query.rendering', 'query', 'rendering', $requestId, array(
				'request' => $requestData,
				'params' => $params,
				'document' => $currentDocument,
			), null, array(), 'request_list_renderer');
			if ($rendering->cancelled()) { return (string) $rendering->result(); }
			$requestData = $rendering->value('request', $requestData);
			$params = $rendering->value('params', $params);
			$request = (object) $requestData;
			$rows = isset($params['ROWS']) && is_array($params['ROWS']) ? $params['ROWS'] : array();

			Debug::startTime('request_' . $requestId);
			$items = '';
			$itemNumber = 0;
			$itemCount = count($rows);
			$numItems = isset($params['TOTAL'])
				? max($itemCount, (int) $params['TOTAL'])
				: $itemCount;
			$limit = isset($params['LIMIT']) && is_numeric($params['LIMIT']) && $params['LIMIT'] > ''
				? (int) $params['LIMIT']
				: (int) $request->request_items_per_page;
			$numPages = isset($params['PAGES'])
				? max(0, (int) $params['PAGES'])
				: ($limit > 0 ? (int) ceil($numItems / $limit) : 0);
			$currentPage = isset($params['PAGE'])
				? max(1, (int) $params['PAGE'])
				: PaginationRenderer::currentPage('page');
			$pagination = isset($params['PAGINATION_HTML']) ? (string) $params['PAGINATION_HTML'] : '';

			$itemContext = new RequestItemContext(
				$request->Id,
				0,
				$request->request_cache_elements == 1,
				$request->request_changed_elements
			);

			Debug::startTime('ELEMENTS_ALL');
			$itemRenderer = new RequestItemRenderer();
			$rows = (new RequestListReadModel())->prepare(
				$rows,
				(string) $request->request_template_item,
				$itemContext
			);

			foreach ($rows as $row) {
				$documentId = is_object($row) && isset($row->Id) ? (int) $row->Id : (int) $row;
				if ($documentId <= 0) {
					continue;
				}

				$itemNumber++;
				$lastItem = $itemNumber === $itemCount;
				Debug::startTime('ELEMENT_' . $itemNumber);

				$renderSource = is_object($row) && isset($row->rubric_id, $row->document_title) ? $row : $documentId;
				$item = $itemRenderer->render(
					$renderSource,
					$request->request_template_item,
					'',
					$itemContext->forItem($itemNumber)
				);
				PublicProfiler::record(array('REQUESTS', $requestId, 'ELEMENTS', $itemNumber), Debug::endTime('ELEMENT_' . $itemNumber));
				$item = RequestItemRenderer::decorateSequence($item, $documentId, $itemNumber, $lastItem);
				$items .= $item;
			}

			PublicProfiler::record(array('REQUESTS', $requestId, 'ELEMENTS', 'ALL'), Debug::endTime('ELEMENTS_ALL'));

			$mainTemplate = (string) $request->request_template_main;
			$blockRenderer = new BlockRenderer();
			$mainTemplate = preg_replace_callback('/\[tag:block:([A-Za-z0-9-_]{1,20}+)\]/', function ($match) use ($blockRenderer) {
				return $blockRenderer->visual($match[1]);
			}, $mainTemplate);
			$mainTemplate = preg_replace_callback('/\[tag:sysblock:([A-Za-z0-9-_]{1,20}+)(|:\{(.*?)\})\]/', function ($match) use ($blockRenderer) {
				return $blockRenderer->system($match[1], isset($match[2]) ? $match[2] : null);
			}, $mainTemplate);
			$mainTemplate = preg_replace_callback(
				'/\[tag:date:([a-zA-Z0-9-. \/]+)\]/',
				function ($match) use ($currentDocument) {
					return $currentDocument
						? \App\Helpers\Locales::translateDate(date($match[1], $currentDocument->document_published))
						: '';
				},
				$mainTemplate
			);

			$replacements = array(
				'[tag:docid]' => $currentDocument ? $currentDocument->Id : 0,
				'[tag:docauthorid]' => $currentDocument ? $currentDocument->document_author_id : 0,
				'[tag:docauthor]' => $currentDocument ? \App\Common\Auth\PublicUserNames::byId($currentDocument->document_author_id) : '',
				'[tag:humandate]' => $currentDocument ? \App\Helpers\Locales::humanDate($currentDocument->document_published) : '',
				'[tag:docdate]' => $currentDocument ? \App\Helpers\Locales::prettyDate(strftime(DATE_FORMAT, $currentDocument->document_published)) : '',
				'[tag:doctime]' => $currentDocument ? \App\Helpers\Locales::prettyDate(strftime(TIME_FORMAT, $currentDocument->document_published)) : '',
				'[tag:domain]' => Url::site(),
				'[tag:pages]' => $pagination,
				'[tag:doctotal]' => $numItems,
				'[tag:doconpage]' => $itemNumber,
				'[tag:pages:curent]' => $currentPage,
				'[tag:pages:total]' => $numPages,
				'[tag:pagetitle]' => $currentDocument ? stripslashes(htmlspecialchars_decode($currentDocument->document_title)) : '',
				'[tag:alias]' => $currentDocument && isset($currentDocument->document_alias) ? $currentDocument->document_alias : '',
			);
			$mainTemplate = str_replace(array_keys($replacements), array_values($replacements), $mainTemplate);
			$mainTemplate = preg_replace('/\[tag:doc:([a-zA-Z0-9-_]+)\]/u', '', $mainTemplate);
			$mainTemplate = preg_replace('/\[tag:langfile:[a-zA-Z0-9-_]+\]/u', '', $mainTemplate);

			$result = str_replace('[tag:content]', $items, $mainTemplate);
			$result = (new HiddenContentRenderer())->render($result);
			$result = str_replace('[tag:path]', ABS_PATH, $result);
			$result = str_replace(
				'[tag:mediapath]',
				ABS_PATH . 'templates/' . (defined('THEME_FOLDER') ? THEME_FOLDER : DEFAULT_THEME_FOLDER) . '/',
				$result
			);
			$result = (new ModuleTagRenderer())->render($result, null, $currentDocument);
			PublicProfiler::record(array('REQUESTS', $requestId, 'TIME'), Debug::endTime('request_' . $requestId));

			if ($request->request_show_statistic) {
				$result .= '<div class="request_statistic"><br>Найдено: ' . $numItems
					. '<br>Показано: ' . $itemCount
					. '<br>Время генерации: ' . Debug::endTime('request_' . $requestId)
					. ' сек<br>Пиковое значение: ' . number_format(memory_get_peak_usage() / 1024, 0, ',', ' ')
					. ' Kb</div>';
			}

			$result = StoredPhpRuntime::normalizeDataPrefixes($result);
			$rendered = Lifecycle::event('content.query.rendered', 'query', 'rendered', $requestId, array(
				'request' => $requestData,
				'document_id' => $currentDocument && isset($currentDocument->Id) ? (int) $currentDocument->Id : 0,
			), $result, array(), 'request_list_renderer');
			return (string) $rendered->result();
		}
	}
