<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestItemContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Immutable request metadata needed while rendering one list item. */
	class RequestItemContext
	{
		protected $requestId;
		protected $itemNumber;
		protected $cacheEnabled;
		protected $changedElementsAt;

		public function __construct($requestId = 0, $itemNumber = 0, $cacheEnabled = false, $changedElementsAt = 0)
		{
			$this->requestId = (int) $requestId;
			$this->itemNumber = (int) $itemNumber;
			$this->cacheEnabled = (bool) $cacheEnabled;
			$this->changedElementsAt = (int) $changedElementsAt;
		}

		public function forItem($itemNumber)
		{
			return new self($this->requestId, $itemNumber, $this->cacheEnabled, $this->changedElementsAt);
		}

		public function requestId()
		{
			return $this->requestId;
		}

		public function itemNumber()
		{
			return $this->itemNumber;
		}

		public function cacheEnabled()
		{
			return $this->cacheEnabled;
		}

		public function changedElementsAt()
		{
			return $this->changedElementsAt;
		}
	}
