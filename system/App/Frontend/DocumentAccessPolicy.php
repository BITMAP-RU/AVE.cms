<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentAccessPolicy.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Public document visibility rules, independent from response side effects. */
	class DocumentAccessPolicy
	{
		const AVAILABLE = 'available';
		const MISSING = 'missing';
		const DELETED = 'deleted';
		const DELAYED = 'delayed';
		const TECHNICAL = 'technical';

		public function status($document, $notFoundDocumentId, $usePublicationWindow = null, $now = null)
		{
			if (!$document || !isset($document->Id)) {
				return self::MISSING;
			}

			if ((int) $document->Id === (int) $notFoundDocumentId) {
				return self::AVAILABLE;
			}

			if (isset($document->document_deleted) && (int) $document->document_deleted === 1) {
				return self::DELETED;
			}

			if (\App\Content\Documents\DocumentVisibility::isTechnical($document)) {
				return self::TECHNICAL;
			}

			$usePublicationWindow = $usePublicationWindow === null
				? (bool) PublicSettings::get('use_doctime')
				: (bool) $usePublicationWindow;
			if ($usePublicationWindow) {
				$now = $now === null ? time() : (int) $now;
				$published = isset($document->document_published) ? (int) $document->document_published : 0;
				$expires = isset($document->document_expire) ? (int) $document->document_expire : 0;
				if (($published > 0 && $published > $now) || ($expires > 0 && $expires < $now)) {
					return self::DELAYED;
				}
			}

			return self::AVAILABLE;
		}
	}
