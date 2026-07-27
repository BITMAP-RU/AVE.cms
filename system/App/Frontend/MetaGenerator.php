<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/MetaGenerator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Builds fallback page metadata from rendered document content. */
	class MetaGenerator
	{
		protected $keywordCount;

		public function __construct($keywordCount = 10)
		{
			$this->keywordCount = max(1, (int) $keywordCount);
		}

		public function generate($content)
		{
			$text = str_replace(array(chr(9), chr(10), chr(13), '&nbsp;', '<br />'), ' ', (string) $content);
			$text = strip_tags($text);
			$text = preg_replace('/ {2,}/', ' ', $text);
			$text = preg_replace('/&(.+?);/', '', $text);
			$text = preg_replace('/\[tag:(.+?)\]|\[mod_(.+?)\]/', '', $text);

			$fastQuotes = array("\x22", "\x60", "\t", "\n", "\r", '"', '\\', '\r', '\n', '/', '{', '}', '[', ']');
			$quotes = array("\x22", "\x60", "\t", "\n", "\r", ',', '/', '¬', '#', ';', ':', '@', '~', '[', ']', '{', '}', '=', '+', ')', '(', '*', '^', '%', '$', '<', '>', '?', '!', '"');
			$text = str_replace($fastQuotes, '', $text);
			$text = trim(str_replace($quotes, ' ', $text));

			$words = array();
			foreach (explode(' ', $text) as $word) {
				if (mb_strlen($word) > 4 || (mb_strlen($word) > 1 && mb_strtoupper($word) === $word)) {
					$words[] = $word;
				}
			}

			$counts = array_count_values($words);
			arsort($counts);

			return array(
				'keywords' => implode(', ', array_slice(array_keys($counts), 0, $this->keywordCount)),
				'description' => rtrim(trim(mb_substr($text, 0, 220)), '.') . '.',
			);
		}
	}
