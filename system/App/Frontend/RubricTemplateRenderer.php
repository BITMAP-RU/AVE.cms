<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RubricTemplateRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Frontend\Media\ThumbnailUrl;
	use App\Frontend\Media\WatermarkService;
	use App\Helpers\Debug;
	use App\Helpers\Locales;

	/** Compiles a rubric template against the current document field context. */
	class RubricTemplateRenderer
	{
		public function render($template, $document)
		{
			$documents = new DocumentRepository();
			Debug::startTime('MAINCONTENT');
			$content = (string) $template;
			$content = $this->renderPaymentProgramConditions($content, $document);
			$content = preg_replace_callback('/\[tag:payment_program_code:([a-z][a-z0-9_-]{1,63})\]/i', function ($match) use ($document) {
				return htmlspecialchars(\App\Content\PaymentPrograms::productInformationCode((int) $document->Id, $match[1]), ENT_QUOTES, 'UTF-8');
			}, $content);

			if (defined('USE_GET_FIELDS') && USE_GET_FIELDS) {
				$content = preg_replace("/\[tag:if_notempty:fld:([a-zA-Z0-9-_]+)\]/u", '<?php if((htmlspecialchars(\\App\\Frontend\\PublicFieldApi::raw(\'$1\', ' . (int) $document->Id . '), ENT_QUOTES)) != \'\') { ?>', $content);
				$content = preg_replace("/\[tag:if_empty:fld:([a-zA-Z0-9-_]+)\]/u", '<?php if((htmlspecialchars(\\App\\Frontend\\PublicFieldApi::raw(\'$1\', ' . (int) $document->Id . '), ENT_QUOTES)) == \'\') { ?>', $content);
			} else {
				$content = preg_replace("/\[tag:if_notempty:fld:([a-zA-Z0-9-_]+)\]/u", '<?php if((htmlspecialchars(\\App\\Frontend\\PublicFieldApi::render(\'$1\', ' . (int) $document->Id . '), ENT_QUOTES)) != \'\') { ?>', $content);
				$content = preg_replace("/\[tag:if_empty:fld:([a-zA-Z0-9-_]+)\]/u", '<?php if((htmlspecialchars(\\App\\Frontend\\PublicFieldApi::render(\'$1\', ' . (int) $document->Id . '), ENT_QUOTES)) == \'\') { ?>', $content);
			}

			$content = str_replace('[tag:if:else]', '<?php }else{ ?>', $content);
			$content = str_replace('[tag:/if]', '<?php } ?>', $content);
			$content = $this->renderFields($content, $document);
			$content = $this->renderFields($content, $document);
			$watermarks = new WatermarkService();
			$content = preg_replace_callback('/\[tag:watermark:(.+?):([a-zA-Z]+):([0-9]+)\]/', array($watermarks, 'fromTag'), $content);
			$content = preg_replace_callback('/\[tag:([rcfts]\d+x\d+r*):(.*?)]/', array(ThumbnailUrl::class, 'fromTag'), $content);
			$content = preg_replace_callback('/\[tag:doc:([a-zA-Z0-9-_]+)\]/u', function ($match) use ($document) {
				return isset($document->{$match[1]}) ? $document->{$match[1]} : null;
			}, $content);
			$content = preg_replace('/\[tag:langfile:[a-zA-Z0-9-_]+\]/u', '', $content);
			$content = preg_replace_callback('/\[tag:date:([a-zA-Z0-9-. \/]+)\]/', function ($match) use ($document) {
				return Locales::translateDate(date($match[1], $document->document_published));
			}, $content);
			$content = preg_replace_callback('/\[tag:get:([a-zA-Z0-9-_]+)(|:([0-9]+))+?\]/', function ($match) {
				return PublicFieldApi::raw($match[1], isset($match[3]) ? $match[3] : null);
			}, $content);
			$content = preg_replace_callback('/\[tag:docs:([0-9]+)(|:([a-zA-Z0-9-_]+))+?\]/', function ($match) use ($documents) {
				return $documents->data($match[1], isset($match[3]) ? $match[3] : '');
			}, $content);

			$content = str_replace('[tag:docdate]', Locales::translateDate(strftime(DATE_FORMAT, $document->document_published)), $content);
			$content = str_replace('[tag:doctime]', Locales::translateDate(strftime(TIME_FORMAT, $document->document_published)), $content);
			$content = str_replace('[tag:humandate]', Locales::humanDate($document->document_published), $content);
			$content = str_replace('[tag:docauthorid]', $document->document_author_id, $content);
			if (strpos($content, '[tag:docauthor]') !== false) {
				$content = str_replace(
					'[tag:docauthor]',
					\App\Common\Auth\PublicUserNames::byId($document->document_author_id),
					$content
				);
			}

			$content = preg_replace('/\[tag:watermark:\w*\]/', '', $content);
			$content = preg_replace('/\[tag:fld:\w*\]/', '', $content);
			$content = preg_replace('/\[tag:doc:\w*\]/', '', $content);
			$content = preg_replace('/\[tag:langfile:\w*\]/', '', $content);
			PublicProfiler::record(array('DOCUMENT', 'MAINCONTENT'), Debug::endTime('MAINCONTENT'));

			return $content;
		}

		protected function renderFields($content, $document)
		{
			$renderer = new DocumentFieldRenderer();
			$content = preg_replace_callback('/\[tag:fld:([a-zA-Z0-9-_]+)\]\[([0-9]+)]\[([0-9]+)]/', function ($match) use ($document, $renderer) {
				return $renderer->element($match[1], $match[2], $match[3], $document->Id);
			}, $content);

			return preg_replace_callback(
				'/\[tag:fld:([a-zA-Z0-9-_]+)(|[:(\d)])+?\]/',
				function ($match) use ($document, $renderer) {
					return $renderer->render($match, $document->Id);
				},
				$content
			);
		}

		protected function renderPaymentProgramConditions($content, $document)
		{
			return preg_replace_callback(
				'/\[tag:if_payment_program:([a-z][a-z0-9_-]{1,63})\](.*?)\[(?:tag:\/if_payment_program|\/tag:if_payment_program)\]/si',
				function ($match) use ($document) {
					$parts = explode('[tag:if:else]', $match[2], 2);
					$enabled = \App\Content\PaymentPrograms::productEnabled((int) $document->Id, $match[1]);
					return $enabled ? $parts[0] : (isset($parts[1]) ? $parts[1] : '');
				},
				$content
			);
		}
	}
