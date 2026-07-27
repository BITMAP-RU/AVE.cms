<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/RussianStemmer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Porter stemmer used by public search code stored in the database. */
	class RussianStemmer
	{
		private $cacheEnabled = true;
		private $cache = array();

		public function stemWord($word)
		{
			$word = mb_strtolower((string) $word, 'UTF-8');
			if ($word === '') {
				return '';
			}

			if ($this->cacheEnabled && isset($this->cache[$word])) {
				return $this->cache[$word];
			}

			$stem = $word;
			if (preg_match('/^(.*?[аеиоуыэюя])(.*)$/u', $word, $parts)) {
				$start = $parts[1];
				$rv = $parts[2];
				if ($rv !== '') {
					if (!$this->replace($rv, '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/u')) {
						$this->replace($rv, '/(с[яь])$/u');
						if ($this->replace($rv, '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|ему|ому|их|ых|ую|юю|ая|яя|ою|ею)$/u')) {
							$this->replace($rv, '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/u');
						} elseif (!$this->replace($rv, '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ен|ило|ыло|ено|ят|ует|уют|ит|ыт|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/u')) {
							$this->replace($rv, '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|иям|ям|ием|ем|ам|ом|о|у|ах|иях|ях|ы|ь|ию|ью|ю|ия|ья|я)$/u');
						}
					}

					$this->replace($rv, '/и$/u');
					if (preg_match('/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/u', $rv)) {
						$this->replace($rv, '/ость?$/u');
					}

					if (!$this->replace($rv, '/ь$/u')) {
						$this->replace($rv, '/ейше?/u');
						$this->replace($rv, '/нн$/u', 'н');
					}

					$stem = $start . $rv;
				}
			}

			if ($this->cacheEnabled) {
				$this->cache[$word] = $stem;
			}

			return $stem;
		}

		public function clearCache()
		{
			$this->cache = array();
		}

		private function replace(&$value, $pattern, $replacement = '')
		{
			$original = $value;
			$value = preg_replace($pattern, $replacement, $value);
			return $original !== $value;
		}
	}
