<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldAdapter.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;
	use App\Content\Fields\FieldRegistry;

	class FieldAdapter
	{
		public static function editPreview(array $field)
		{
			$type = FieldTypes::one((string) $field['rubric_field_type']);
			if (!$type) {
				return self::result('error', 'Тип поля не зарегистрирован', '', array());
			}

			$plugin = FieldRegistry::get((string) $field['rubric_field_type']);
			if (!$plugin) {
				return self::result('error', 'Native-тип поля не зарегистрирован', '', array());
			}

			try {
				$context = new FieldContext((string) $field['rubric_field_default'], $field, 'edit');
				$html = $plugin->renderEdit($context);

				$html = is_string($html) ? trim($html) : '';
				if ($html === '') {
					return self::result('warning', 'Native-тип не вернул HTML для режима edit', '', array());
				}

				$normalized = self::normalizeHtml($html);
				$html = $normalized['html'];
				return self::result('ok', 'Native edit-render выполнен через FieldRegistry.', $html, array(), $normalized['notes']);
			} catch (\Throwable $e) {
				return self::result('error', $e->getMessage(), '', array());
			}
		}

		protected static function normalizeHtml($html)
		{
			$html = (string) $html;
			$notes = array();

			$scriptCount = preg_match_all('#<script\b[^>]*>.*?</script>#is', $html, $m);
			if ($scriptCount > 0) {
				$html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
				$notes[] = 'Preview: legacy script-теги отключены (' . (int) $scriptCount . ').';
			}

			$handlerCount = preg_match_all('#\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)#is', $html, $m);
			if ($handlerCount > 0) {
				$html = preg_replace('#\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)#is', '', $html);
				$notes[] = 'Preview: inline JS-обработчики удалены (' . (int) $handlerCount . ').';
			}

			$rewrites = 0;
			$html = preg_replace_callback('/\b(href|src|action)\s*=\s*([\'"])(.*?)\2/i', function ($m) use (&$rewrites) {
				$url = trim((string) $m[3]);
				$normalized = self::normalizeUrl($url);
				if ($normalized !== $url) {
					$rewrites++;
				}

				return $m[1] . '=' . $m[2] . htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8') . $m[2];
			}, $html);

			$rootAssets = preg_match_all('#\b(?:href|src|action)\s*=\s*([\'"])/(admin|class|fields|images|lib|modules|templates|tmp|uploads)/#i', $html, $m);
			if ($rewrites > 0) {
				$notes[] = 'Preview: legacy asset-пути приведены к корню сайта (' . (int) $rewrites . ').';
			} elseif ($rootAssets > 0) {
				$notes[] = 'Preview: legacy asset-пути используют корень сайта.';
			}

			return array('html' => $html, 'notes' => $notes);
		}

		protected static function normalizeUrl($url)
		{
			$url = (string) $url;
			$trimmed = trim($url);
			if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === '/') {
				return $url;
			}

			if (preg_match('#^(https?:)?//#i', $trimmed) || preg_match('#^(mailto|tel|data):#i', $trimmed)) {
				return $url;
			}

			if (preg_match('#^javascript:#i', $trimmed)) {
				return '#';
			}

			$path = preg_replace('#^\./#', '', $trimmed);
			if (preg_match('#^(admin|class|fields|images|lib|modules|templates|tmp|uploads)/#i', $path)) {
				return '/' . ltrim($path, '/');
			}

			return $url;
		}

		protected static function result($status, $message, $html, array $assets, array $notes = array())
		{
			return array(
				'status' => $status,
				'message' => $message,
				'html' => $html,
				'assets' => array_values($assets),
				'notes' => array_values(array_unique($notes)),
			);
		}
	}
