<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Navigation/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Navigation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\CodeEditor;
	use App\Adminx\Support\AdminLocale;
	use App\Common\AdminAssets;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Hooks;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Navigation/assets/navigation.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Navigation/assets/navigation.js', 50);
			CodeEditor::useCodeMirror('htmlmixed');

			$q = Request::getStr('q', '');
			$panelSources = Hooks::filter('navigation.item.panel_sources', array());
			if (!is_array($panelSources)) { $panelSources = array(); }
			$panelSources = array_values(array_filter(array_map(function ($source) {
				if (!is_array($source)) { return null; }
				$code = strtolower(trim(isset($source['code']) ? (string) $source['code'] : ''));
				$title = trim(isset($source['title']) ? (string) $source['title'] : '');
				if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $code) || $title === '') { return null; }
				return array('code' => $code, 'title' => AdminLocale::translateMarkup($title));
			}, $panelSources)));

			return $this->render('@navigation/index.twig', array(
				'navigation_items' => Model::all($q),
				'stats' => Model::stats(),
				'filters' => array('q' => $q),
				'access_groups' => Model::accessGroups(),
				'navigation_panel_sources' => $panelSources,
				'can_manage' => Permission::check('manage_navigation'),
			));
		}

		public function show(array $params = array())
		{
			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Навигация не найдена', array(), 404);
			}

			return $this->success('', array('data' => $item));
		}

		public function aliasCheck(array $params = array())
		{
			if (!Permission::check('manage_navigation')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$alias = trim(Request::getStr('alias', ''));
			$id = Request::getInt('id', 0);
			$valid = true;
			$available = true;
			$message = 'Алиас свободен';

			if ($alias === '') {
				$valid = false;
				$available = false;
				$message = 'Укажите алиас';
			} elseif (preg_match('/^[A-Za-z0-9-_]{1,20}$/i', $alias) !== 1 || is_numeric($alias)) {
				$valid = false;
				$available = false;
				$message = 'Только A-Z, 0-9, дефис и подчёркивание, до 20 символов; не число';
			} elseif (Model::aliasExists($alias, $id)) {
				$available = false;
				$message = 'Такой алиас уже используется';
			}

			return $this->success($message, array(
				'data' => array(
					'alias' => $alias,
					'id' => $id,
					'valid' => $valid,
					'available' => $available,
				),
			));
		}

		public function documentPicker(array $params = array())
		{
			if (!Permission::check('manage_navigation')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			return $this->success('', array(
				'data' => array(
					'items' => Model::documentPicker(
						Request::getStr('q', ''),
						Request::getInt('limit', 30)
					),
				),
			));
		}

		public function store(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$errors = $this->validate(Request::postAll(), 0);
			if (!empty($errors)) {
				return $this->error('Проверьте поля формы', $errors);
			}

			$id = Model::save(0, Request::postAll());
			Revisions::capture($id, 'create', Auth::id());
			return $this->success('Навигация создана', array('data' => array('id' => $id), 'redirect' => $this->base() . '/navigation'));
		}

		public function update(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($id)) {
				return $this->error('Навигация не найдена', array(), 404);
			}

			$errors = $this->validate(Request::postAll(), $id);
			if (!empty($errors)) {
				return $this->error('Проверьте поля формы', $errors);
			}

			Revisions::capture($id, 'baseline', Auth::id(), 'Снимок до изменения');
			Model::save($id, Request::postAll());
			Revisions::capture($id, 'update', Auth::id());
			return $this->success('Навигация сохранена', array('redirect' => $this->base() . '/navigation'));
		}

		public function copy(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				$id = Model::copy(isset($params['id']) ? (int) $params['id'] : 0, Request::postStr('title', ''));
				Revisions::capture($id, 'create', Auth::id(), 'Создано копированием');
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Копия создана', array('redirect' => $this->base() . '/navigation'));
		}

		public function revisions(array $params = array())
		{
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = Model::one($id);
			if (!$item) { return $this->error('Навигация не найдена', array(), 404); }

			return $this->success('', array('data' => array(
				'navigation' => array('id' => $id, 'title' => $item['title']),
				'revisions' => Revisions::listing($id),
			)));
		}

		public function revision(array $params = array())
		{
			$revision = Revisions::one(isset($params['revision']) ? (int) $params['revision'] : 0);
			return $revision
				? $this->success('', array('data' => $revision))
				: $this->error('Ревизия не найдена', array(), 404);
		}

		public function restoreRevision(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			try {
				$id = Revisions::restore(isset($params['revision']) ? (int) $params['revision'] : 0, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Навигация восстановлена из ревизии', array(
				'data' => array('id' => $id),
				'redirect' => $this->base() . '/navigation',
			));
		}

		public function lint(array $params = array())
		{
			if (($err = $this->guard()) !== null) { return $err; }
			if (!class_exists('App\\Adminx\\Templates\\Syntax')) {
				return $this->error('Проверка недоступна', array(), 501);
			}

			$result = \App\Adminx\Templates\Syntax::check((string) Request::post('code', ''));
			if (!empty($result['ok'])) {
				return $this->success($result['message'], array('data' => array('checked' => !empty($result['checked']))));
			}

			return $this->error($result['message'], array('code' => $result['output']), 422);
		}

		public function clearCache(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				return $this->error('Навигация не найдена', array(), 404);
			}

			Model::clearCache($item['navigation_id'], $item['alias']);
			return $this->success('Кеш навигации очищен');
		}

		public function items(array $params = array())
		{
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$nav = Model::one($id);
			if (!$nav) {
				return $this->error('Навигация не найдена', array(), 404);
			}

			return $this->success('', array(
				'data' => array(
					'navigation' => array(
						'id' => $nav['navigation_id'],
						'title' => $nav['title'],
						'alias' => $nav['alias'],
						'tag' => $nav['tag'],
					),
					'items' => Model::itemsTree($id),
					'flat' => Model::flatItems($id),
				),
			));
		}

		public function item(array $params = array())
		{
			$item = Model::item(isset($params['item']) ? (int) $params['item'] : 0);
			if (!$item) {
				return $this->error('Пункт навигации не найден', array(), 404);
			}

			return $this->success('', array('data' => $item));
		}

		public function storeItem(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$navigationId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($navigationId)) {
				return $this->error('Навигация не найдена', array(), 404);
			}

			$errors = $this->validateItem(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте поля пункта', $errors);
			}

			$id = Model::saveItem(0, $navigationId, Request::postAll());
			return $this->success('Пункт навигации создан', array('data' => array('id' => $id)));
		}

		public function updateItem(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$id = isset($params['item']) ? (int) $params['item'] : 0;
			$item = Model::item($id);
			if (!$item) {
				return $this->error('Пункт навигации не найден', array(), 404);
			}

			$errors = $this->validateItem(Request::postAll());
			if (!empty($errors)) {
				return $this->error('Проверьте поля пункта', $errors);
			}

			Model::saveItem($id, (int) $item['navigation_id'], Request::postAll());
			return $this->success('Пункт навигации сохранён');
		}

		public function reorderItems(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$navigationId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($navigationId)) {
				return $this->error('Навигация не найдена', array(), 404);
			}

			$order = Request::postJsonArray('order');
			if (!is_array($order)) {
				return $this->error('Некорректный порядок пунктов', array(), 422);
			}

			try {
				Model::reorderItems($navigationId, $order);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Порядок пунктов сохранён');
		}

		public function toggleItem(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$status = Model::toggleItem(isset($params['item']) ? (int) $params['item'] : 0);
			if ($status === null) {
				return $this->error('Пункт навигации не найден', array(), 404);
			}

			return $this->success($status === '1' ? 'Пункт включён' : 'Пункт выключен', array('data' => array('status' => $status)));
		}

		public function destroyItem(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			if (!Model::deleteItem(isset($params['item']) ? (int) $params['item'] : 0)) {
				return $this->error('Пункт навигации не найден', array(), 404);
			}

			return $this->success('Пункт навигации удалён или выключен');
		}

		public function destroy(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			try {
				if (!Model::delete(isset($params['id']) ? (int) $params['id'] : 0)) {
					return $this->error('Навигация не найдена', array(), 404);
				}
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Навигация удалена');
		}

		protected function guard()
		{
			return $this->guardPermission('manage_navigation');
		}

		protected function validate(array $input, $id)
		{
			$errors = array();
			$title = trim(isset($input['title']) ? (string) $input['title'] : '');
			$alias = trim(isset($input['alias']) ? (string) $input['alias'] : '');
			$level1 = isset($input['level1']) ? trim((string) $input['level1']) : '';
			$level1Active = isset($input['level1_active']) ? trim((string) $input['level1_active']) : '';

			if ($title === '') {
				$errors['title'] = 'Укажите название';
			}

			if ($alias === '') {
				$errors['alias'] = 'Укажите алиас';
			} elseif (preg_match('/^[A-Za-z0-9-_]{1,20}$/i', $alias) !== 1 || is_numeric($alias)) {
				$errors['alias'] = 'Только A-Z, 0-9, дефис и подчёркивание, до 20 символов; не число';
			} elseif (Model::aliasExists($alias, (int) $id)) {
				$errors['alias'] = 'Такой алиас уже используется';
			}

			if ($level1 === '') {
				$errors['level1'] = 'Укажите шаблон ссылки первого уровня';
			}

			if ($level1Active === '') {
				$errors['level1_active'] = 'Укажите активный шаблон первого уровня';
			}

			return $errors;
		}

		protected function validateItem(array $input)
		{
			$errors = array();
			$title = trim(isset($input['title']) ? (string) $input['title'] : '');
			$alias = trim(isset($input['alias']) ? (string) $input['alias'] : '');
			$parentId = isset($input['parent_id']) ? (int) $input['parent_id'] : 0;

			if ($title === '') {
				$errors['item_title'] = 'Укажите название пункта';
			}

			if ($alias === '') {
				$errors['item_alias'] = 'Укажите ссылку или alias';
			}

			if ($parentId < 0) {
				$errors['item_parent_id'] = 'Некорректный родитель';
			}

			return $errors;
		}
	}
