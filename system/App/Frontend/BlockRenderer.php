<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/BlockRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicSysblockRegistry;
	use App\Common\Lifecycle;
	use App\Common\AdminLocation;
	use App\Frontend\Debug\PublicLayoutInspector;
	use App\Frontend\Media\ThumbnailUrl;
	use App\Frontend\Media\WatermarkService;
	use App\Helpers\Debug;
	use App\Helpers\Json;
	use App\Helpers\Url;

	/** Renders unified blocks and their nested public tags. */
	class BlockRenderer
	{
		protected static $stack = array();
		protected $maxDepth = 20;

		public function visual($identifier)
		{
			$id = $this->identifier($identifier);
			if ($id === '') {
				return false;
			}

			$key = 'block:' . $id;
			if (!$this->enter($key)) {
				return '';
			}

			$rendering = Lifecycle::event('content.block.rendering', 'block', 'rendering', $id, array(
				'document' => PublicPageContext::document(),
			), null, array(), 'block_renderer');
			if ($rendering->cancelled()) {
				$this->leave($key);
				return $this->inspect((string) $rendering->result(), $id, 'block');
			}

			Debug::startTime('BLOCK_' . $id);
			$resolvedId = $id;
			if (is_numeric($id) && PublicSysblockRegistry::metadata('block-' . $id)) {
				$resolvedId = 'block-' . $id;
			}

			$output = $this->system($resolvedId, null, 'block');
			PublicProfiler::record(array('BLOCKS', $id), Debug::endTime('BLOCK_' . $id));
			$this->leave($key);
			$rendered = Lifecycle::event('content.block.rendered', 'block', 'rendered', $id, array(
				'document_id' => PublicPageContext::documentId(),
			), $output, array(), 'block_renderer');
			return (string) $rendered->result();
		}

		public function system($identifier, $rawParams = null, $source = 'sysblock')
		{
			$id = $this->identifier($identifier);
			if ($id === '') {
				return false;
			}

			$params = $this->params($rawParams);
			$key = 'sysblock:' . $id . ':' . md5(serialize($params));
			if (!$this->enter($key)) {
				return '';
			}

			$rendering = Lifecycle::event('content.sysblock.rendering', 'sysblock', 'rendering', $id, array(
				'params' => $params,
				'document' => PublicPageContext::document(),
			), null, array(), 'block_renderer');
			if ($rendering->cancelled()) {
				$this->leave($key);
				return $this->inspect((string) $rendering->result(), $id, $source);
			}

			Debug::startTime('SYSBLOCK_' . $id);
			$resolved = PublicSysblockRegistry::render($id, $params, array(
				'core' => null,
				'document' => PublicPageContext::document(),
			));
			if (!empty($resolved['final'])) {
				$this->leave($key);
				$rendered = Lifecycle::event('content.sysblock.rendered', 'sysblock', 'rendered', $id, array(
					'params' => $params,
					'document_id' => PublicPageContext::documentId(),
				), isset($resolved['output']) ? (string) $resolved['output'] : '', array('final' => true), 'block_renderer');
				return $this->inspect((string) $rendered->result(), $id, $source);
			}

			$output = isset($resolved['output']) ? (string) $resolved['output'] : '';
			$output = $this->tags($output);
			$output = preg_replace_callback(
				'/\[(?:sys:param|sysparam):([A-Za-z0-9-+_]+)\]/',
				function ($match) use ($params) {
					if (!array_key_exists($match[1], $params)) {
						return '';
					}

					return is_scalar($params[$match[1]]) ? (string) $params[$match[1]] : Json::encode($params[$match[1]], JSON_UNESCAPED_UNICODE);
				},
				$output
			);
			if (!empty($resolved['evaluate'])) {
				$output = \App\Common\StoredPhpRuntime::render($output, get_defined_vars());
			}

			PublicProfiler::record(array('SYSBLOCK', $id), Debug::endTime('SYSBLOCK_' . $id));
			$this->leave($key);
			$rendered = Lifecycle::event('content.sysblock.rendered', 'sysblock', 'rendered', $id, array(
				'params' => $params,
				'document_id' => PublicPageContext::documentId(),
			), $output, array('final' => false), 'block_renderer');
			return $this->inspect((string) $rendered->result(), $id, $source);
		}

		protected function inspect($output, $identifier, $source)
		{
			if (!PublicLayoutInspector::enabled()) {
				return (string) $output;
			}

			$row = PublicSysblockRegistry::metadata($identifier);
			$id = is_array($row) && isset($row['id']) ? (int) $row['id'] : 0;
			$alias = is_array($row) && isset($row['sysblock_alias']) ? (string) $row['sysblock_alias'] : (string) $identifier;
			$title = is_array($row) && isset($row['sysblock_name']) ? (string) $row['sysblock_name'] : 'Блок ' . $alias;
			$tagName = $source === 'block' ? 'block' : 'sysblock';

			return PublicLayoutInspector::annotate($output, 'block', array(
				'id' => $id > 0 ? $id : $identifier,
				'title' => $title,
				'alias' => $alias,
				'tag' => '[tag:' . $tagName . ':' . $identifier . ']',
				'edit_url' => $id > 0 ? AdminLocation::url('blocks?edit=' . $id) : AdminLocation::url('blocks'),
			));
		}

		protected function tags($output)
		{
			$documentId = PublicPageContext::documentId();
			$output = str_replace(
				array('[tag:mediapath]', '[tag:path]', '[tag:docid]', '[tag:home]'),
				array(
					ABS_PATH . 'templates/' . (defined('THEME_FOLDER') ? THEME_FOLDER : DEFAULT_THEME_FOLDER) . '/',
					ABS_PATH,
					$documentId,
					Url::home(),
				),
				(string) $output
			);
			if (strpos($output, '[tag:breadcrumb]') !== false) {
				$output = str_replace('[tag:breadcrumb]', (new BreadcrumbRenderer())->render(), $output);
			}

			$requestRenderer = new RequestRenderer();
			$output = preg_replace_callback(
				'/\[tag:request:([A-Za-z0-9-_]{1,20}+)\]/',
				function ($match) use ($requestRenderer) {
					return $requestRenderer->render($match);
				},
				$output
			);

			if ($documentId > 0) {
				$fieldRenderer = new DocumentFieldRenderer();
				$output = preg_replace_callback(
					'/\[tag:fld:([a-zA-Z0-9-_]+)\]\[([0-9]+)]\[([0-9]+)]/',
					function ($match) use ($fieldRenderer, $documentId) {
						return $fieldRenderer->element($match[1], $match[2], $match[3], $documentId);
					},
					$output
				);
				$output = preg_replace_callback(
					'/\[tag:fld:([a-zA-Z0-9-_]+)(|[:(\d)])+?\]/',
					function ($match) use ($fieldRenderer, $documentId) {
						return $fieldRenderer->render($match, $documentId);
					},
					$output
				);
				$watermarks = new WatermarkService();
				$output = preg_replace_callback(
					'/\[tag:watermark:(.+?):([a-zA-Z]+):([0-9]+)\]/',
					array($watermarks, 'fromTag'),
					$output
				);
				$output = preg_replace_callback(
					'/\[tag:([rcfts]\d+x\d+r*):(.*?)]/',
					array(ThumbnailUrl::class, 'fromTag'),
					$output
				);
			}

			$output = preg_replace_callback(
				'/\[tag:block:([A-Za-z0-9-_]{1,20}+)\]/',
				function ($match) {
					return $this->visual($match[1]);
				},
				$output
			);
			return preg_replace_callback(
				'/\[tag:sysblock:([A-Za-z0-9-_]{1,20}+)(|:\{(.*?)\})\]/',
				function ($match) {
					return $this->system($match[1], isset($match[2]) ? $match[2] : null);
				},
				$output
			);
		}

		protected function identifier($value)
		{
			if (is_array($value)) {
				$value = isset($value[1]) ? $value[1] : '';
			}

			$value = trim((string) $value);
			return preg_match('/^[A-Za-z0-9_-]{1,20}$/', $value) ? $value : '';
		}

		protected function params($raw)
		{
			if (is_array($raw)) {
				return $raw;
			}

			$raw = trim((string) $raw, ':');
			if ($raw === '') {
				return array();
			}

			return Json::toArray($raw);
		}

		protected function enter($key)
		{
			if (count(self::$stack) >= $this->maxDepth || isset(self::$stack[$key])) {
				error_log('Public block recursion stopped: ' . $key);
				return false;
			}

			self::$stack[$key] = true;
			return true;
		}

		protected function leave($key)
		{
			unset(self::$stack[$key]);
		}
	}
