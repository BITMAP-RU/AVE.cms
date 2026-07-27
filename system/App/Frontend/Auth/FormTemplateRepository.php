<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/FormTemplateRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\Twig;
	use App\Content\PublicUserTables;
	use App\Frontend\ThemeAssets;
	use DB;

	/** Stores editable public-auth Twig fragments with file templates as fallbacks. */
	class FormTemplateRepository
	{
		protected static $keys = array(
			'panel', 'login', 'register', 'remember', 'reset', 'profile',
			'overview', 'password', 'message',
		);

		public static function keys()
		{
			return self::$keys;
		}

		public function get($key)
		{
			$key = $this->key($key);
			$row = false;
			try {
				$row = DB::query(
					'SELECT template_text FROM `' . $this->table() . '` WHERE page_key=%s LIMIT 1',
					$key
				)->getAssoc();
			} catch (\Throwable $e) {
				error_log('Public auth templates are unavailable: ' . $e->getMessage());
			}

			if ($row && trim((string) $row['template_text']) !== '') {
				return (string) $row['template_text'];
			}

			return $this->defaultTemplate($key);
		}

		public function customized($key)
		{
			$key = $this->key($key);
			try {
				return (bool) DB::query(
					'SELECT page_key FROM `' . $this->table() . '` WHERE page_key=%s LIMIT 1',
					$key
				)->getValue();
			} catch (\Throwable $e) {
				return false;
			}
		}

		public function save($key, $template, $authorId = 0)
		{
			$key = $this->key($key);
			$template = trim((string) $template);
			if ($template === '') {
				throw new \InvalidArgumentException('Шаблон формы не может быть пустым.');
			}

			$this->validate($template, $key);
			$this->assertAvailable();
			$data = array(
				'template_text' => $template,
				'updated_by' => max(0, (int) $authorId),
				'updated_at' => date('Y-m-d H:i:s'),
			);
			if ($this->customized($key)) {
				DB::Update($this->table(), $data, 'page_key=%s', $key);
			} else {
				$data['page_key'] = $key;
				DB::Insert($this->table(), $data);
			}

			return $this->get($key);
		}

		public function reset($key)
		{
			$key = $this->key($key);
			$this->assertAvailable();
			DB::Delete($this->table(), 'page_key=%s', $key);
			return $this->defaultTemplate($key);
		}

		public function render($key, array $context)
		{
			$key = $this->key($key);
			$template = $this->get($key);
			try {
				return Twig::twig()->createTemplate($template, 'public_auth_' . $key)->render($context);
			} catch (\Throwable $e) {
				error_log('Public auth template [' . $key . ']: ' . $e->getMessage());
				return Twig::twig()->render('@system_auth/' . $key . '.twig', $context);
			}
		}

		public function validate($template, $key = 'form')
		{
			try {
				Twig::twig()->createTemplate((string) $template, 'public_auth_validate_' . preg_replace('/[^a-z0-9_]/', '', (string) $key));
			} catch (\Throwable $e) {
				throw new \InvalidArgumentException('Ошибка Twig: ' . $e->getMessage());
			}

			return true;
		}

		public function defaultTemplate($key)
		{
			$key = $this->key($key);
			$themeTemplate = ThemeAssets::viewOverrideSource('system_auth', $key . '.twig');
			if ($themeTemplate !== '') {
				return $themeTemplate;
			}

			$file = __DIR__ . '/view/' . $key . '.twig';
			if (!is_file($file)) {
				throw new \RuntimeException('Штатный шаблон формы не найден.');
			}

			return (string) file_get_contents($file);
		}

		protected function key($key)
		{
			$key = strtolower(trim((string) $key));
			if (!in_array($key, self::$keys, true)) {
				throw new \InvalidArgumentException('Неизвестный шаблон формы.');
			}

			return $key;
		}

		protected function assertAvailable()
		{
			if (!DatabaseSchema::tableExists($this->table())) {
				throw new \RuntimeException('Схема шаблонов публичной авторизации не обновлена. Примените миграции ядра.');
			}
		}

		protected function table()
		{
			return PublicUserTables::table('auth_templates');
		}
	}
