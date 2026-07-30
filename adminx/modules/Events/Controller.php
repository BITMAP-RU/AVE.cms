<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Events/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Events;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Events/assets/events.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Events/assets/events.js', 50);

			$source = Request::getStr('source', Model::defaultSource());
			$q = Request::getStr('q', '');
			$limit = Request::getInt('limit', 300);
			$sourceDef = Model::source($source);
			$rows = Model::rows($sourceDef['code'], $q, $limit);

			return $this->render('@events/index.twig', array(
				'sources' => Model::summaries(),
				'active_source' => $sourceDef['code'],
				'active_source_def' => $sourceDef,
				'rows' => $rows,
				'q' => $q,
				'limit' => $limit,
				'can_manage' => Permission::check('manage_events'),
			));
		}

		public function clear(array $params = array())
		{
			if (($err = $this->guard()) !== null) {
				return $err;
			}

			$source = isset($params['source']) ? $params['source'] : '';
			if (!Model::clear($source, Auth::id())) {
				return $this->error('Этот источник нельзя очистить', array(), 422);
			}

			return $this->success($source === 'audit' ? 'Аудит очищен' : 'Журнал очищен', array(
				'redirect' => $this->base() . '/events?source=' . rawurlencode($source),
			));
		}

		public function export(array $params = array())
		{
			if (!Permission::check('view_events')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$source = Model::source(isset($params['source']) ? $params['source'] : '');
			$csv = Model::csv($source['code'], Request::getStr('q', ''), 1000);
			$file = 'events_' . $source['code'] . '_' . date('Ymd_His') . '.csv';

			Request::setHeader('Content-Type: text/csv; charset=UTF-8', true);
			Request::setHeader('Content-Disposition: attachment; filename="' . addslashes($file) . '"', true);
			Request::setHeader('Cache-Control: must-revalidate', true);
			Request::setHeader('Pragma: public', true);
			echo "\xEF\xBB\xBF" . $csv;
			Request::shutDown();
		}

		protected function guard()
		{
			return $this->guardPermission('manage_events');
		}
	}
