<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PageTemplateResolver.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Lifecycle;

	/** Selects the public page shell and extracts its theme declaration. */
	class PageTemplateResolver
	{
		public function resolve($document, array $options)
		{
			$templateId = isset($options['template_id']) ? (int) $options['template_id'] : 0;
			$rendering = Lifecycle::event('content.template.rendering', 'template', 'rendering', $templateId, array(
				'document' => $document,
				'options' => $options,
			), null, array(), 'page_template_resolver');
			if ($rendering->cancelled()) {
				return is_array($rendering->result()) ? $rendering->result() : array(
					'text' => (string) $rendering->result(),
					'theme' => defined('DEFAULT_THEME_FOLDER') ? DEFAULT_THEME_FOLDER : 'default',
					'used_document_template' => false,
				);
			}

			$document = $rendering->value('document', $document);
			$options = $rendering->value('options', $options);
			$onlyContent = !empty($options['only_content']) || !empty($options['pop']);
			$usedDocumentTemplate = false;

			if ($onlyContent) {
				$text = '[tag:maincontent]';
			} elseif (!empty($options['fetched'])) {
				$text = (string) $options['fetched'];
			} elseif (!empty($options['template'])) {
				$text = (string) $options['template'];
			} elseif ($document && !empty($document->template_text)) {
				$text = stripslashes((string) $document->template_text);
				$usedDocumentTemplate = true;
			} else {
				$repository = new PageTemplateRepository();
				$fallback = $repository->findText(isset($options['template_id']) ? $options['template_id'] : 0);
				$text = $fallback === null ? '' : stripslashes((string) $fallback);
			}

			$theme = defined('DEFAULT_THEME_FOLDER') ? DEFAULT_THEME_FOLDER : 'default';
			if (preg_match('/\[tag:theme:(\w+)]/', $text, $match)) {
				$theme = $match[1];
			}

			$text = preg_replace('/\[tag:theme:(.*?)]/', '', $text);
			$text = str_replace('[tag:language]', '', $text);

			$result = array(
				'text' => (string) $text,
				'theme' => (string) $theme,
				'used_document_template' => $usedDocumentTemplate,
			);
			$rendered = Lifecycle::event('content.template.rendered', 'template', 'rendered', $templateId, array(
				'document_id' => is_object($document) && isset($document->Id) ? (int) $document->Id : 0,
			), $result, array(), 'page_template_resolver');
			return is_array($rendered->result()) ? $rendered->result() : $result;
		}
	}
