<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/LifecycleEvent.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Mutable, serializable context shared by framework lifecycle hooks. */
	class LifecycleEvent
	{
		protected $subject;
		protected $operation;
		protected $identifier;
		protected $source;
		protected $data;
		protected $result;
		protected $meta;
		protected $cancelled = false;
		protected $message = '';

		public function __construct($subject, $operation, $identifier = null, array $data = array(), $result = null, array $meta = array(), $source = 'runtime')
		{
			$this->subject = (string) $subject;
			$this->operation = (string) $operation;
			$this->identifier = $identifier;
			$this->data = $data;
			$this->result = $result;
			$this->meta = $meta;
			$this->source = (string) $source;
		}

		public function subject() { return $this->subject; }
		public function operation() { return $this->operation; }
		public function identifier() { return $this->identifier; }
		public function source() { return $this->source; }
		public function data() { return $this->data; }
		public function result() { return $this->result; }
		public function metadata() { return $this->meta; }

		public function value($key, $default = null)
		{
			return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
		}

		public function setValue($key, $value)
		{
			$this->data[(string) $key] = $value;
			return $this;
		}

		public function replaceData(array $data)
		{
			$this->data = $data;
			return $this;
		}

		public function setResult($result)
		{
			$this->result = $result;
			return $this;
		}

		public function meta($key, $default = null)
		{
			return array_key_exists($key, $this->meta) ? $this->meta[$key] : $default;
		}

		public function setMeta($key, $value)
		{
			$this->meta[(string) $key] = $value;
			return $this;
		}

		public function cancel($message = '')
		{
			$this->cancelled = true;
			$this->message = (string) $message;
			return $this;
		}

		public function cancelled() { return $this->cancelled; }
		public function message() { return $this->message; }

		public function debugData()
		{
			return array(
				'subject' => $this->subject,
				'operation' => $this->operation,
				'identifier' => $this->identifier,
				'source' => $this->source,
				'data' => $this->data,
				'result_type' => is_object($this->result) ? get_class($this->result) : gettype($this->result),
				'meta' => $this->meta,
				'cancelled' => $this->cancelled,
				'message' => $this->message,
			);
		}
	}
