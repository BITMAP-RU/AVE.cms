<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/StoredPhpRuntime.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Executes administrator-managed PHP stored in templates, rubrics and blocks. */
	class StoredPhpRuntime
	{
		public static function removeExecutableMarkers($content)
		{
			return str_replace(array('<?php', '<?', '?>', '<script'), '', (string) $content);
		}

		public static function render($content, array $context = array())
		{
			ob_start();
			try {
				self::execute($content, $context);
				return (string) ob_get_clean();
			} catch (\Throwable $e) {
				ob_end_clean();
				throw $e;
			}
		}

		public static function evaluate($expression, array $context = array(), $owner = null)
		{
			$expression = self::normalizeDataPrefixes((string) $expression);
			unset($context['expression'], $context['context']);
			ob_start();
			try {
				$runner = function () use ($expression, $context) {
					if ($context) {
						extract($context, EXTR_SKIP);
					}

					eval($expression);
				};
				if (is_object($owner)) {
					$runner = \Closure::bind($runner, $owner, get_class($owner));
				}

				$runner();
				return (string) ob_get_clean();
			} catch (\Throwable $e) {
				ob_end_clean();
				throw $e;
			}
		}

		public static function evaluateMutable($expression, array &$context, $owner = null)
		{
			$expression = self::normalizeDataPrefixes((string) $expression);
			ob_start();
			try {
				$runner = function () use ($expression, &$context) {
					extract($context, EXTR_REFS | EXTR_SKIP);
					eval($expression);
				};
				if (is_object($owner)) {
					$runner = \Closure::bind($runner, $owner, get_class($owner));
				}

				$runner();
				return (string) ob_get_clean();
			} catch (\Throwable $e) {
				ob_end_clean();
				throw $e;
			}
		}

		public static function execute($content, array $context = array())
		{
			$content = self::normalizeDataPrefixes((string) $content);
			unset($context['content'], $context['context']);
			if ($context) {
				extract($context, EXTR_SKIP);
			}

			eval('?>' . $content);
		}

		public static function normalizeDataPrefixes($content)
		{
			$content = (string) $content;
			$legacyBaseConstant = 'BASE' . '_DIR';
			$content = preg_replace('/\b' . preg_quote($legacyBaseConstant, '/') . '\b/', 'BASEPATH', $content);
			$content = preg_replace(
				'/\s*require_once\s*\(\s*BASEPATH\s*\.\s*["\']\/class\/class\.porter\.php["\']\s*\)\s*;/i',
				'',
				$content
			);
			$content = preg_replace(
				'/\bnew\s+Lingua_Stem_Ru\s*\(\s*\)/i',
				'new \\App\\Helpers\\RussianStemmer()',
				$content
			);
			$content = preg_replace('/->stem_word\s*\(/i', '->stemWord(', $content);
			$content = preg_replace(
				array(
					'/(?<![A-Za-z0-9_:\\\\])get_current_document_id\s*\(\s*\)/',
					'/(?<![A-Za-z0-9_:\\\\])get_parent_document_id\s*\(\s*\)/',
					'/(?<![A-Za-z0-9_:\\\\])get_document\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])getDocument\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])document_pagination\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])prepare_url\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])translit_string\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])isRussian\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])transliterateen\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])get_settings\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])msort\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])_json\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])get_field\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])document_get_field\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])request_get_document_field\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])parse_request\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])request_parse\s*\(/',
					'/(?<![A-Za-z0-9_:\\\\])get_current_page\s*\(\s*\)/',
					'/(?<![A-Za-z0-9_:\\\\])make_thumbnail\s*\(/',
				),
				array(
					'\\App\\Frontend\\PublicDocumentApi::currentId()',
					'\\App\\Frontend\\PublicDocumentApi::parentId()',
					'\\App\\Frontend\\PublicDocumentApi::data(',
					'\\App\\Frontend\\PublicDocumentApi::find(',
					'\\App\\Frontend\\PublicDocumentApi::paginate(',
					'\\App\\Helpers\\Url::prepare(',
					'\\App\\Helpers\\Locales::transliterate(',
					'\\App\\Helpers\\SearchText::containsRussian(',
					'\\App\\Helpers\\SearchText::toRussian(',
					'\\App\\Frontend\\StoredTemplateApi::settings(',
					'\\App\\Frontend\\StoredTemplateApi::sort(',
					'\\App\\Frontend\\StoredTemplateApi::outputJson(',
					'\\App\\Frontend\\PublicFieldApi::raw(',
					'\\App\\Frontend\\PublicFieldApi::render(',
					'\\App\\Frontend\\PublicFieldApi::renderRequest(',
					'(new \\App\\Frontend\\RequestListRenderer())->render(',
					'(new \\App\\Frontend\\RequestRenderer())->render(',
					'\\App\\Frontend\\PaginationRenderer::currentPage()',
					'\\App\\Frontend\\Media\\ThumbnailUrl::make(',
				),
				$content
			);
			$content = str_replace('Registry::stored', '\\App\\Common\\Registry::exists', $content);
			$content = preg_replace(
				array(
					'/(?<![A-Za-z0-9_\\\\])Debug::/',
					'/(?<![A-Za-z0-9_\\\\])Registry::/',
					'/(?<![A-Za-z0-9_\\\\])Hooks::/',
				),
				array('\\App\\Helpers\\Debug::', '\\App\\Common\\Registry::', '\\App\\Helpers\\Hooks::'),
				$content
			);
			$content = str_replace(
				'$AVE_Core->curentdoc',
				'\\App\\Frontend\\PublicPageContext::document()',
				$content
			);
			$content = str_replace(
				array('$AVE_Utm->get_value(', '$AVE_Utm->getValue(', 'new UTMCookie()'),
				array(
					'\\App\\Common\\TrafficAttribution::storedValue(',
					'\\App\\Common\\TrafficAttribution::storedValue(',
					'new \\App\\Common\\TrafficAttribution()',
				),
				$content
			);
			$content = str_replace(
				array('$AVE_DB->Query', '$AVE_DB->EscStr', '->FetchAssocArray()', '->FetchRow()', '->GetCell()', '->getCell()'),
				array('DB::query', 'DB::safe', '->getAssoc()', '->getObject()', '->getValue()', '->getValue()'),
				$content
			);
			$content = preg_replace('/(?<![A-Za-z0-9_\\\\])DB::/', '\\DB::', $content);
			$maps = array(
				'CATALOG_PREFIX' => 'module_catalog',
				'BASKET_DATA_PREFIX' => 'module_basket',
				'CONTACTS_DATA_PREFIX' => 'module_contacts',
				'PUBLIC_USER_PREFIX' => '(?:users|user_groups|users_session|auth_tokens|user_profile_fields|user_profile_values)',
				'PUBLIC_SHELL_PREFIX' => '(?:settings|sessions)',
				'MODULE_DATA_PREFIX' => 'module(?:_[a-z0-9_]+)?',
			);
			foreach ($maps as $constant => $suffix) {
				$content = preg_replace(
					'/(?<![A-Z_])PREFIX(?=\s*\.\s*["\']_(?:' . $suffix . ')(?:["\']|_))/i',
					$constant,
					$content
				);
			}

			return preg_replace(
				'/(?<![A-Z_])PREFIX(?=\s*\.\s*["\']_[a-z0-9_]+)/i',
				'CONTENT_PREFIX',
				$content
			);
		}
	}
