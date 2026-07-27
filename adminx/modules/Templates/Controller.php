<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Templates/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Templates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\CodeEditor;
	use App\Common\AdminAssets;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Templates/assets/templates.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Templates/assets/templates.js', 50);
			CodeEditor::useCodeMirror('application/x-httpd-php');

			$q = Request::getStr('q', '');
			$items = Model::all($q);

			return $this->render('@templates/index.twig', array(
				'templates' => $items,
				'stats' => Model::stats(),
				'filters' => array('q' => $q),
				'tag_groups' => TagRegistry::groups(),
				'can_manage' => Permission::check('manage_templates'),
			));
		}

		public function show(array $params = array())
		{
			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Шаблон не найден', array(), 404);
			}

			return $this->success('', array('data' => $item));
		}

		public function store(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$errors = $this->validate(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте поля формы', $errors);
			}

			$id = Model::save(0, Request::postAll(), Auth::id());
			return $this->success('Шаблон создан', array('data' => array('id' => $id), 'redirect' => $this->base() . '/templates'));
		}

		public function update(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($id)) {
				return $this->error('Шаблон не найден', array(), 404);
			}

			$errors = $this->validate(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте поля формы', $errors);
			}

			Model::save($id, Request::postAll(), Auth::id());
			return $this->success('Шаблон сохранён', array('redirect' => $this->base() . '/templates'));
		}

		public function copy(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				Model::copy(isset($params['id']) ? (int) $params['id'] : 0, Request::postStr('title', ''), Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Копия создана', array('redirect' => $this->base() . '/templates'));
		}

		public function revisions(array $params = array())
		{
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = Model::one($id);
			if (!$item) {
				return $this->error('Шаблон не найден', array(), 404);
			}

			return $this->success('', array(
				'data' => array(
					'template' => array('id' => $item['Id'], 'title' => $item['template_title']),
					'revisions' => Revisions::listForTemplate($id),
				),
			));
		}

		public function revision(array $params = array())
		{
			$revision = Revisions::one(isset($params['revision']) ? (int) $params['revision'] : 0);
			if (!$revision) {
				return $this->error('Ревизия не найдена', array(), 404);
			}

			return $this->success('', array('data' => $revision));
		}

		public function restoreRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$templateId = Revisions::restore(isset($params['revision']) ? (int) $params['revision'] : 0, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Шаблон восстановлен из ревизии', array('data' => array('id' => $templateId), 'redirect' => $this->base() . '/templates'));
		}

		public function deleteRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$templateId = Revisions::delete(isset($params['revision']) ? (int) $params['revision'] : 0);
			if (!$templateId) {
				return $this->error('Ревизия не найдена', array(), 404);
			}

			return $this->success('Ревизия удалена', array('data' => array('id' => $templateId)));
		}

		public function deleteRevisions(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($id)) {
				return $this->error('Шаблон не найден', array(), 404);
			}

			$count = Revisions::deleteForTemplate($id);
			return $this->success('Ревизии удалены', array('data' => array('id' => $id, 'count' => $count)));
		}

		public function lint(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$result = Syntax::check(Request::postStr('template_text', ''));
			if (!$result['ok']) {
				return $this->error($result['message'], array('template_text' => $result['output']), 422);
			}

			return $this->success($result['message'], array('data' => $result));
		}

		public function clearCache(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Шаблон не найден', array(), 404);
			}

			Model::clearCache($item['Id']);
			return $this->success('Файловый кеш шаблона очищен');
		}

		public function destroy(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				if (!Model::delete(isset($params['id']) ? (int) $params['id'] : 0, Auth::id())) {
					return $this->error('Шаблон не найден', array(), 404);
				}
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Шаблон удалён');
		}

		protected function guard()
		{
			return $this->guardPermission('manage_templates');
		}

		protected function validate(array $input)
		{
			$errors = array();
			$title = trim(isset($input['template_title']) ? (string) $input['template_title'] : '');
			$text = isset($input['template_text']) ? (string) $input['template_text'] : '';

			if ($title === '') {
				$errors['template_title'] = 'Укажите название';
			}

			if (trim($text) === '') {
				$errors['template_text'] = 'Укажите код шаблона';
			}

			$syntax = Syntax::check($text);
			if (!empty($syntax['checked']) && empty($syntax['ok'])) {
				$errors['template_text'] = $syntax['line'] > 0
					? 'PHP-синтаксис: ошибка на строке ' . (int) $syntax['line']
					: 'PHP-синтаксис: ошибка в коде шаблона';
			}

			return $errors;
		}
	}
