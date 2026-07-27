<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/TrafficAttribution.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;

	class TrafficAttribution
	{
		protected $parameters = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content');
		protected $values = array();

		public function capture()
		{
			$current = $this->currentParameters();
			$this->values['utm_source'] = isset($current['utm_source']) ? $current['utm_source'] : $this->cookie('utm_source');
			if (!$current) {
				$this->values['utm_history'] = $this->cookie('utm_history');
				$this->values['utm_last'] = $this->cookie('utm_last');
				return;
			}

			$serialized = $this->serialize($current);
			$history = $this->cookie('utm_history');
			if ($history === '') {
				$history = $serialized;
				$this->setCookie('utm_history', $history, 15552000);
			} elseif ($serialized !== $history) {
				$this->setCookie('utm_last', $serialized, 15552000);
			}

			if (isset($current['utm_source'])) {
				$this->setCookie('utm_source', $current['utm_source'], 0);
			}

			$this->values['utm_history'] = $history;
			$this->values['utm_last'] = $serialized !== $history ? $serialized : $this->cookie('utm_last');
			$this->values['utm_source'] = isset($current['utm_source']) ? $current['utm_source'] : '';
		}

		public function getValue($name = 'utm_history')
		{
			if (!in_array((string) $name, array('utm_history', 'utm_last', 'utm_source'), true)) {
				$name = 'utm_history';
			}

			return isset($this->values[$name]) ? (string) $this->values[$name] : $this->cookie($name);
		}

		public function get_value($name = 'utm_history')
		{
			return $this->getValue($name);
		}

		/** Stable entry point for PHP stored in legacy templates and field values. */
		public static function storedValue($name = 'utm_history')
		{
			$attribution = new self();
			return $attribution->getValue($name);
		}

		protected function currentParameters()
		{
			$out = array();
			foreach ($this->parameters as $name) {
				$value = trim(strip_tags(Request::getStr($name, '')));
				if ($value === '') { continue; }
				$out[$name] = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $value), 0, 255);
			}

			return $out;
		}

		public static function orderData()
		{
			$out = array();
			foreach (array('utm_history', 'utm_last', 'utm_source') as $name) {
				$out[$name] = isset($_COOKIE[$name])
					? mb_substr((string) $_COOKIE[$name], 0, 2048)
					: '';
			}

			return $out;
		}

		public static function encodedOrderData()
		{
			return base64_encode(serialize(self::orderData()));
		}

		protected function serialize(array $values)
		{
			$parts = array();
			foreach ($this->parameters as $name) {
				$parts[] = $name . '=' . (isset($values[$name]) ? $values[$name] : 'none');
			}

			return implode('; ', $parts) . '; ';
		}

		protected function cookie($name)
		{
			return isset($_COOKIE[$name]) ? mb_substr((string) $_COOKIE[$name], 0, 2048) : '';
		}

		protected function setCookie($name, $value, $ttl)
		{
			$secure = Request::isHttps();
			$expires = $ttl > 0 ? time() + (int) $ttl : 0;
			setcookie($name, $value, array(
				'expires' => $expires,
				'path' => '/',
				'secure' => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			));
			$_COOKIE[$name] = $value;
		}
	}
