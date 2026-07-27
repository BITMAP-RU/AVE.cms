<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Registrations/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Registrations;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;
	use App\Helpers\Response;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_registrations')) {
				Response::forbidden();
				return '';
			}

			$filters = array(
				'status'     => Request::getStr('status', ''),
				'risk_class' => Request::getStr('risk_class', ''),
				'q'          => Request::getStr('q', ''),
			);

			return $this->render('@registrations/index.twig', array(
				'items'        => Model::all($filters),
				'summary'      => Model::summary(),
				'statuses'     => Model::statuses(),
				'risk_classes' => Model::riskClasses(),
				'filters'      => $filters,
				'can_manage'   => Permission::check('manage_registrations'),
			));
		}

		public function create(array $params = array())
		{
			if (!Permission::check('manage_registrations')) {
				Response::forbidden();
				return '';
			}

			return $this->render('@registrations/edit.twig', array(
				'item'         => null,
				'statuses'     => Model::statuses(),
				'risk_classes' => Model::riskClasses(),
			));
		}

		public function edit(array $params = array())
		{
			if (!Permission::check('view_registrations')) {
				Response::forbidden();
				return '';
			}

			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) {
				Response::notFound();
				return '';
			}

			return $this->render('@registrations/edit.twig', array(
				'item'         => $item,
				'statuses'     => Model::statuses(),
				'risk_classes' => Model::riskClasses(),
				'can_manage'   => Permission::check('manage_registrations'),
			));
		}

		public function store(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			try {
				$item = Model::create($this->input());
				return $this->success('Удостоверение добавлено', array(
					'data' => array('id' => $item['id'], 'redirect' => $this->base() . '/registrations'),
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array('number' => $e->getMessage()), 422);
			}
		}

		public function update(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			try {
				$item = Model::update(isset($params['id']) ? (int) $params['id'] : 0, $this->input());
				if (!$item) {
					return $this->error('Удостоверение не найдено', array(), 404);
				}

				return $this->success('Сохранено', array('data' => array('id' => $item['id'])));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array('number' => $e->getMessage()), 422);
			}
		}

		public function delete(array $params = array())
		{
			if (($error = $this->manageGuard()) !== null) {
				return $error;
			}

			$deleted = Model::delete(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$deleted) {
				return $this->error('Удостоверение не найдено', array(), 404);
			}

			return $this->success('Удостоверение удалено', array(
				'data' => array('summary' => Model::summary()),
			));
		}

		protected function input()
		{
			return array(
				'number'       => Request::postStr('number', ''),
				'holder'       => Request::postStr('holder', ''),
				'product_name' => Request::postStr('product_name', ''),
				'risk_class'   => Request::postStr('risk_class', ''),
				'status'       => Request::postStr('status', 'active'),
				'document_id'  => Request::postInt('document_id', 0),
				'scan_url'     => Request::postStr('scan_url', ''),
				'issued_at'    => Request::postStr('issued_at', ''),
				'valid_until'  => Request::postStr('valid_until', ''),
				'perpetual'    => Request::postInt('perpetual', 0),
				'note'         => Request::postStr('note', ''),
			);
		}

		protected function manageGuard()
		{
			if (($error = $this->csrfGuard()) !== null) {
				return $error;
			}

			return Permission::check('manage_registrations')
				? null
				: $this->error('Недостаточно прав', array(), 403);
		}
	}
