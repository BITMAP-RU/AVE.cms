<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentUrlParser.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Parses legacy-friendly public document URLs without mutating globals. */
	class DocumentUrlParser
	{
		public function parse($requestUri, $basePath, $suffix)
		{
			$uri = (string) $requestUri;
			$basePath = (string) $basePath;
			$suffix = (string) $suffix;

			if (strpos($uri, $basePath . '?') === 0) {
				$uri = '';
			} elseif (substr($uri, 0, strlen($basePath . 'index.php')) !== $basePath . 'index.php'
				&& strpos($uri, '?') !== false) {
				$uri = substr($uri, 0, strpos($uri, '?'));
			}

			$uri = rawurldecode($uri);
			if ($basePath !== '' && strpos($uri, $basePath) === 0) {
				$uri = mb_substr($uri, mb_strlen($basePath));
			}

			$checkUrl = $uri;
			if ($suffix !== '' && mb_substr($uri, -mb_strlen($suffix)) === $suffix) {
				$uri = mb_substr($uri, 0, -mb_strlen($suffix));
			}

			$segments = explode('/', $uri);
			$print = false;
			$pagination = array();
			$remaining = array();

			foreach ($segments as $segment) {
				if ($segment === 'index') {
					continue;
				}

				if (strcasecmp($segment, 'print') === 0) {
					$print = true;
					continue;
				}

				if (preg_match('/^(page|apage|artpage)-(\d+)$/i', $segment, $matches)) {
					$pagination[strtolower($matches[1])] = (int) $matches[2];
					continue;
				}

				$remaining[] = $segment;
			}

			$alias = implode('/', $remaining);
			$tag = null;
			$fakeUrl = false;
			if (preg_match('#^tags(?:/(.*))?$#is', $alias, $matches) && !empty($matches[1])) {
				$tagSegments = explode('/', trim($matches[1], '/'));
				$tag = urldecode((string) array_shift($tagSegments));
				if ($tag !== '') {
					$alias = 'tags';
					$fakeUrl = true;
				}
			}

			return array(
				'alias' => $alias,
				'check_url' => $checkUrl,
				'print' => $print,
				'pagination' => $pagination,
				'tag' => $tag,
				'fake_url' => $fakeUrl,
			);
		}
	}
