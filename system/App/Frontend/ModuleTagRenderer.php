<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ModuleTagRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicModuleRuntime;
	use App\Helpers\Debug;

	/** Applies registered public module tags with legacy-compatible timing. */
	class ModuleTagRenderer
	{
		public function render($content, $core = null, $document = null, $scope = 'all')
		{
			Debug::startTime('MODULES_PARSE');
			$content = PublicModuleRuntime::parseTags((string) $content, array(
				'core' => $core,
				'document' => $document,
			), $scope);
			PublicProfiler::record(array('DOCUMENT', 'MODULES'), Debug::endTime('MODULES_PARSE'));
			return $content;
		}

		public function renderPrivate($content, $core = null, $document = null)
		{
			return $this->render($content, $core, $document, 'private');
		}
	}
