<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationPreview.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Twig;
	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Helpers\Json;

	class PresentationPreview
	{
		public static function render($documentId, array $input)
		{
			$snapshot = (new DocumentSnapshotRepository())->find((int) $documentId);
			if (!$snapshot) { throw new \RuntimeException('Выберите существующий материал'); }
			$item = self::item($snapshot);
			$context = array('code' => 'admin_preview', 'position' => 1);
			$twig = Twig::twig();
			$itemMarkup = isset($input['draft_item_markup']) ? (string) $input['draft_item_markup'] : '';
			$wrapperMarkup = isset($input['draft_wrapper_markup']) ? (string) $input['draft_wrapper_markup'] : '';
			$emptyMarkup = isset($input['draft_empty_markup']) ? (string) $input['draft_empty_markup'] : '';
			$syntax = PresentationSyntax::checkMany(array(
				'Элемент' => $itemMarkup,
				'Обёртка' => $wrapperMarkup,
				'Пустой результат' => $emptyMarkup,
			));
			if (!$syntax['ok']) { throw new \InvalidArgumentException($syntax['message']); }

			$variables = array(
				'item' => $item,
				'items' => array($item),
				'context' => $context,
				'actions' => array(),
			);
			$content = trim($itemMarkup) !== ''
				? $twig->createTemplate($itemMarkup, 'presentation-preview-item')->render($variables)
				: '';
			$variables['content'] = $content;
			$rendered = trim($wrapperMarkup) !== ''
				? $twig->createTemplate($wrapperMarkup, 'presentation-preview-wrapper')->render($variables)
				: $content;
			if (trim($rendered) === '' && trim($emptyMarkup) !== '') {
				$rendered = $twig->createTemplate($emptyMarkup, 'presentation-preview-empty')->render($variables);
			}

			$json = Json::encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			return array('markup' => $rendered, 'data' => $variables, 'json' => is_string($json) ? $json : '{}');
		}

		public static function item(array $snapshot)
		{
			return DocumentPresentationBridge::item($snapshot);
		}
	}
