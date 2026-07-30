<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Twig;
	use App\Common\Lifecycle;
	use App\Common\LifecycleEvent;

	/** Renders only explicitly enabled published presentations and otherwise returns null. */
	class PresentationRenderer
	{
		protected static $requiredStyles = array();

		public static function renderList($contextCode, array $targets, array $items, array $context = array())
		{
			$assignment = self::resolve($contextCode, $targets);
			if (!$assignment) { return null; }
			return self::renderResolved($assignment, $items, $context);
		}

		public static function resolve($contextCode, array $targets)
		{
			try {
				$assignment = PresentationAssignmentRepository::resolve($contextCode, $targets);
				$resolved = Lifecycle::event(
					'content.presentation.resolved',
					'presentation',
					'resolved',
					(string) $contextCode,
					array('context_code' => (string) $contextCode, 'targets' => $targets, 'assignment' => $assignment),
					$assignment,
					array(),
					'presentation_renderer'
				);
				$assignment = is_array($resolved->result()) ? $resolved->result() : null;
				if (!$assignment
					|| !isset($assignment['mode'], $assignment['is_published'])
					|| (string) $assignment['mode'] !== 'native'
					|| empty($assignment['is_published'])
				) {
					return null;
				}

				return $assignment;
			} catch (\Throwable $e) {
				error_log('Public presentation ' . (string) $contextCode . ': ' . $e->getMessage());
				return null;
			}
		}

		public static function renderResolved(array $presentation, array $items, array $context = array())
		{
			try {
				return self::renderPublished($presentation, $items, $context);
			} catch (\Throwable $e) {
				$code = isset($presentation['presentation_code']) ? (string) $presentation['presentation_code'] : 'unknown';
				error_log('Public presentation ' . $code . ': ' . $e->getMessage());
				return null;
			}
		}

		public static function renderPublished(array $presentation, array $items, array $context = array())
		{
			$itemMarkup = isset($presentation['published_item_markup'])
				? trim((string) $presentation['published_item_markup'])
				: '';
			if ($itemMarkup === '') {
				throw new \RuntimeException('Опубликованный шаблон элемента пуст');
			}

			$context['code'] = isset($context['code']) && trim((string) $context['code']) !== ''
				? (string) $context['code']
				: 'content_list';
			$context['presentation'] = array(
				'id' => isset($presentation['presentation_id'])
					? (int) $presentation['presentation_id']
					: (isset($presentation['id']) ? (int) $presentation['id'] : 0),
				'code' => isset($presentation['presentation_code']) ? (string) $presentation['presentation_code'] : '',
				'version' => isset($presentation['presentation_version']) ? (int) $presentation['presentation_version'] : 0,
			);
			$twig = Twig::twig();
			$name = 'public_presentation_' . $context['presentation']['id']
				. '_v' . $context['presentation']['version'];
			$itemTemplate = $twig->createTemplate($itemMarkup, $name . '_item');
			$content = '';
			foreach (array_values($items) as $index => $item) {
				if (!is_array($item)) {
					throw new \UnexpectedValueException('Элемент представления должен быть массивом');
				}

				$itemContext = $context;
				$itemContext['position'] = $index + 1;
				$content .= $itemTemplate->render(array(
					'item' => $item,
					'items' => $items,
					'context' => $itemContext,
					'actions' => isset($item['actions']) && is_array($item['actions'])
						? $item['actions']
						: array(),
				));
			}

			$variables = array(
				'items' => $items,
				'content' => $content,
				'context' => $context,
				'actions' => array(),
				'settings' => isset($presentation['settings']) && is_array($presentation['settings'])
					? $presentation['settings']
					: array(),
			);
			$wrapperMarkup = isset($presentation['published_wrapper_markup'])
				? trim((string) $presentation['published_wrapper_markup'])
				: '';
			$html = $wrapperMarkup !== ''
				? $twig->createTemplate($wrapperMarkup, $name . '_wrapper')->render($variables)
				: $content;
			if (!$items) {
				$emptyMarkup = isset($presentation['published_empty_markup'])
					? trim((string) $presentation['published_empty_markup'])
					: '';
				$html = $emptyMarkup !== ''
					? $twig->createTemplate($emptyMarkup, $name . '_empty')->render($variables)
					: '';
			}

			self::requireStyle($presentation);
			return $html;
		}

		public static function injectAssets(array $matches = array(), array $context = array())
		{
			return self::stylesMarkup() . '</head>';
		}

		/** Returns styles required by an AJAX fragment rendered in the current request. */
		public static function fragmentStyles()
		{
			return self::stylesMarkup();
		}

		public static function injectLifecycleAssets($event)
		{
			if (!$event instanceof LifecycleEvent || !self::$requiredStyles) { return $event; }
			$html = (string) $event->result();
			$styles = self::stylesMarkup();
			if ($styles === '' || stripos($html, '</head>') === false) { return $event; }
			return $event->setResult(preg_replace('#</head>#i', $styles . '</head>', $html, 1));
		}

		protected static function stylesMarkup()
		{
			$html = '';
			foreach (self::$requiredStyles as $key => $css) {
				$html .= '<style data-public-presentation="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">'
					. preg_replace('#</style#i', '<\\/style', (string) $css)
					. '</style>';
			}

			return $html;
		}

		public static function resetAssets()
		{
			self::$requiredStyles = array();
		}

		protected static function requireStyle(array $presentation)
		{
			$css = isset($presentation['published_css']) ? trim((string) $presentation['published_css']) : '';
			if ($css === '') { return; }
			$id = isset($presentation['presentation_id'])
				? (int) $presentation['presentation_id']
				: (isset($presentation['id']) ? (int) $presentation['id'] : 0);
			$version = isset($presentation['presentation_version'])
				? (int) $presentation['presentation_version']
				: (isset($presentation['version']) ? (int) $presentation['version'] : 0);
			self::$requiredStyles[$id . ':' . $version] = $css;
		}
	}
