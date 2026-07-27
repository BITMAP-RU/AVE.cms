<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentHookException.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class DocumentHookException extends \RuntimeException
	{
		protected $phase;
		protected $rubricId;
		protected $documentId;

		public function __construct($message, $phase, $rubricId, $documentId, \Throwable $previous = null)
		{
			parent::__construct((string) $message, 0, $previous);
			$this->phase = (string) $phase;
			$this->rubricId = (int) $rubricId;
			$this->documentId = (int) $documentId;
		}

		public static function fromThrowable(\Throwable $error, DocumentHookContext $hook)
		{
			$message = sprintf(
				'Ошибка кода рубрики «%s» (рубрика #%d%s): %s',
				$hook->phaseLabel(),
				$hook->rubricId(),
				$hook->documentId() > 0 ? ', документ #' . $hook->documentId() : '',
				$error->getMessage()
			);

			return new self($message, $hook->phase(), $hook->rubricId(), $hook->documentId(), $error);
		}

		public function phase()
		{
			return $this->phase;
		}

		public function rubricId()
		{
			return $this->rubricId;
		}

		public function documentId()
		{
			return $this->documentId;
		}
	}
