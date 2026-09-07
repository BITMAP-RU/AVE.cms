<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RequestFieldRenderer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Str;
	use App\Content\Fields\MediaFieldValue;
	use App\Content\Fields\PublicFieldRuntime;
	use App\Frontend\Media\ImageSourceExtractor;

	/** Renders one document field in a request item context. */
	class RequestFieldRenderer
	{
		protected $repository;

		public function __construct(DocumentFieldRepository $repository = null)
		{
			$this->repository = $repository ?: new DocumentFieldRepository();
		}

		public function render($field_id, $document_id, $maxlength = null, $rubric_id = 0)
		{
			if (! is_numeric($document_id) || $document_id < 1)
				return '';

			$document_fields = $this->repository->all($document_id);

			if (!isset($document_fields[$field_id]))
				return '';
			if (!is_array($document_fields[$field_id]))
				$field_id = (int) $document_fields[$field_id];

			if (empty($document_fields[$field_id]))
				return '';

			$field_value = trim($document_fields[$field_id]['field_value']);
			$field_type = (string) $document_fields[$field_id]['rubric_field_type'];

			// [img] requests the original source, not the rendered request markup.
			// Request cards intentionally suppress galleries, while metadata still
			// needs the first image from image_multi/image_mega fields.
			if ($maxlength === 'img' && in_array($field_type, array('image_single', 'image_multi', 'image_mega'), true)) {
				return MediaFieldValue::firstImageUrl($field_value);
			}

			$field_value = PublicFieldRuntime::renderRequest(
				$field_type,
				$field_value,
				$document_fields[$field_id]
			);

			if ($maxlength != '')
			{
				if ($maxlength == 'more' || $maxlength == 'esc'|| $maxlength == 'img' || $maxlength == 'strip')
				{
					if ($maxlength == 'more')
					{
						// ToDo - Вывести в настройки или в настройки самого запроса
						$teaser = explode('<a name="more"></a>', $field_value);
						$field_value = $teaser[0];
					}
					elseif ($maxlength == 'esc')
						{
							$field_value = addslashes($field_value);
						}
						elseif ($maxlength == 'img')
							{
								$field_value = ImageSourceExtractor::firstOriginal($field_value);
							}
							elseif ($maxlength == 'strip')
								{
									$field_value = str_replace(array("\r\n","\n","\r"), " ", $field_value);
									$field_value = strip_tags($field_value, REQUEST_STRIP_TAGS);
									$field_value = preg_replace('/  +/', ' ', $field_value);
									$field_value = trim($field_value);
								}
				}
				elseif (is_numeric($maxlength))
					{
						if ($maxlength < 0)
						{
							$field_value = str_replace(array("\r\n","\n","\r"), " ", $field_value);
							$field_value = strip_tags($field_value, REQUEST_STRIP_TAGS);
							$field_value = preg_replace('/  +/', ' ', $field_value);
							$field_value = trim($field_value);

							$maxlength = abs($maxlength);
						}

						// ToDo - сделать настройки окончаний = Уже есть в Доп настройках
						if ($maxlength != 0)
						{
								$field_value = Str::truncateSmart($field_value, $maxlength, REQUEST_ETC, REQUEST_BREAK_WORDS);
						}

					}
					else
						return false;
			}

			return $field_value;
		}

		public function value($fieldId, $documentId, $maxlength = 0)
		{
			if (! is_numeric($fieldId) || $fieldId < 1 || ! is_numeric($documentId) || $documentId < 1)
				return '';

			$documentFields = $this->repository->all($documentId);
			$fieldValue = isset($documentFields[$fieldId])
				? $documentFields[$fieldId]['field_value']
				: '';

			if (! empty($fieldValue))
			{
				$fieldValue = strip_tags($fieldValue, '<br /><strong><em><p><i>');
				$fieldValue = str_replace(
					'[tag:mediapath]',
					ABS_PATH . 'templates/' . (defined('THEME_FOLDER') ? THEME_FOLDER : DEFAULT_THEME_FOLDER) . '/',
					$fieldValue
				);
			}

			if (is_numeric($maxlength) && $maxlength != 0)
			{
				if ($maxlength < 0)
				{
					$fieldValue = str_replace(array("\r\n", "\n", "\r"), ' ', $fieldValue);
					$fieldValue = strip_tags($fieldValue, '<a>');
					$fieldValue = preg_replace('/  +/', ' ', $fieldValue);
					$maxlength = abs($maxlength);
				}

				$fieldValue = mb_substr($fieldValue, 0, $maxlength) . (strlen($fieldValue) > $maxlength
					? '... '
					: '');
			}

			return $fieldValue;
		}
	}
