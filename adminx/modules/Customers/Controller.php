<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Customers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Adminx\Support\Roles;
	use App\Adminx\Support\CodeEditor;
	use App\Adminx\Support\SavedViews;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;
	use App\Helpers\Response;
	use App\Helpers\Csv;
	use App\Common\AuditLog;

	class Controller extends BaseController
	{
		public function exportCustomers(array $params = array())
		{
			if (!Permission::check('view_customers')) { Response::forbidden(); return ''; }
			$q = Request::getStr('q', '');
			AuditLog::record('customers.exported', array('actor_id' => Auth::id(), 'target_type' => 'customers', 'meta' => array('q' => $q)));
			Csv::download('customers_' . date('Ymd_His') . '.csv', array('ID', 'Имя', 'Фамилия', 'Логин', 'Email', 'Телефон', 'Компания', 'Статус', 'Регистрация', 'Последний вход'), function ($write) use ($q) {
				$before = 0;
				do {
					$rows = Model::exportChunk($q, $before, 500);
					foreach ($rows as $row) { $before = (int) $row['id']; $write(array($row['id'], $row['firstname'], $row['lastname'], $row['user_name'], $row['email'], $row['phone'], $row['company'], (string) $row['status'] === '1' ? 'Активен' : 'Отключён', (int) $row['reg_time'] > 0 ? date('d.m.Y H:i', (int) $row['reg_time']) : '', (int) $row['last_visit'] > 0 ? date('d.m.Y H:i', (int) $row['last_visit']) : ''));
					}
				} while (count($rows) === 500);
			});
			return '';
		}

		public function index(array $params=array())
		{
			if(!Permission::check('view_customers')){Response::forbidden();return '';}AdminAssets::addStyle($this->base().'/modules/Customers/assets/customers.css',50);AdminAssets::addScript($this->base().'/modules/Customers/assets/customers.js',50);CodeEditor::useCodeMirror('htmlmixed');
			$authMethods=Model::authMethods();$authMethodStats=array('installed'=>0,'active'=>0);foreach($authMethods as &$authMethod){$authMethod['can_open']=$authMethod['installed']?Permission::check($authMethod['permission']):Permission::check('view_modules');$authMethod['action_url']=$authMethod['installed']?$authMethod['url']:'/modules';$authMethod['action_label']=$authMethod['installed']?'Настроить':'Открыть модули';if($authMethod['installed']){$authMethodStats['installed']++;}if($authMethod['active']){$authMethodStats['active']++;}}unset($authMethod);
			$tab=Request::getStr('tab','center');if(!in_array($tab,array('center','customers','fields','auth','pages'),true)){$tab='center';}$q=Request::getStr('q','');$segment=Request::getStr('segment','all');return $this->render('@customers/index.twig',array('tab'=>$tab,'customers'=>Model::customers($q),'fields'=>Model::fields(),'stats'=>Model::stats(),'center_customers'=>$tab==='center'?CustomerCenter::listing($q,$segment):array(),'center_stats'=>$tab==='center'?CustomerCenter::stats():array(),'center_segments'=>CustomerCenter::segments(),'duplicate_groups'=>$tab==='center'?CustomerCenter::duplicateGroups():array(),'segment'=>$segment,'saved_views'=>SavedViews::all('customers_center',Auth::id(),array('q','segment')),'auth_settings'=>Model::authSettings(),'checkout_access_template_default'=>\App\Common\PublicAuthSettings::defaultCheckoutAccessTemplate(),'auth_methods'=>$authMethods,'auth_method_stats'=>$authMethodStats,'customer_groups'=>Model::groups(),'admin_roles'=>Roles::map(),'page_templates'=>Model::pageTemplates(),'auth_forms'=>Model::authFormDefinitions(),'q'=>$q,'can_manage'=>Permission::check('manage_customers'),'can_manage_admin_access'=>Permission::check('manage_users'),'current_public_user_id'=>Model::publicIdForSystem(Auth::id())));
		}

		public function saveSavedView(array $params = array())
		{
			if (($error = $this->savedViewGuard()) !== null) { return $error; }
			$filters = json_decode(Request::postStr('filters', '{}'), true);
			if (!is_array($filters)) { return $this->error('Некорректный набор фильтров', array(), 422); }
			try { $views = SavedViews::save('customers_center', Auth::id(), Request::postStr('title', ''), $filters, array('q', 'segment')); }
			catch (\InvalidArgumentException $e) { return $this->error($e->getMessage(), array(), 422); }
			return $this->success('Представление сохранено', array('data' => array('views' => $views)));
		}

		public function deleteSavedView(array $params = array())
		{
			if (($error = $this->savedViewGuard()) !== null) { return $error; }
			try { $views = SavedViews::delete('customers_center', Auth::id(), isset($params['id']) ? $params['id'] : '', array('q', 'segment')); }
			catch (\InvalidArgumentException $e) { return $this->error($e->getMessage(), array(), 404); }
			return $this->success('Представление удалено', array('data' => array('views' => $views)));
		}

		protected function savedViewGuard() { if (($error = $this->csrfGuard()) !== null) { return $error; } return Permission::check('view_customers') ? null : $this->error('Недостаточно прав', array(), 403); }

		public function centerCustomer(array $params = array())
		{
			if (!Permission::check('view_customers')) { return $this->error('Недостаточно прав', array(), 403); }
			$customer = CustomerCenter::detail(isset($params['id']) ? (int) $params['id'] : 0, Auth::id());
			if (!$customer) { return $this->error('Пользователь не найден', array(), 404); }
			return $this->success('', array('html' => array('detail' => $this->render('@customers/customer-center-detail.twig', array('customer' => $customer, 'can_manage' => Permission::check('manage_customers'))))));
		}

		public function addCustomerNote(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { CustomerCenter::addNote(isset($params['id']) ? (int) $params['id'] : 0, Auth::id(), Request::postStr('note', '')); }
			catch (\InvalidArgumentException $e) { return $this->error($e->getMessage(), array(), 422); }
			catch (\Throwable $e) { error_log('Customer note: ' . $e->getMessage()); return $this->error('Не удалось добавить заметку', array(), 500); }
			return $this->success('Заметка добавлена');
		}

		public function mergeCustomers(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $customer = CustomerCenter::merge(Request::postInt('target_id', 0), Request::postInt('source_id', 0), Auth::id()); }
			catch (\InvalidArgumentException $e) { return $this->error($e->getMessage(), array(), 422); }
			catch (\Throwable $e) { error_log('Customer merge: ' . $e->getMessage()); return $this->error('Не удалось объединить аккаунты', array(), 500); }
			return $this->success('Аккаунты объединены', array('data' => array('id' => (int) $customer['user']['id']), 'reload' => true));
		}

		public function toggle(array $params=array())
		{
			if(($e=$this->guard())!==null){return $e;}
			$id=isset($params['id'])?(int)$params['id']:0;
			try{$active=Model::toggle($id,Auth::id());}catch(\InvalidArgumentException $e){return $this->error($e->getMessage(),array(),422);}
			AuditLog::record('customers.status_changed',array('actor_id'=>Auth::id(),'target_type'=>'public_user','target_id'=>$id,'meta'=>array('active'=>$active)));
			return $this->success($active?'Пользователь включён':'Пользователь отключён');
		}

		public function customer(array $params=array())
		{
			if(!Permission::check('view_customers')){return $this->error('Недостаточно прав',array(),403);}
			$customer=Model::customer(isset($params['id'])?$params['id']:0,Auth::id());
			return $customer?$this->success('',array('data'=>$customer)):$this->error('Пользователь не найден',array(),404);
		}

		public function updateCustomer(array $params=array())
		{
			if(($e=$this->guard())!==null){return $e;}
			$id=isset($params['id'])?(int)$params['id']:0;$input=Request::postAll();
			if(!Permission::check('manage_users')){$current=Model::customer($id,Auth::id());$input['admin_access']=!empty($current['system']['is_active'])?'1':'';$input['admin_role']=!empty($current['system']['role'])?(string)$current['system']['role']:'manager';}
			try{$customer=Model::updateCustomer($id,$input,Auth::id());}catch(\InvalidArgumentException $e){return $this->error($e->getMessage(),array(),422);}catch(\Throwable $e){return $this->error('Не удалось сохранить пользователя',array(),500);}
			AuditLog::record('customers.updated',array('actor_id'=>Auth::id(),'target_type'=>'public_user','target_id'=>$id));
			return $this->success('Профиль пользователя сохранён',array('data'=>$customer));
		}

		public function deleteCustomer(array $params = array())
		{
			if (($e = $this->guard()) !== null) {
				return $e;
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try {
				Model::deleteCustomer($id, Auth::id());
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			} catch (\Throwable $e) {
				return $this->error('Не удалось удалить пользователя', array(), 500);
			}

			AuditLog::record('customers.deleted', array('actor_id' => Auth::id(), 'target_type' => 'public_user', 'target_id' => $id));

			return $this->success('Пользователь удалён', array('reload' => true));
		}

		public function saveField(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$id=Model::saveField(isset($params['id'])?$params['id']:0,Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Поле сохранено',array('data'=>array('id'=>$id),'reload'=>true));}
		public function deleteField(array $params=array()){if(($e=$this->guard())!==null){return $e;}Model::deleteField(isset($params['id'])?$params['id']:0);return $this->success('Поле удалено',array('reload'=>true));}
		public function toggleField(array $params=array()){if(($e=$this->guard())!==null){return $e;}$active=Model::toggleField(isset($params['id'])?$params['id']:0);return $this->success($active?'Поле включено':'Поле скрыто',array('data'=>array('is_active'=>$active?1:0)));}
		public function reorderFields(array $params=array()){if(($e=$this->guard())!==null){return $e;}$ids=json_decode(Request::postStr('order','[]'),true);if(!is_array($ids)){return $this->error('Некорректный порядок полей',array(),422);}$count=Model::reorderFields($ids);return $this->success('Порядок полей сохранён',array('data'=>array('count'=>$count)));}
		public function saveAuthSettings(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$settings=Model::saveAuthSettings(Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}AuditLog::record('customers.auth_settings_updated',array('actor_id'=>Auth::id(),'target_type'=>'public_auth'));return $this->success('Настройки регистрации сохранены',array('data'=>array('settings'=>$settings)));}
		public function saveAuthPages(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$settings=Model::saveAuthPages(Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}AuditLog::record('customers.auth_pages_updated',array('actor_id'=>Auth::id(),'target_type'=>'public_auth'));return $this->success('Страницы входа сохранены',array('data'=>array('settings'=>$settings)));}
		public function authForm(array $params=array()){if(!Permission::check('view_customers')){return $this->error('Недостаточно прав',array(),403);}try{$form=Model::authForm(isset($params['key'])?(string)$params['key']:'');}catch(\Throwable $e){return $this->error($e->getMessage(),array(),404);}return $this->success('',array('data'=>$form));}
		public function saveAuthForm(array $params=array()){if(($e=$this->guard())!==null){return $e;}$key=isset($params['key'])?(string)$params['key']:'';try{$template=Model::saveAuthForm($key,Request::postStr('template',''),Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array('template'=>$e->getMessage()),422);}AuditLog::record('customers.auth_form_updated',array('actor_id'=>Auth::id(),'target_type'=>'public_auth_form','meta'=>array('form'=>$key)));return $this->success('Шаблон формы сохранён',array('data'=>array('template'=>$template,'customized'=>1)));}
		public function resetAuthForm(array $params=array()){if(($e=$this->guard())!==null){return $e;}$key=isset($params['key'])?(string)$params['key']:'';try{$template=Model::resetAuthForm($key);}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}AuditLog::record('customers.auth_form_reset',array('actor_id'=>Auth::id(),'target_type'=>'public_auth_form','meta'=>array('form'=>$key)));return $this->success('Восстановлен штатный шаблон формы',array('data'=>array('template'=>$template,'customized'=>0)));}
		protected function guard(){return $this->guardPermission('manage_customers');}
	}
