<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Customers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Adminx\Support\Roles;
	use App\Common\Auth\IdentityLinker;
	use App\Common\ModuleManager;
	use App\Common\SystemTables;
	use App\Content\PublicUserTables;
	use App\Content\ContentTables;
	use App\Common\PublicAuthSettings;
	use App\Common\Twig;
	use App\Frontend\Auth\FormTemplateRepository;
	use App\Frontend\Auth\OAuth\ProviderRegistry;
	use App\Frontend\Auth\Phone\ProviderRegistry as PhoneProviderRegistry;
	use App\Frontend\Auth\UserRepository;
	use App\Helpers\Phone;

	class Model
	{
		public static function customers($q='')
		{
			$sql='SELECT Id AS id,email,firstname,lastname,user_name,phone,company,status,reg_time,last_visit FROM '.self::table('users').' WHERE deleted!=%s';$args=array('1');$q=trim((string)$q);
			if($q!==''){$sql.=' AND (email LIKE %ss OR firstname LIKE %ss OR lastname LIKE %ss OR phone LIKE %ss OR company LIKE %ss)';for($i=0;$i<5;$i++){$args[]=$q;}}$sql.=' ORDER BY Id DESC LIMIT 500';return call_user_func_array(array('DB','query'),array_merge(array($sql),$args))->getAll()?:array();
		}

		public static function stats()
		{
			$table=self::table('users');return array('total'=>(int)DB::query('SELECT COUNT(*) FROM '.$table.' WHERE deleted!=%s','1')->getValue(),'active'=>(int)DB::query('SELECT COUNT(*) FROM '.$table.' WHERE deleted!=%s AND status=%s','1','1')->getValue(),'verified'=>(int)DB::query('SELECT COUNT(*) FROM '.$table.' WHERE deleted!=%s AND (email_verified_at>0 OR phone_verified_at>0)','1')->getValue(),'fields'=>count(self::fields()));
		}

		public static function toggle($id, $currentSystemId = 0)
		{
			$customer = self::customer($id, $currentSystemId);
			if (!$customer) {
				throw new \InvalidArgumentException('Пользователь не найден');
			}

			if (!empty($customer['is_current'])) {
				throw new \InvalidArgumentException('Нельзя отключить собственную учётную запись');
			}

			DB::query('UPDATE '.self::table('users')." SET status=IF(status='1','0','1') WHERE Id=%i AND deleted!=%s",(int)$id,'1');
			$active=(string)DB::query('SELECT status FROM '.self::table('users').' WHERE Id=%i',(int)$id)->getValue()==='1';
			if(!$active){self::invalidateCustomerSessions((int)$id);}
			return $active;
		}

		public static function customer($id, $currentSystemId = 0)
		{
			$row=DB::query('SELECT Id AS id,email,email_verified_at,firstname,lastname,user_name,phone,phone_normalized,phone_verified_at,company,city,street,street_nr,zipcode,birthday,description,user_group,status,reg_time,last_visit FROM '.self::table('users').' WHERE Id=%i AND deleted!=%s LIMIT 1',(int)$id,'1')->getAssoc();
			if(!$row){return null;}
			$row=(array)$row;
			$row['birthday']=(int)$row['birthday']>0?date('Y-m-d',(int)$row['birthday']):'';
			$values=DB::query('SELECT field_id,value FROM '.self::table('user_profile_values').' WHERE user_id=%i',(int)$id)->getAll()?:array();
			$extra=array();
			foreach($values as $value){$extra[(string)(int)$value['field_id']]=(string)$value['value'];}
			$system=IdentityLinker::systemForPublic((int)$id,(string)$row['email']);
			return array('user'=>$row,'extra'=>$extra,'system'=>$system?array(
				'id'=>(int)$system['id'],'role'=>(string)$system['role'],'is_active'=>(int)$system['is_active'],
			):null,'is_current'=>$system&&(int)$system['id']===(int)$currentSystemId);
		}

		public static function publicIdForSystem($systemId)
		{
			$system = DB::query(
				'SELECT * FROM ' . SystemTables::table('users') . ' WHERE id = %i LIMIT 1',
				(int) $systemId
			)->getAssoc();
			if (!$system) {
				return 0;
			}

			$public = IdentityLinker::publicForSystem((array) $system);
			return $public ? (int) $public['Id'] : 0;
		}

		public static function updateCustomer($id,array $input,$currentSystemId=0)
		{
			$id=(int)$id;
			$current=self::customer($id,$currentSystemId);
			if(!$current){throw new \InvalidArgumentException('Пользователь не найден');}
			if(!empty($current['is_current'])){
				$currentSystem=$current['system'];
				if(isset($input['user_group'])&&(int)$input['user_group']!==(int)$current['user']['user_group']){
					throw new \InvalidArgumentException('Нельзя изменить собственную публичную группу');
				}

				if(isset($input['admin_role'])&&(string)$input['admin_role']!==(string)$currentSystem['role']){
					throw new \InvalidArgumentException('Нельзя изменить собственную роль в панели управления');
				}

				$input['user_group']=(string)$current['user']['user_group'];
				$input['status']=(string)$current['user']['status']==='1'?'1':'';
				$input['admin_access']=!empty($currentSystem['is_active'])?'1':'';
				$input['admin_role']=(string)$currentSystem['role'];
			}

			$email=mb_strtolower(trim((string)(isset($input['email'])?$input['email']:'')));
			$phoneInput=trim((string)(isset($input['phone'])?$input['phone']:''));
			$phone=$phoneInput!==''?Phone::normalize($phoneInput):'';
			$firstName=trim((string)(isset($input['firstname'])?$input['firstname']:''));
			$lastName=trim((string)(isset($input['lastname'])?$input['lastname']:''));
			$userName=trim((string)(isset($input['user_name'])?$input['user_name']:''));
			$userName=$userName!==''?$userName:($email!==''?$email:$phone);
			$group=(int)(isset($input['user_group'])?$input['user_group']:0);
			$password=(string)(isset($input['password'])?$input['password']:'');
			$adminAccess=!empty($input['admin_access']);
			$adminRole=trim((string)(isset($input['admin_role'])?$input['admin_role']:'manager'));
			if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)){throw new \InvalidArgumentException('Укажите корректный email');}
			if($phoneInput!==''&&$phone===''){throw new \InvalidArgumentException('Укажите корректный телефон');}
			if($email===''&&$phone===''){throw new \InvalidArgumentException('Укажите email или телефон');}
			if($adminAccess&&$email===''){throw new \InvalidArgumentException('Для доступа в панель управления укажите email');}
			if(mb_strlen($email)>100||mb_strlen($userName)>50||mb_strlen($firstName)>50||mb_strlen($lastName)>50){throw new \InvalidArgumentException('Имя, email или логин превышают допустимую длину');}
			if($email!==''&&DB::query('SELECT Id FROM '.self::table('users').' WHERE LOWER(email)=LOWER(%s) AND Id!=%i AND deleted!=%s LIMIT 1',$email,$id,'1')->getValue()){throw new \InvalidArgumentException('Этот email уже используется');}
			if($phone!==''&&DB::query('SELECT Id FROM '.self::table('users').' WHERE phone_normalized=%s AND Id!=%i AND deleted!=%s LIMIT 1',$phone,$id,'1')->getValue()){throw new \InvalidArgumentException('Этот телефон уже используется');}
			if(DB::query('SELECT Id FROM '.self::table('users').' WHERE LOWER(user_name)=LOWER(%s) AND Id!=%i AND deleted!=%s LIMIT 1',$userName,$id,'1')->getValue()){throw new \InvalidArgumentException('Этот логин уже используется');}
			$groupIds=array_map(function($item){return (int)$item['id'];},self::groups());
			if(!in_array($group,$groupIds,true)){throw new \InvalidArgumentException('Выберите активную группу публичных пользователей');}
			$authSettings=PublicAuthSettings::all();
			$minimum=max(6,(int)(isset($authSettings['password_min_length'])?$authSettings['password_min_length']:8));
			if($password!==''&&mb_strlen($password)<$minimum){throw new \InvalidArgumentException('Новый пароль: минимум '.$minimum.' символов');}
			if($adminAccess&&!in_array($adminRole,Roles::codes(),true)){throw new \InvalidArgumentException('Выберите роль для доступа в панель управления');}

			$extra=isset($input['extra'])&&is_array($input['extra'])?$input['extra']:array();
			self::validateExtraValues($extra);
			$birthday=trim((string)(isset($input['birthday'])?$input['birthday']:''));
			$birthdayValue=$birthday!==''?strtotime($birthday.' 00:00:00'):0;
			if($birthday!==''&&$birthdayValue===false){throw new \InvalidArgumentException('Укажите корректную дату рождения');}
			$active=!empty($input['status'])?'1':'0';
			$now=time();
			$data=array(
				'email'=>$email!==''?$email:null,'firstname'=>$firstName,'lastname'=>$lastName,'user_name'=>$userName,
				'phone'=>$phone,'phone_normalized'=>$phone!==''?$phone:null,
				'company'=>trim((string)(isset($input['company'])?$input['company']:'')),
				'city'=>trim((string)(isset($input['city'])?$input['city']:'')),
				'street'=>trim((string)(isset($input['street'])?$input['street']:'')),
				'street_nr'=>trim((string)(isset($input['street_nr'])?$input['street_nr']:'')),
				'zipcode'=>trim((string)(isset($input['zipcode'])?$input['zipcode']:'')),
				'birthday'=>$birthdayValue?(int)$birthdayValue:0,
				'description'=>trim((string)(isset($input['description'])?$input['description']:'')),
				'user_group'=>$group,'status'=>$active,
				'email_verified_at'=>$email!==''&&!empty($input['email_verified'])?((int)$current['user']['email_verified_at']?:$now):0,
				'phone_verified_at'=>$phone!==''&&!empty($input['phone_verified'])&&$phone===(string)$current['user']['phone_normalized']?((int)$current['user']['phone_verified_at']?:$now):($phone!==''&&!empty($input['phone_verified'])?$now:0),
			);

			DB::startTransaction();
			try{
				DB::Update(self::table('users'),$data,'Id=%i',$id);
				self::saveExtraValues($id,$extra);
				if($password!==''){(new UserRepository())->setPassword($id,$password);}
				IdentityLinker::setAdminAccess($id,$adminAccess,$adminRole,(int)$currentSystemId);
				if($password!==''||$active!=='1'){self::invalidateCustomerSessions($id);}
				DB::commit();
			}catch(\Throwable $e){DB::rollback();throw $e;}
			return self::customer($id);
		}

		public static function deleteCustomer($id, $currentSystemId = 0)
		{
			$id = (int) $id;
			$current = self::customer($id, $currentSystemId);
			if (!$current) {
				throw new \InvalidArgumentException('Пользователь не найден');
			}

			if (!empty($current['is_current'])) {
				throw new \InvalidArgumentException('Нельзя удалить собственную учётную запись');
			}

			if (!empty($current['system']['is_active'])) {
				throw new \InvalidArgumentException('Сначала отключите пользователю доступ к панели управления');
			}

			DB::startTransaction();
			try {
				DB::Update(self::table('users'), array(
					'status' => '0',
					'deleted' => '1',
					'del_time' => time(),
				), 'Id = %i', $id);
				self::invalidateCustomerSessions($id);
				DB::Delete(self::table('user_identities'), 'user_id = %i', $id);
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				throw $e;
			}

			return true;
		}

		public static function fields()
		{
			$rows = DB::query('SELECT * FROM '.self::table('user_profile_fields').' ORDER BY position,id')->getAll() ?: array();
			$types = self::fieldTypes();
			foreach ($rows as &$row) {
				$type = isset($types[$row['type']]) ? $types[$row['type']] : $types['text'];
				$row['type_label'] = $type['label'];
				$row['type_icon'] = $type['icon'];
				$row['choices'] = self::fieldChoices(isset($row['options']) ? $row['options'] : '');
			}

			unset($row);
			return $rows;
		}

		public static function saveField($id,array $input)
		{
			$id=(int)$id;$code=strtolower(trim((string)(isset($input['code'])?$input['code']:'')));$name=trim((string)(isset($input['name'])?$input['name']:''));$type=(string)(isset($input['type'])?$input['type']:'text');
			if(!preg_match('/^[a-z][a-z0-9_]{1,63}$/',$code)){throw new \InvalidArgumentException('Код: латиница, цифры и подчёркивание');}if($name===''){throw new \InvalidArgumentException('Укажите название поля');}if(!in_array($type,array('text','textarea','select','checkbox','date','number','email','tel'),true)){$type='text';}
			if(DB::query('SELECT id FROM '.self::table('user_profile_fields').' WHERE code=%s AND id!=%i LIMIT 1',$code,$id)->getValue()){throw new \InvalidArgumentException('Поле с таким кодом уже существует');}
			$data=array('code'=>$code,'name'=>$name,'type'=>$type,'options'=>trim((string)(isset($input['options'])?$input['options']:'')),'is_required'=>!empty($input['is_required'])?1:0,'is_active'=>!empty($input['is_active'])?1:0,'show_registration'=>!empty($input['show_registration'])?1:0,'show_profile'=>!empty($input['show_profile'])?1:0,'position'=>max(0,(int)(isset($input['position'])?$input['position']:0)));
			if($id>0){DB::Update(self::table('user_profile_fields'),$data,'id=%i',$id);}else{$data['created_at']=time();DB::Insert(self::table('user_profile_fields'),$data);$id=(int)DB::insertId();}return $id;
		}

		public static function deleteField($id)
		{
			$id=(int)$id;DB::Delete(self::table('user_profile_values'),'field_id=%i',$id);return DB::Delete(self::table('user_profile_fields'),'id=%i',$id);
		}

		public static function toggleField($id)
		{
			$id = (int) $id;
			DB::query('UPDATE '.self::table('user_profile_fields').' SET is_active=IF(is_active=1,0,1) WHERE id=%i', $id);
			return (int) DB::query('SELECT is_active FROM '.self::table('user_profile_fields').' WHERE id=%i', $id)->getValue() === 1;
		}

		public static function reorderFields(array $ids)
		{
			$valid = array();
			foreach ($ids as $id) {
				$id = (int) $id;
				if ($id > 0 && !in_array($id, $valid, true)) { $valid[] = $id; }
			}

			foreach ($valid as $position => $id) {
				DB::Update(self::table('user_profile_fields'), array('position' => $position + 1), 'id=%i', $id);
			}

			return count($valid);
		}

		public static function authSettings()
		{
			$settings = PublicAuthSettings::all();
			$settings['verification_hours'] = max(1, (int) round($settings['verification_ttl'] / 3600));
			$settings['reset_hours'] = max(1, (int) round($settings['reset_ttl'] / 3600));
			return $settings;
		}

		public static function oauthModules()
		{
			$definitions = array(
				array(
					'module' => 'yandex_oauth', 'provider' => 'yandex', 'label' => 'Яндекс',
					'description' => 'Вход и регистрация через аккаунт Яндекса.',
					'url' => '/modules/yandex-oauth/settings', 'permission' => 'view_yandex_oauth',
					'brand' => 'is-yandex', 'mark' => 'Я',
				),
				array(
					'module' => 'vk_oauth', 'provider' => 'vk', 'label' => 'VK ID',
					'description' => 'Вход и регистрация через единую учётную запись VK.',
					'url' => '/modules/vk-oauth/settings', 'permission' => 'view_vk_oauth',
					'brand' => 'is-vk', 'mark' => 'VK',
				),
				array(
					'module' => 'smsc_auth', 'provider' => 'smsc', 'label' => 'SMS',
					'description' => 'Вход и регистрация по одноразовому коду SMSC.',
					'url' => '/modules/smsc-auth/settings', 'permission' => 'view_smsc_auth',
					'brand' => 'is-smsc', 'mark' => 'SMS', 'kind' => 'phone',
				),
			);
			$registered = ProviderRegistry::codes();
			$items = array();
			foreach ($definitions as $definition) {
				$module = ModuleManager::get($definition['module']);
				if (!$module || empty($module['installed'])) { continue; }

				$isPhone = isset($definition['kind']) && $definition['kind'] === 'phone';
				if ($isPhone) {
					$phoneProvider = PhoneProviderRegistry::get($definition['provider']);
					$isRegistered = $phoneProvider !== null;
					$config = class_exists('\\App\\Modules\\SmscAuth\\SecretStore')
						? \App\Modules\SmscAuth\SecretStore::config()
						: array();
					$configured = $isRegistered && $phoneProvider->configured();
				} else {
					$isRegistered = in_array($definition['provider'], $registered, true);
					$config = $isRegistered ? ProviderRegistry::config($definition['provider']) : array();
					$configured = $isRegistered && ProviderRegistry::configured($definition['provider'], $config);
				}

				$active = !empty($module['enabled']) && $configured && !empty($config['enabled']);
				if (empty($module['enabled'])) {
					$state = array('label' => 'Модуль выключен', 'badge' => 'badge-gray');
				} elseif (!$configured) {
					$state = array('label' => 'Нужны ключи', 'badge' => 'badge-amber');
				} elseif (!$active) {
					$state = array('label' => 'Вход выключен', 'badge' => 'badge-gray');
				} else {
					$state = array('label' => 'Вход активен', 'badge' => 'badge-green');
				}

				$items[] = array_merge($definition, array(
					'module_enabled' => !empty($module['enabled']),
					'configured' => $configured,
					'active' => $active,
					'allow_registration' => !empty($config['allow_registration']),
					'link_by_email' => !empty($config['link_by_email']),
					'state' => $state,
				));
			}

			return $items;
		}

		public static function saveAuthSettings(array $input)
		{
			foreach (array('registration_enabled','password_reset_enabled','require_firstname','show_lastname','require_lastname','show_phone','require_phone','show_company','require_company','checkout_registration_enabled') as $key) {
				$input[$key] = !empty($input[$key]) ? 1 : 0;
			}

			$groups = self::groups();
			$groupIds = array_map(function ($group) { return (int) $group['id']; }, $groups);
			$group = isset($input['default_group']) ? (int) $input['default_group'] : 0;
			if (!in_array($group, $groupIds, true)) {
				throw new \InvalidArgumentException('Выберите существующую активную публичную группу');
			}

			if (isset($input['verification_hours'])) { $input['verification_ttl'] = max(1, (int) $input['verification_hours']) * 3600; }
			if (isset($input['reset_hours'])) { $input['reset_ttl'] = max(1, (int) $input['reset_hours']) * 3600; }
			$template = trim((string) (isset($input['checkout_access_template']) ? $input['checkout_access_template'] : ''));
			if ($template === '') { $template = PublicAuthSettings::defaultCheckoutAccessTemplate(); }
			try {
				Twig::twig()->createTemplate($template, 'checkout_access_validate');
			} catch (\Throwable $e) {
				throw new \InvalidArgumentException('Ошибка Twig в письме после заказа: ' . $e->getMessage());
			}

			$input['checkout_access_template'] = $template;
			return PublicAuthSettings::save($input);
		}

		public static function saveAuthPages(array $input)
		{
			$pages = isset($input['pages']) && is_array($input['pages']) ? $input['pages'] : array();
			if (empty($pages)) { throw new \InvalidArgumentException('Настройки страниц не переданы'); }
			$templateIds = array_map(function ($row) { return (int) $row['id']; }, self::pageTemplates());
			foreach (array('login','register','remember','reset','profile','password') as $key) {
				$templateId = isset($pages[$key]['template_id']) ? (int) $pages[$key]['template_id'] : 0;
				if (!in_array($templateId, $templateIds, true)) {
					throw new \InvalidArgumentException('Выберите существующий шаблон сайта для каждой формы.');
				}
			}

			return PublicAuthSettings::save(array('pages' => $pages));
		}

		public static function pageTemplates()
		{
			$rows = DB::query('SELECT Id AS id, template_title AS title FROM ' . ContentTables::table('templates') . ' ORDER BY Id ASC')->getAll();
			return $rows ?: array();
		}

		public static function authFormDefinitions()
		{
			return array(
				'panel' => array('label' => 'Форма в шапке', 'description' => 'Выпадающая авторизация и меню вошедшего пользователя.', 'icon' => 'ti-layout-navbar', 'tile' => 'blue'),
				'login' => array('label' => 'Вход', 'description' => 'Основная страница входа в личный кабинет.', 'icon' => 'ti-login', 'tile' => 'blue'),
				'register' => array('label' => 'Регистрация', 'description' => 'Базовые и дополнительные поля регистрации.', 'icon' => 'ti-user-plus', 'tile' => 'green'),
				'remember' => array('label' => 'Запрос восстановления', 'description' => 'Форма отправки ссылки на email.', 'icon' => 'ti-mail-forward', 'tile' => 'amber'),
				'reset' => array('label' => 'Новый пароль', 'description' => 'Форма из одноразовой ссылки.', 'icon' => 'ti-key', 'tile' => 'red'),
				'overview' => array('label' => 'Обзор кабинета', 'description' => 'Сводка заказов и быстрые переходы пользователя.', 'icon' => 'ti-layout-dashboard', 'tile' => 'blue'),
				'profile' => array('label' => 'Профиль', 'description' => 'Контактные и дополнительные поля пользователя.', 'icon' => 'ti-user-circle', 'tile' => 'violet'),
				'password' => array('label' => 'Смена пароля', 'description' => 'Форма для авторизованного пользователя.', 'icon' => 'ti-lock-cog', 'tile' => 'cyan'),
				'message' => array('label' => 'Системное сообщение', 'description' => 'Результат регистрации, подтверждения и восстановления.', 'icon' => 'ti-message-circle-check', 'tile' => 'green'),
			);
		}

		public static function authForm($key)
		{
			$definitions = self::authFormDefinitions();
			if (!isset($definitions[$key])) { throw new \InvalidArgumentException('Неизвестная форма'); }
			$repository = new FormTemplateRepository();
			return array_merge($definitions[$key], array(
				'key' => $key,
				'template' => $repository->get($key),
				'customized' => $repository->customized($key) ? 1 : 0,
			));
		}

		public static function saveAuthForm($key, $template, $authorId)
		{
			self::authForm($key);
			return (new FormTemplateRepository())->save($key, $template, $authorId);
		}

		public static function resetAuthForm($key)
		{
			self::authForm($key);
			return (new FormTemplateRepository())->reset($key);
		}

		public static function groups()
		{
			$rows = DB::query('SELECT user_group AS id,user_group_name AS name,status,user_group_permission AS permissions FROM '.self::table('user_groups').' ORDER BY user_group ASC')->getAll() ?: array();
			foreach ($rows as &$row) {
				$items = array_filter(array_map('trim', explode('|', (string) $row['permissions'])));
				$row['permissions_count'] = count($items);
			}

			unset($row);
			return array_values(array_filter($rows, function ($row) { return (string) $row['status'] === '1'; }));
		}

		protected static function fieldTypes()
		{
			return array(
				'text' => array('label' => 'Однострочный текст', 'icon' => 'ti-letter-case'),
				'textarea' => array('label' => 'Многострочный текст', 'icon' => 'ti-align-left'),
				'select' => array('label' => 'Список значений', 'icon' => 'ti-list'),
				'checkbox' => array('label' => 'Переключатель', 'icon' => 'ti-toggle-right'),
				'date' => array('label' => 'Дата', 'icon' => 'ti-calendar'),
				'number' => array('label' => 'Число', 'icon' => 'ti-number'),
				'email' => array('label' => 'Email', 'icon' => 'ti-mail'),
				'tel' => array('label' => 'Телефон', 'icon' => 'ti-phone'),
			);
		}

		protected static function validateExtraValues(array $values)
		{
			foreach(self::fields() as $field){
				if(empty($field['is_active'])){continue;}
				$id=(int)$field['id'];
				$value=isset($values[$id])?$values[$id]:'';
				$normalized=(string)$field['type']==='checkbox'?(!empty($value)?'1':'0'):trim(is_array($value)?implode(',',array_map('trim',$value)):(string)$value);
				if(!empty($field['is_required'])&&($normalized===''||((string)$field['type']==='checkbox'&&$normalized!=='1'))){throw new \InvalidArgumentException('Заполните поле «'.(string)$field['name'].'»');}
				if($normalized!==''&&(string)$field['type']==='email'&&!filter_var($normalized,FILTER_VALIDATE_EMAIL)){throw new \InvalidArgumentException('Поле «'.(string)$field['name'].'» должно содержать email');}
				if($normalized!==''&&(string)$field['type']==='number'&&!is_numeric($normalized)){throw new \InvalidArgumentException('Поле «'.(string)$field['name'].'» должно содержать число');}
				if($normalized!==''&&(string)$field['type']==='select'&&!in_array($normalized,$field['choices'],true)){throw new \InvalidArgumentException('Выберите значение поля «'.(string)$field['name'].'» из списка');}
			}
		}

		protected static function saveExtraValues($userId,array $values)
		{
			foreach(self::fields() as $field){
				if(empty($field['is_active'])){continue;}
				$id=(int)$field['id'];
				$value=isset($values[$id])?$values[$id]:'';
				if((string)$field['type']==='checkbox'){$value=!empty($value)?'1':'0';}
				elseif(is_array($value)){$value=implode(',',array_map('trim',$value));}
				else{$value=trim((string)$value);}
				DB::query('INSERT INTO '.self::table('user_profile_values').' (user_id,field_id,value,updated_at) VALUES (%i,%i,%s,%i) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=VALUES(updated_at)',(int)$userId,$id,$value,time());
			}
		}

		protected static function fieldChoices($value)
		{
			$json=json_decode((string)$value,true);
			return is_array($json)?array_values($json):array_values(array_filter(array_map('trim',preg_split('/[\r\n,]+/',(string)$value))));
		}

		protected static function invalidateCustomerSessions($id)
		{
			DB::Delete(self::table('users_session'),'user_id=%i',(int)$id);
			DB::Delete(self::table('auth_tokens'),'user_id=%i',(int)$id);
		}

		protected static function table($suffix){return PublicUserTables::table($suffix);}
	}
