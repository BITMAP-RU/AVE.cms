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
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;
	use App\Helpers\Response;

	class Controller extends BaseController
	{
		public function index(array $params=array())
		{
			if(!Permission::check('view_customers')){Response::forbidden();return '';}AdminAssets::addStyle($this->base().'/modules/Customers/assets/customers.css',50);AdminAssets::addScript($this->base().'/modules/Customers/assets/customers.js',50);CodeEditor::useCodeMirror('htmlmixed');
			$oauthModules=Model::oauthModules();foreach($oauthModules as &$oauthModule){$oauthModule['can_open']=Permission::check($oauthModule['permission']);}unset($oauthModule);
			$tab=Request::getStr('tab','customers');if(!in_array($tab,array('customers','fields','auth','pages'),true)){$tab='customers';}return $this->render('@customers/index.twig',array('tab'=>$tab,'customers'=>Model::customers(Request::getStr('q','')),'fields'=>Model::fields(),'stats'=>Model::stats(),'auth_settings'=>Model::authSettings(),'checkout_access_template_default'=>\App\Common\PublicAuthSettings::defaultCheckoutAccessTemplate(),'oauth_modules'=>$oauthModules,'customer_groups'=>Model::groups(),'admin_roles'=>Roles::map(),'page_templates'=>Model::pageTemplates(),'auth_forms'=>Model::authFormDefinitions(),'q'=>Request::getStr('q',''),'can_manage'=>Permission::check('manage_customers'),'can_manage_admin_access'=>Permission::check('manage_users')));
		}

		public function toggle(array $params=array()){if(($e=$this->guard())!==null){return $e;}return $this->success(Model::toggle(isset($params['id'])?$params['id']:0)?'Пользователь включён':'Пользователь отключён');}
		public function customer(array $params=array())
		{
			if(!Permission::check('view_customers')){return $this->error('Недостаточно прав',array(),403);}
			$customer=Model::customer(isset($params['id'])?$params['id']:0);
			return $customer?$this->success('',array('data'=>$customer)):$this->error('Пользователь не найден',array(),404);
		}

		public function updateCustomer(array $params=array())
		{
			if(($e=$this->guard())!==null){return $e;}
			$id=isset($params['id'])?(int)$params['id']:0;$input=Request::postAll();
			if(!Permission::check('manage_users')){$current=Model::customer($id);$input['admin_access']=!empty($current['system']['is_active'])?'1':'';$input['admin_role']=!empty($current['system']['role'])?(string)$current['system']['role']:'manager';}
			try{$customer=Model::updateCustomer($id,$input,Auth::id());}catch(\InvalidArgumentException $e){return $this->error($e->getMessage(),array(),422);}catch(\Throwable $e){return $this->error('Не удалось сохранить пользователя',array(),500);}
			return $this->success('Профиль пользователя сохранён',array('data'=>$customer));
		}

		public function saveField(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$id=Model::saveField(isset($params['id'])?$params['id']:0,Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Поле сохранено',array('data'=>array('id'=>$id),'reload'=>true));}
		public function deleteField(array $params=array()){if(($e=$this->guard())!==null){return $e;}Model::deleteField(isset($params['id'])?$params['id']:0);return $this->success('Поле удалено',array('reload'=>true));}
		public function toggleField(array $params=array()){if(($e=$this->guard())!==null){return $e;}$active=Model::toggleField(isset($params['id'])?$params['id']:0);return $this->success($active?'Поле включено':'Поле скрыто',array('data'=>array('is_active'=>$active?1:0)));}
		public function reorderFields(array $params=array()){if(($e=$this->guard())!==null){return $e;}$ids=json_decode(Request::postStr('order','[]'),true);if(!is_array($ids)){return $this->error('Некорректный порядок полей',array(),422);}$count=Model::reorderFields($ids);return $this->success('Порядок полей сохранён',array('data'=>array('count'=>$count)));}
		public function saveAuthSettings(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$settings=Model::saveAuthSettings(Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Настройки регистрации сохранены',array('data'=>array('settings'=>$settings)));}
		public function saveAuthPages(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$settings=Model::saveAuthPages(Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Страницы входа сохранены',array('data'=>array('settings'=>$settings)));}
		public function authForm(array $params=array()){if(!Permission::check('view_customers')){return $this->error('Недостаточно прав',array(),403);}try{$form=Model::authForm(isset($params['key'])?(string)$params['key']:'');}catch(\Throwable $e){return $this->error($e->getMessage(),array(),404);}return $this->success('',array('data'=>$form));}
		public function saveAuthForm(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$template=Model::saveAuthForm(isset($params['key'])?(string)$params['key']:'',Request::postStr('template',''),Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array('template'=>$e->getMessage()),422);}return $this->success('Шаблон формы сохранён',array('data'=>array('template'=>$template,'customized'=>1)));}
		public function resetAuthForm(array $params=array()){if(($e=$this->guard())!==null){return $e;}try{$template=Model::resetAuthForm(isset($params['key'])?(string)$params['key']:'');}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Восстановлен штатный шаблон формы',array('data'=>array('template'=>$template,'customized'=>0)));}
		protected function guard(){return $this->guardPermission('manage_customers');}
	}
