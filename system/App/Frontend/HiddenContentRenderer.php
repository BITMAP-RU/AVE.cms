<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/HiddenContentRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Resolves [tag:hide:groups] blocks for the current public user group. */
	class HiddenContentRenderer
	{
		public function render($content, $userGroupId = null)
		{
			$groupId = $userGroupId === null ? (int) UGROUP : (int) $userGroupId;
			return preg_replace_callback(
				'/\[tag:hide:([0-9]+(?:,[0-9]+)*)(?::([^\]]*))?\](.*?)\[\/tag:hide\]/s',
				function ($match) use ($groupId) {
					$groups = array_map('intval', explode(',', $match[1]));
					if (!in_array($groupId, $groups, true)) {
						return $match[3];
					}

					$replacement = isset($match[2]) ? (string) $match[2] : '';
					return $replacement !== ''
						? $replacement
						: trim((string) PublicSettings::get('hidden_text', ''));
				},
				(string) $content
			);
		}
	}
