<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestResultContract.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	/** Normalized list of document properties exposed by a content view. */
	class RequestResultContract
	{
		const FORMAT = 'ave.request-result-contract';
		const VERSION = 1;

		protected static $systemFields = array(
			'id' => 'ID',
			'title' => 'Название',
			'alias' => 'Alias',
			'excerpt' => 'Тизер',
			'status' => 'Состояние',
			'published_at' => 'Дата публикации',
			'changed_at' => 'Дата изменения',
			'author_id' => 'Автор',
			'meta' => 'SEO и метаданные',
		);

		public static function systemOptions()
		{
			return self::$systemFields;
		}

		public static function defaults()
		{
			return array(
				'format' => self::FORMAT,
				'version' => self::VERSION,
				'system' => array('id', 'title', 'alias', 'excerpt', 'status', 'published_at'),
				'fields' => array(),
			);
		}

		public static function decode($value, array $availableFieldIds = array())
		{
			if (is_string($value)) {
				$value = Json::toArray($value);
			}

			if (!is_array($value) || !$value) {
				return self::defaults();
			}

			return self::normalize($value, $availableFieldIds);
		}

		public static function normalize(array $value, array $availableFieldIds = array())
		{
			$allowedSystem = array_keys(self::$systemFields);
			$system = self::uniqueStrings(isset($value['system']) ? $value['system'] : array(), $allowedSystem);
			$fields = self::uniqueIds(isset($value['fields']) ? $value['fields'] : array(), $availableFieldIds);

			return array(
				'format' => self::FORMAT,
				'version' => self::VERSION,
				'system' => $system,
				'fields' => $fields,
			);
		}

		public static function encode(array $value, array $availableFieldIds = array())
		{
			return Json::encode(
				self::normalize($value, $availableFieldIds),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
		}

		/** Select only properties declared by the contract from a document snapshot. */
		public static function project(array $snapshot, array $contract)
		{
			$contract = self::normalize($contract);
			$document = isset($snapshot['document']) && is_array($snapshot['document'])
				? $snapshot['document']
				: array();
			$result = array('system' => array(), 'fields' => array());

			foreach ($contract['system'] as $key) {
				$result['system'][$key] = array_key_exists($key, $document) ? $document[$key] : null;
			}

			foreach ($contract['fields'] as $fieldId) {
				$key = (string) $fieldId;
				$field = isset($snapshot['fields'][$key]) && is_array($snapshot['fields'][$key])
					? $snapshot['fields'][$key]
					: array();
				$result['fields'][$key] = array(
					'id' => $fieldId,
					'alias' => isset($field['alias']) ? (string) $field['alias'] : '',
					'type' => isset($field['type']) ? (string) $field['type'] : '',
					'value' => array_key_exists('value', $field) ? $field['value'] : null,
				);
				if (isset($field['relation']) && is_array($field['relation'])) {
					$result['fields'][$key]['relation'] = $field['relation'];
				}
			}

			return $result;
		}

		protected static function uniqueStrings($values, array $allowed)
		{
			$result = array();
			foreach (is_array($values) ? $values : array() as $value) {
				$value = trim((string) $value);
				if ($value !== '' && in_array($value, $allowed, true) && !in_array($value, $result, true)) {
					$result[] = $value;
				}
			}

			return $result;
		}

		protected static function uniqueIds($values, array $availableFieldIds)
		{
			$allowed = array();
			foreach ($availableFieldIds as $fieldId) {
				$fieldId = (int) $fieldId;
				if ($fieldId > 0) { $allowed[$fieldId] = true; }
			}

			$result = array();
			foreach (is_array($values) ? $values : array() as $value) {
				$fieldId = (int) $value;
				if ($fieldId <= 0 || ($allowed && !isset($allowed[$fieldId])) || isset($result[$fieldId])) {
					continue;
				}

				$result[$fieldId] = $fieldId;
				if (count($result) >= 100) { break; }
			}

			return array_values($result);
		}
	}
