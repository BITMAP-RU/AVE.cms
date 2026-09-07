<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/ProfileRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\PublicUserTables;

	class ProfileRepository
	{
		public function fields($context = 'profile')
		{
			$column = (string) $context === 'registration' ? 'show_registration' : 'show_profile';
			$rows = DB::query('SELECT * FROM `' . PublicUserTables::table('user_profile_fields') . '` WHERE is_active=1 AND `' . $column . '`=1 ORDER BY position ASC,id ASC')->getAll();
			$out = array();
			foreach ($rows ?: array() as $row) {
				$field = (array) $row;
				$field['choices'] = $this->choices(isset($field['options']) ? $field['options'] : '');
				$out[] = $field;
			}

			return $out;
		}

		public function values($userId)
		{
			$rows = DB::query('SELECT field_id,value FROM `' . PublicUserTables::table('user_profile_values') . '` WHERE user_id=%i', (int) $userId)->getAll();
			$out = array();
			foreach ($rows ?: array() as $row) {
				$row = (array) $row;
				$out[(int) $row['field_id']] = (string) $row['value'];
			}

			return $out;
		}

		public function save($userId, array $values, $context = 'profile')
		{
			foreach ($this->fields($context) as $field) {
				$fieldId = (int) $field['id'];
				$value = isset($values[$fieldId]) ? $values[$fieldId] : '';
				if ((string) $field['type'] === 'checkbox') {
					$value = !empty($value) ? '1' : '0';
				} elseif (is_array($value)) {
					$value = implode(',', array_map('trim', $value));
				} else {
					$value = trim((string) $value);
				}

				DB::query('INSERT INTO `' . PublicUserTables::table('user_profile_values') . '` (user_id,field_id,value,updated_at) VALUES (%i,%i,%s,%i)'
					. ' ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=VALUES(updated_at)', (int) $userId, $fieldId, $value, time());
			}
		}

		public function validate(array $values, $context = 'profile')
		{
			$errors = array();
			foreach ($this->fields($context) as $field) {
				$value = isset($values[(int) $field['id']]) ? $values[(int) $field['id']] : '';
				$normalized = trim(is_array($value) ? implode(',', array_map('trim', $value)) : (string) $value);
				$key = 'extra_' . (int) $field['id'];
				$type = (string) $field['type'];
				if (!empty($field['is_required']) && ($normalized === '' || ($type === 'checkbox' && $normalized !== '1'))) {
					$errors[$key] = 'Заполните поле «' . (string) $field['name'] . '».';
				} elseif ($normalized !== '' && $type === 'email' && !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
					$errors[$key] = 'Укажите корректный email.';
				} elseif ($normalized !== '' && $type === 'number' && !is_numeric($normalized)) {
					$errors[$key] = 'Укажите число.';
				} elseif ($normalized !== '' && $type === 'date' && !$this->validDate($normalized)) {
					$errors[$key] = 'Укажите корректную дату.';
				} elseif ($normalized !== '' && $type === 'select' && !in_array($normalized, $field['choices'], true)) {
					$errors[$key] = 'Выберите значение из списка.';
				}
			}

			return $errors;
		}

		protected function validDate($value)
		{
			$date = \DateTime::createFromFormat('!Y-m-d', (string) $value);
			$errors = \DateTime::getLastErrors();
			return $date !== false
				&& ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0));
		}

		protected function choices($value)
		{
			$json = json_decode((string) $value, true);
			if (is_array($json)) {
				return $json;
			}

			return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $value))));
		}
	}
