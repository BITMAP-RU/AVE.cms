<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Debug/PublicLayoutInspector.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Debug;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminLocation;
	use App\Common\ModuleTagRegistry;

	/** Collects debug-only render boundaries without adding layout wrappers. */
	class PublicLayoutInspector
	{
		const MARKER_PATTERN = "/\x1EAVE_INSPECT:([BE]):([0-9]+)\x1F/";

		protected static $enabled = false;
		protected static $entries = array();
		protected static $nextId = 1;

		public static function boot()
		{
			self::$enabled = true;
			self::$entries = array();
			self::$nextId = 1;

			ModuleTagRegistry::setRenderObserver(function ($output, array $context) {
				$inspect = isset($context['inspect']) && is_array($context['inspect']) ? $context['inspect'] : array();
				$module = isset($inspect['module']) ? (string) $inspect['module'] : (isset($context['code']) ? (string) $context['code'] : '');
				$editUrl = isset($inspect['edit_url']) && (string) $inspect['edit_url'] !== ''
					? AdminLocation::url((string) $inspect['edit_url'])
					: '';
				return self::annotate($output, 'module', array(
					'title' => isset($inspect['title']) ? (string) $inspect['title'] : ($module !== '' ? $module : 'Модуль'),
					'module' => $module,
					'tag' => isset($context['tag']) ? (string) $context['tag'] : '',
					'edit_url' => $editUrl,
				));
			});
		}

		public static function enabled()
		{
			return self::$enabled;
		}

		public static function annotate($html, $type, array $metadata = array())
		{
			$html = (string) $html;
			if (!self::$enabled) {
				return $html;
			}

			$type = strtolower(trim((string) $type));
			if (!in_array($type, array('document', 'block', 'request', 'navigation', 'module'), true)) {
				$type = 'component';
			}

			$token = self::$nextId++;
			self::$entries[$token] = array(
				'token' => $token,
				'type' => $type,
				'entity_id' => self::text(isset($metadata['id']) ? $metadata['id'] : '', 64),
				'title' => self::text(isset($metadata['title']) ? $metadata['title'] : '', 180),
				'alias' => self::text(isset($metadata['alias']) ? $metadata['alias'] : '', 100),
				'tag' => self::safeTag(isset($metadata['tag']) ? $metadata['tag'] : ''),
				'module' => self::text(isset($metadata['module']) ? $metadata['module'] : '', 64),
				'edit_url' => self::editUrl(isset($metadata['edit_url']) ? $metadata['edit_url'] : ''),
				'order' => 0,
				'depth' => 0,
				'has_boundary' => false,
			);

			return self::marker('B', $token) . $html . self::marker('E', $token);
		}

		public static function finalize($html, $decorate = true)
		{
			$html = (string) $html;
			if (strpos($html, "\x1EAVE_INSPECT:") === false) {
				return $html;
			}

			if (!$decorate || stripos($html, '<body') === false || stripos($html, '</body>') === false) {
				return preg_replace(self::MARKER_PATTERN, '', $html);
			}

			$markers = self::markers($html);
			$valid = self::validPairs($markers);
			self::applyOrder($markers, $valid);

			return preg_replace_callback(self::MARKER_PATTERN, function ($match) use ($valid) {
				$id = (int) $match[2];
				if (empty($valid[$id])) {
					return '';
				}

				return '<!--ave-inspect:' . ($match[1] === 'B' ? 'begin' : 'end') . ':' . $id . '-->';
			}, $html);
		}

		public static function entries()
		{
			$entries = array_values(self::$entries);
			usort($entries, function ($left, $right) {
				$leftOrder = !empty($left['order']) ? (int) $left['order'] : PHP_INT_MAX;
				$rightOrder = !empty($right['order']) ? (int) $right['order'] : PHP_INT_MAX;
				return $leftOrder === $rightOrder
					? (int) $left['token'] <=> (int) $right['token']
					: $leftOrder <=> $rightOrder;
			});

			return $entries;
		}

		public static function reset()
		{
			self::$enabled = false;
			self::$entries = array();
			self::$nextId = 1;
			ModuleTagRegistry::setRenderObserver(null);
		}

		protected static function markers($html)
		{
			preg_match_all(self::MARKER_PATTERN, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
			$state = array(
				'mode' => 'text',
				'quote' => '',
				'tag' => '',
				'raw' => '',
				'in_body' => false,
			);
			$result = array();
			$cursor = 0;

			foreach ($matches as $match) {
				$offset = (int) $match[0][1];
				self::advanceState(substr($html, $cursor, $offset - $cursor), $state);
				$result[] = array(
					'action' => $match[1][0],
					'id' => (int) $match[2][0],
					'safe' => $state['mode'] === 'text' && $state['raw'] === '' && $state['in_body'],
				);
				$cursor = $offset + strlen($match[0][0]);
			}

			return $result;
		}

		protected static function validPairs(array $markers)
		{
			$stack = array();
			$valid = array();
			foreach ($markers as $marker) {
				$id = (int) $marker['id'];
				if ($marker['action'] === 'B') {
					$stack[] = $marker;
					continue;
				}

				$start = $stack ? array_pop($stack) : null;
				if (!$start || (int) $start['id'] !== $id) {
					$valid[$id] = false;
					continue;
				}

				$valid[$id] = !empty($start['safe']) && !empty($marker['safe']);
				if (isset(self::$entries[$id])) {
					self::$entries[$id]['has_boundary'] = $valid[$id];
				}
			}

			foreach ($stack as $marker) {
				$valid[(int) $marker['id']] = false;
			}

			return $valid;
		}

		protected static function applyOrder(array $markers, array $valid)
		{
			$stack = array();
			$order = 0;
			foreach ($markers as $marker) {
				$id = (int) $marker['id'];
				if (empty($valid[$id])) {
					continue;
				}

				if ($marker['action'] === 'B') {
					$order++;
					if (isset(self::$entries[$id]) && empty(self::$entries[$id]['order'])) {
						self::$entries[$id]['order'] = $order;
						self::$entries[$id]['depth'] = count($stack);
					}

					$stack[] = $id;
					continue;
				}

				if ($stack && end($stack) === $id) {
					array_pop($stack);
				}
			}
		}

		protected static function advanceState($segment, array &$state)
		{
			$length = strlen($segment);
			for ($index = 0; $index < $length; $index++) {
				$char = $segment[$index];
				if ($state['mode'] === 'comment') {
					if (substr($segment, $index, 3) === '-->') {
						$state['mode'] = 'text';
						$index += 2;
					}

					continue;
				}

				if ($state['mode'] === 'raw') {
					if ($char === '<' && preg_match('/\A<\s*\/\s*' . preg_quote($state['raw'], '/') . '\b/i', substr($segment, $index))) {
						$state['mode'] = 'tag';
						$state['tag'] = '<';
					}

					continue;
				}

				if ($state['mode'] === 'tag') {
					$state['tag'] .= $char;
					if ($state['quote'] !== '') {
						if ($char === $state['quote']) {
							$state['quote'] = '';
						}

						continue;
					}

					if ($char === '"' || $char === "'") {
						$state['quote'] = $char;
						continue;
					}

					if ($char === '>') {
						self::closeTag($state);
					}

					continue;
				}

				if ($char !== '<') {
					continue;
				}

				if (substr($segment, $index, 4) === '<!--') {
					$state['mode'] = 'comment';
					$index += 3;
					continue;
				}

				$state['mode'] = 'tag';
				$state['tag'] = '<';
				$state['quote'] = '';
			}
		}

		protected static function closeTag(array &$state)
		{
			$tag = $state['tag'];
			$state['tag'] = '';
			$state['quote'] = '';
			$state['mode'] = 'text';
			if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9:-]+)/', $tag, $match)) {
				return;
			}

			$closing = $match[1] === '/';
			$name = strtolower($match[2]);
			if ($name === 'body') {
				$state['in_body'] = !$closing;
			}

			if ($closing && $state['raw'] === $name) {
				$state['raw'] = '';
				return;
			}

			if (!$closing && in_array($name, array('script', 'style', 'textarea', 'title'), true)
				&& substr(rtrim($tag), -2) !== '/>') {
				$state['raw'] = $name;
				$state['mode'] = 'raw';
			}
		}

		protected static function marker($action, $token)
		{
			return "\x1EAVE_INSPECT:" . $action . ':' . (int) $token . "\x1F";
		}

		protected static function safeTag($value)
		{
			$value = self::text($value, 180);
			return preg_replace(
				'/((?:token|secret|password|api[_-]?key)\s*[:=]\s*)([^\s,}\]]+)/iu',
				'$1***',
				$value
			);
		}

		protected static function editUrl($value)
		{
			$value = trim((string) $value);
			return $value !== '' && $value[0] === '/' && strpos($value, '//') !== 0
				? self::text($value, 500)
				: '';
		}

		protected static function text($value, $limit)
		{
			$value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $value)));
			return function_exists('mb_substr')
				? mb_substr($value, 0, (int) $limit, 'UTF-8')
				: substr($value, 0, (int) $limit);
		}
	}
