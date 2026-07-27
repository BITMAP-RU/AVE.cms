<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ThemePageShellRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\PublicModuleRuntime;
	use App\Common\Twig;
	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Frontend\Auth\Feature as AuthFeature;
	use App\Helpers\Request;

	/** Applies an optional filesystem-owned outer shell to a public page. */
	class ThemePageShellRenderer
	{
		public function render($content, $mainContent, $document)
		{
			if (!$this->supportsCurrentRequest()) { return (string) $content; }

			$theme = ThemeAssets::currentTheme();
			$manifest = ThemeAssets::manifest($theme);
			if (!isset($manifest['presentation_mode']) || $manifest['presentation_mode'] !== 'theme') {
				return (string) $content;
			}

			$template = ThemeAssets::twigTemplate(isset($manifest['page_shell']) ? $manifest['page_shell'] : '');
			if ($template === '') { return (string) $content; }

			try {
				ThemeAssets::registerViewOverrides($theme);
				$body = isset($manifest['page_shell_mode']) && $manifest['page_shell_mode'] === 'replace'
					? (string) $mainContent
					: (string) $content;
				$publicUser = Auth::publicUser();
				$systemUser = Auth::systemUser();
				$inheritRubricTemplates = !empty($manifest['inherit_rubric_templates']);
				$rubricHeader = $inheritRubricTemplates && is_object($document) && isset($document->rubric_header_template)
					? (string) $document->rubric_header_template
					: '';
				$rubricOpenGraph = $inheritRubricTemplates && is_object($document) && isset($document->rubric_og_template)
					? trim((string) $document->rubric_og_template)
					: '';
				if ($rubricOpenGraph !== '') { $rubricHeader = rtrim($rubricHeader) . "\n" . $rubricOpenGraph; }
				$html = Twig::twig()->render($template, array(
					'content' => $body,
					'main_content' => (string) $mainContent,
					'document' => $document,
					'page' => $this->documentData($document),
					'runtime_page' => PublicPageContext::hasDeferredPage(),
					'not_found' => PublicPageContext::isNotFound(),
					'site_name' => (string) PublicSettings::get('site_name', ''),
					'home_url' => defined('ABS_PATH') ? (string) ABS_PATH : '/',
					'auth_urls' => AuthFeature::urls(),
					'basket_urls' => $this->basketUrls(),
					'storefront' => ThemeSettings::publicValues($theme),
					'logged_in' => $publicUser !== null || $systemUser !== null,
					'current_user' => $publicUser ?: $systemUser,
					'rubric_header' => $rubricHeader,
					'rubric_footer' => $inheritRubricTemplates && is_object($document) && isset($document->rubric_footer_template)
						? (string) $document->rubric_footer_template
						: '',
				));

				return PublicModuleRuntime::parseTags($html, array(
					'document' => $document,
				), 'all');
			} catch (\Throwable $e) {
				error_log('Theme page shell [' . $theme . ']: ' . $e->getMessage());
				return (string) $content;
			}
		}

		protected function basketUrls()
		{
			$class = '\\App\\Frontend\\Basket\\Feature';
			return class_exists($class) && method_exists($class, 'urls') ? $class::urls() : array();
		}

		/** Provides stable, theme-friendly document data keyed by field alias. */
		protected function documentData($document)
		{
			$id = is_object($document) && isset($document->Id) ? (int) $document->Id : 0;
			$data = array(
				'id' => $id,
				'rubric_id' => is_object($document) && isset($document->rubric_id) ? (int) $document->rubric_id : 0,
				'title' => is_object($document) && isset($document->document_title) ? (string) $document->document_title : '',
				'breadcrumb_title' => is_object($document) && isset($document->document_breadcrumb_title) ? (string) $document->document_breadcrumb_title : '',
				'alias' => is_object($document) && isset($document->document_alias) ? trim((string) $document->document_alias, '/') : '',
				'excerpt' => is_object($document) && isset($document->document_excerpt) ? (string) $document->document_excerpt : '',
				'published_at' => is_object($document) && isset($document->document_published) ? (int) $document->document_published : 0,
				'fields' => array(),
				'field_types' => array(),
			);
			if ($id <= 0) { return $data; }

			try {
				$snapshot = (new DocumentSnapshotRepository())->find($id);
				if (!is_array($snapshot)) { return $data; }
				if (!empty($snapshot['document']) && is_array($snapshot['document'])) {
					$source = $snapshot['document'];
					foreach (array('title', 'alias', 'excerpt', 'published_at') as $key) {
						if (array_key_exists($key, $source)) { $data[$key] = $source[$key]; }
					}
				}

				foreach (isset($snapshot['fields']) && is_array($snapshot['fields']) ? $snapshot['fields'] : array() as $field) {
					$alias = isset($field['alias']) ? trim((string) $field['alias']) : '';
					if ($alias === '') { continue; }
					$data['fields'][$alias] = isset($field['value']) ? $field['value'] : '';
					$data['field_types'][$alias] = isset($field['type']) ? (string) $field['type'] : '';
				}
			} catch (\Throwable $e) {
				error_log('Theme document data #' . $id . ': ' . $e->getMessage());
			}

			return $data;
		}

		protected function supportsCurrentRequest()
		{
			if (defined('ONLYCONTENT') || Request::isAjax()) { return false; }
			if (isset($_REQUEST['pop']) && (int) $_REQUEST['pop'] === 1) { return false; }
			return !isset($_REQUEST['sysblock']) && !isset($_REQUEST['request']);
		}
	}
