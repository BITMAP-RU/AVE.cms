<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldValidationException.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use RuntimeException;

	/** Ошибки валидации значений полей: name => сообщение. */
	class FieldValidationException extends RuntimeException
	{
		/** @var array */
		protected $errors;

		public function __construct(array $errors, $message = 'Проверьте заполнение полей')
		{
			parent::__construct($message);
			$this->errors = $errors;
		}

		public function errors()
		{
			return $this->errors;
		}
	}
