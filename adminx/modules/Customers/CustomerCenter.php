<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/CustomerCenter.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Customers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;
	use App\Adminx\Support\AdminLocale;
	use App\Content\BasketTables;
	use App\Content\ContactsTables;
	use App\Content\PublicUserTables;
	use DB;

	/** Read model and safe merge operations for the customer workspace. */
	class CustomerCenter
	{
		public static function listing($query = '', $segment = 'all', $limit = 300)
		{
			$query = mb_substr(trim((string) $query), 0, 190, 'UTF-8');
			$segment = isset(self::segments()[$segment]) ? $segment : 'all';
			$limit = max(1, min(500, (int) $limit));
			$users = PublicUserTables::table('users');
			$orders = BasketTables::table('module_basket_history');
			$hasOrders = DatabaseSchema::tableExists($orders);
			$orderJoin = $hasOrders
				? ' LEFT JOIN (SELECT order_user_id,COUNT(*) orders_count,SUM(order_total) orders_total,MAX(order_published) last_order_at'
					. ' FROM ' . $orders . ' WHERE order_user_id>0 GROUP BY order_user_id) orders ON orders.order_user_id=u.Id'
				: '';
			$sql = 'SELECT u.Id AS id,u.email,u.firstname,u.lastname,u.user_name,u.phone,u.company,u.status,u.reg_time,u.last_visit,'
				. ($orderJoin !== '' ? 'COALESCE(orders.orders_count,0)' : '0') . ' orders_count,'
				. ($orderJoin !== '' ? 'COALESCE(orders.orders_total,0)' : '0') . ' orders_total,'
				. ($orderJoin !== '' ? 'COALESCE(orders.last_order_at,0)' : '0') . ' last_order_at'
				. ' FROM ' . $users . ' u' . $orderJoin . ' WHERE u.deleted!=%s';
			$args = array('1');
			if ($query !== '') {
				$sql .= ' AND (u.email LIKE %ss OR u.firstname LIKE %ss OR u.lastname LIKE %ss OR u.user_name LIKE %ss OR u.phone LIKE %ss OR u.company LIKE %ss';
				$args = array_merge($args, array($query, $query, $query, $query, $query, $query));
				if (ctype_digit($query)) { $sql .= ' OR u.Id=%i'; $args[] = (int) $query; }
				$sql .= ')';
			}

			$now = time();
			if ($segment === 'new') { $sql .= ' AND u.reg_time>=%i'; $args[] = $now - 30 * 86400; }
			elseif ($segment === 'buyers') { $sql .= $hasOrders ? ' AND COALESCE(orders.orders_count,0)>0' : ' AND 1=0'; }
			elseif ($segment === 'repeat') { $sql .= $hasOrders ? ' AND COALESCE(orders.orders_count,0)>=2' : ' AND 1=0'; }
			elseif ($segment === 'vip') { $sql .= $hasOrders ? ' AND COALESCE(orders.orders_total,0)>=100000' : ' AND 1=0'; }
			elseif ($segment === 'inactive') { $sql .= ' AND (u.last_visit=0 OR u.last_visit<%i)'; $args[] = $now - 180 * 86400; }
			elseif ($segment === 'without_orders' && $hasOrders) { $sql .= ' AND COALESCE(orders.orders_count,0)=0'; }
			$sql .= ' ORDER BY orders_total DESC,last_order_at DESC,u.Id DESC LIMIT ' . $limit;
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
			foreach ($rows as &$row) {
				$row['id'] = (int) $row['id'];
				$row['orders_count'] = (int) $row['orders_count'];
				$row['orders_total'] = (float) $row['orders_total'];
				$row['last_order_at'] = (int) $row['last_order_at'];
				$row['segment_codes'] = self::segmentCodes($row);
			}

			unset($row);
			return $rows;
		}

		public static function stats()
		{
			$users = PublicUserTables::table('users');
			$orders = BasketTables::table('module_basket_history');
			$result = array(
				'total' => (int) DB::query('SELECT COUNT(*) FROM ' . $users . ' WHERE deleted!=%s', '1')->getValue(),
				'buyers' => 0, 'repeat' => 0, 'duplicates' => count(self::duplicateGroups()),
			);
			if (DatabaseSchema::tableExists($orders)) {
				$result['buyers'] = (int) DB::query('SELECT COUNT(DISTINCT order_user_id) FROM ' . $orders . ' WHERE order_user_id>0')->getValue();
				$result['repeat'] = (int) DB::query('SELECT COUNT(*) FROM (SELECT order_user_id FROM ' . $orders . ' WHERE order_user_id>0 GROUP BY order_user_id HAVING COUNT(*)>=2) repeated')->getValue();
			}

			return $result;
		}

		public static function segments()
		{
			$segments = array('all' => 'Все', 'new' => 'Новые', 'buyers' => 'С заказами', 'repeat' => 'Повторные', 'vip' => 'От 100 000 ₽', 'inactive' => 'Неактивные', 'without_orders' => 'Без заказов');
			foreach ($segments as &$label) { $label = AdminLocale::translateMarkup($label); }
			unset($label);
			return $segments;
		}

		public static function detail($id, $currentSystemId = 0)
		{
			$id = (int) $id;
			$profile = Model::customer($id, $currentSystemId);
			if (!$profile) { return null; }
			$orderData = self::orderData(
				$id,
				isset($profile['user']['email']) ? $profile['user']['email'] : '',
				isset($profile['user']['phone']) ? $profile['user']['phone'] : ''
			);
			$profile['summary'] = $orderData['summary'];
			$profile['orders'] = $orderData['orders'];
			$profile['contacts'] = self::contacts(isset($profile['user']['email']) ? $profile['user']['email'] : '');
			$profile['popup_leads'] = self::popupLeads(
				isset($profile['user']['email']) ? $profile['user']['email'] : '',
				isset($profile['user']['phone']) ? $profile['user']['phone'] : ''
			);
			$profile['quiz_leads'] = self::quizLeads(
				isset($profile['user']['email']) ? $profile['user']['email'] : '',
				isset($profile['user']['phone']) ? $profile['user']['phone'] : ''
			);
			$profile['identities'] = self::identities($id);
			$profile['engagement'] = self::engagement($id);
			$profile['notes'] = self::notes($id);
			$profile['segments'] = self::segmentCodes(array_merge($profile['user'], $orderData['summary']));
			return $profile;
		}

		public static function addNote($userId, $authorId, $text)
		{
			$userId = (int) $userId; $authorId = (int) $authorId;
			$text = trim((string) $text);
			if (!Model::customer($userId)) { throw new \InvalidArgumentException('Пользователь не найден'); }
			if ($text === '') { throw new \InvalidArgumentException('Введите текст заметки'); }
			if (mb_strlen($text, 'UTF-8') > 4000) { throw new \InvalidArgumentException('Заметка не должна превышать 4000 символов'); }
			if (!DatabaseSchema::tableExists(self::notesTable())) { throw new \RuntimeException('Примените миграцию центра покупателей'); }
			DB::Insert(self::notesTable(), array('user_id' => $userId, 'author_id' => $authorId, 'note' => $text, 'created_at' => time()));
			return (int) DB::insertId();
		}

		public static function merge($targetId, $sourceId, $actorSystemId = 0)
		{
			$targetId = (int) $targetId; $sourceId = (int) $sourceId;
			if ($targetId <= 0 || $sourceId <= 0 || $targetId === $sourceId) { throw new \InvalidArgumentException('Выберите два разных аккаунта'); }
			$target = Model::customer($targetId, $actorSystemId); $source = Model::customer($sourceId, $actorSystemId);
			if (!$target || !$source) { throw new \InvalidArgumentException('Один из аккаунтов не найден'); }
			if (!empty($source['is_current']) || !empty($source['system'])) { throw new \InvalidArgumentException('Нельзя объединить аккаунт, связанный с доступом в панель'); }
			self::assertIdentityMerge($targetId, $sourceId);
			DB::startTransaction();
			try {
				$sourceUnique = array('email' => null);
				if (DatabaseSchema::columnExists(PublicUserTables::table('users'), 'phone_normalized')) { $sourceUnique['phone_normalized'] = null; }
				DB::Update(PublicUserTables::table('users'), $sourceUnique, 'Id=%i', $sourceId);
				self::mergeCoreProfile($target['user'], $source['user']);
				self::mergeProfileValues($targetId, $sourceId);
				self::moveOptionalData($targetId, $sourceId);
				$identities = PublicUserTables::table('user_identities');
				if (DatabaseSchema::tableExists($identities)) { DB::Update($identities, array('user_id' => $targetId, 'updated_at' => time()), 'user_id=%i', $sourceId); }
				if (DatabaseSchema::tableExists(self::notesTable())) { DB::Update(self::notesTable(), array('user_id' => $targetId), 'user_id=%i', $sourceId); }
				self::addNote($targetId, $actorSystemId, 'Объединён аккаунт #' . $sourceId . '. Контакты источника: ' . trim((string) $source['user']['email'] . ' ' . (string) $source['user']['phone']));
				DB::Update(PublicUserTables::table('users'), array('status' => '0', 'deleted' => '1', 'del_time' => time()), 'Id=%i', $sourceId);
				DB::Delete(PublicUserTables::table('users_session'), 'user_id=%i', $sourceId);
				DB::Delete(PublicUserTables::table('auth_tokens'), 'user_id=%i', $sourceId);
				DB::commit();
			} catch (\Throwable $e) { DB::rollback(); throw $e; }
			return self::detail($targetId, $actorSystemId);
		}

		public static function duplicateGroups()
		{
			$table = PublicUserTables::table('users'); $groups = array();
			$queries = array(
				'email' => "SELECT LOWER(TRIM(email)) duplicate_value,GROUP_CONCAT(Id ORDER BY Id) ids,COUNT(*) amount FROM $table WHERE deleted!='1' AND email!='' GROUP BY LOWER(TRIM(email)) HAVING COUNT(*)>1 LIMIT 30",
			);
			if (DatabaseSchema::columnExists($table, 'phone_normalized')) {
				$queries['phone'] = "SELECT phone_normalized duplicate_value,GROUP_CONCAT(Id ORDER BY Id) ids,COUNT(*) amount FROM $table WHERE deleted!='1' AND phone_normalized IS NOT NULL GROUP BY phone_normalized HAVING COUNT(*)>1 LIMIT 30";
			}

			foreach ($queries as $kind => $sql) {
				foreach (DB::query($sql)->getAll() ?: array() as $row) {
					$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $row['ids'])))));
					if (count($ids) > 1) { $groups[] = array('kind' => $kind, 'value' => (string) $row['duplicate_value'], 'ids' => $ids, 'target_id' => min($ids)); }
				}
			}

			return $groups;
		}

		/**
		 * Заказы человека: свои по аккаунту плюс гостевые по контактам.
		 *
		 * Оформить заказ можно без входа (`order_user_id = 0`), и такие покупки
		 * раньше не попадали на карточку вовсе. Ищем их так же, как обращения и
		 * заявки — по email и телефону, — но помечаем `matched_by`, чтобы
		 * догадка не выдавалась за подтверждённую принадлежность аккаунту.
		 */
		protected static function orderData($userId, $email = '', $phone = '')
		{
			$table = BasketTables::table('module_basket_history');
			$empty = array('summary' => array('orders_count' => 0, 'orders_total' => 0, 'last_order_at' => 0, 'guest_count' => 0), 'orders' => array());
			if (!DatabaseSchema::tableExists($table)) { return $empty; }

			$where = array('order_user_id=%i');
			$args = array((int) $userId);
			$email = trim((string) $email);
			if ($email !== '') { $where[] = '(order_user_id=0 AND LOWER(order_email)=LOWER(%s))'; $args[] = $email; }
			$digits = preg_replace('/\D+/', '', (string) $phone);
			if (strlen($digits) >= 10) {
				$where[] = "(order_user_id=0 AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(order_phone,' ',''),'(',''),')',''),'-',''),'+',''),10)=%s)";
				$args[] = substr($digits, -10);
			}

			$condition = '(' . implode(' OR ', $where) . ')';
			$summaryArgs = array_merge(array(
				'SELECT COUNT(*) orders_count,COALESCE(SUM(order_total),0) orders_total,COALESCE(MAX(order_published),0) last_order_at,'
					. ' COALESCE(SUM(order_user_id=0),0) guest_count FROM ' . $table . ' WHERE ' . $condition,
			), $args);
			$summary = call_user_func_array(array(DB::class, 'query'), $summaryArgs)->getAssoc() ?: array();
			$listArgs = array_merge(array(
				'SELECT id,order_id,order_total,order_status,order_pay,order_published,order_user_id FROM ' . $table
					. ' WHERE ' . $condition . ' ORDER BY order_published DESC,id DESC LIMIT 20',
			), $args);
			$rows = call_user_func_array(array(DB::class, 'query'), $listArgs)->getAll() ?: array();
			foreach ($rows as &$row) {
				$row['id'] = (int) $row['id'];
				$row['order_total'] = (float) $row['order_total'];
				$row['order_published'] = (int) $row['order_published'];
				$row['matched_by'] = (int) $row['order_user_id'] > 0 ? 'account' : 'contact';
			}

			unset($row);
			return array('summary' => array(
				'orders_count' => isset($summary['orders_count']) ? (int) $summary['orders_count'] : 0,
				'orders_total' => isset($summary['orders_total']) ? (float) $summary['orders_total'] : 0,
				'last_order_at' => isset($summary['last_order_at']) ? (int) $summary['last_order_at'] : 0,
				'guest_count' => isset($summary['guest_count']) ? (int) $summary['guest_count'] : 0,
			), 'orders' => $rows);
		}

		protected static function contacts($email)
		{
			$table = ContactsTables::table('module_contacts_history'); $email = trim((string) $email);
			if ($email === '' || !DatabaseSchema::tableExists($table)) { return array(); }
			return DB::query('SELECT id,form_id,subject,status,date FROM ' . $table . ' WHERE LOWER(email)=LOWER(%s) ORDER BY date DESC,id DESC LIMIT 20', $email)->getAll() ?: array();
		}

		/**
		 * Заявки из поп-апов сопоставляются по email или телефону.
		 * Модуль устанавливается отдельно, поэтому отсутствие таблицы —
		 * обычное состояние, а не ошибка.
		 */
		protected static function popupLeads($email, $phone)
		{
			if (!class_exists('App\\Modules\\Popups\\Repository')) { return array(); }
			try {
				if (!DatabaseSchema::tableExists(\App\Modules\Popups\Tables::table('leads'))) { return array(); }
				return \App\Modules\Popups\Repository::leadsForContact($email, $phone, 20);
			} catch (\Throwable $e) {
				return array();
			}
		}

		/** Заявки из пошагового подбора — тот же принцип, что и у поп-апов. */
		protected static function quizLeads($email, $phone)
		{
			if (!class_exists('App\\Modules\\Quiz\\Repository')) { return array(); }
			try { return \App\Modules\Quiz\Repository::leadsForContact($email, $phone, 20); }
			catch (\Throwable $e) { return array(); }
		}

		protected static function identities($userId)
		{
			$table = PublicUserTables::table('user_identities');
			return DatabaseSchema::tableExists($table) ? (DB::query('SELECT provider,email,created_at,last_login_at FROM ' . $table . ' WHERE user_id=%i ORDER BY provider', (int) $userId)->getAll() ?: array()) : array();
		}

		protected static function engagement($userId)
		{
			$result = array('favorites' => array(), 'viewed' => array());
			$favorites = BasketTables::table('module_basket_favorites');
			if (DatabaseSchema::tableExists($favorites)) { $result['favorites'] = array_map('intval', array_column(DB::query('SELECT fav_document_id FROM ' . $favorites . ' WHERE fav_user_id=%i ORDER BY fav_created_at DESC LIMIT 30', (int) $userId)->getAll() ?: array(), 'fav_document_id')); }
			$viewed = BasketTables::table('module_basket_viewed');
			if (DatabaseSchema::tableExists($viewed)) { $result['viewed'] = array_map('intval', array_column(DB::query('SELECT viewed_document_id FROM ' . $viewed . ' WHERE viewed_user_id=%i ORDER BY viewed_at DESC LIMIT 30', (int) $userId)->getAll() ?: array(), 'viewed_document_id')); }
			return $result;
		}

		protected static function notes($userId)
		{
			if (!DatabaseSchema::tableExists(self::notesTable())) { return array(); }
			return DB::query('SELECT n.*,u.name author_name FROM ' . self::notesTable() . ' n LEFT JOIN ' . SystemTables::table('users') . ' u ON u.id=n.author_id WHERE n.user_id=%i ORDER BY n.created_at DESC,n.id DESC LIMIT 100', (int) $userId)->getAll() ?: array();
		}

		protected static function segmentCodes(array $row)
		{
			$codes = array(); $now = time();
			if (!empty($row['reg_time']) && (int) $row['reg_time'] >= $now - 30 * 86400) { $codes[] = 'new'; }
			if (!empty($row['orders_count'])) { $codes[] = 'buyers'; }
			if (isset($row['orders_count']) && (int) $row['orders_count'] >= 2) { $codes[] = 'repeat'; }
			if (isset($row['orders_total']) && (float) $row['orders_total'] >= 100000) { $codes[] = 'vip'; }
			if (empty($row['last_visit']) || (int) $row['last_visit'] < $now - 180 * 86400) { $codes[] = 'inactive'; }
			if (empty($row['orders_count'])) { $codes[] = 'without_orders'; }
			return $codes;
		}

		protected static function assertIdentityMerge($targetId, $sourceId)
		{
			$table = PublicUserTables::table('user_identities');
			if (!DatabaseSchema::tableExists($table)) { return; }
			$conflict = DB::query('SELECT source.provider FROM ' . $table . ' source INNER JOIN ' . $table . ' target ON target.provider=source.provider AND target.user_id=%i WHERE source.user_id=%i LIMIT 1', (int) $targetId, (int) $sourceId)->getValue();
			if ($conflict) { throw new \InvalidArgumentException('У обоих аккаунтов подключён один способ входа: ' . $conflict); }
		}

		protected static function mergeProfileValues($targetId, $sourceId)
		{
			$table = PublicUserTables::table('user_profile_values');
			if (!DatabaseSchema::tableExists($table)) { return; }
			foreach (DB::query('SELECT field_id,value FROM ' . $table . ' WHERE user_id=%i', (int) $sourceId)->getAll() ?: array() as $row) {
				$current = DB::query('SELECT value FROM ' . $table . ' WHERE user_id=%i AND field_id=%i', (int) $targetId, (int) $row['field_id'])->getValue();
				if ($current === null) { DB::Insert($table, array('user_id' => $targetId, 'field_id' => (int) $row['field_id'], 'value' => (string) $row['value'], 'updated_at' => time())); }
				elseif (trim((string) $current) === '' && trim((string) $row['value']) !== '') { DB::Update($table, array('value' => (string) $row['value'], 'updated_at' => time()), 'user_id=%i AND field_id=%i', $targetId, (int) $row['field_id']); }
			}

			DB::Delete($table, 'user_id=%i', $sourceId);
		}

		protected static function mergeCoreProfile(array $target, array $source)
		{
			$fields = array('email', 'phone', 'phone_normalized', 'firstname', 'lastname', 'street', 'street_nr', 'zipcode', 'city', 'telefax', 'description', 'company', 'birthday', 'country');
			$values = array();
			foreach ($fields as $field) {
				if (trim(isset($target[$field]) ? (string) $target[$field] : '') === '' && trim(isset($source[$field]) ? (string) $source[$field] : '') !== '') {
					$values[$field] = $source[$field];
				}
			}

			$targetEmail = strtolower(trim(isset($target['email']) ? (string) $target['email'] : ''));
			$sourceEmail = strtolower(trim(isset($source['email']) ? (string) $source['email'] : ''));
			if ($targetEmail === '' || ($targetEmail !== '' && $targetEmail === $sourceEmail)) {
				$values['email_verified_at'] = max(isset($target['email_verified_at']) ? (int) $target['email_verified_at'] : 0, isset($source['email_verified_at']) ? (int) $source['email_verified_at'] : 0);
			}

			$targetPhone = trim(isset($target['phone_normalized']) ? (string) $target['phone_normalized'] : '');
			$sourcePhone = trim(isset($source['phone_normalized']) ? (string) $source['phone_normalized'] : '');
			if ($targetPhone === '' || ($targetPhone !== '' && $targetPhone === $sourcePhone)) {
				$values['phone_verified_at'] = max(isset($target['phone_verified_at']) ? (int) $target['phone_verified_at'] : 0, isset($source['phone_verified_at']) ? (int) $source['phone_verified_at'] : 0);
			}

			if ($values) { DB::Update(PublicUserTables::table('users'), $values, 'Id=%i', (int) $target['id']); }
		}

		protected static function moveOptionalData($targetId, $sourceId)
		{
			$orders = BasketTables::table('module_basket_history');
			if (DatabaseSchema::tableExists($orders)) { DB::Update($orders, array('order_user_id' => $targetId), 'order_user_id=%i', $sourceId); }
			$favorites = BasketTables::table('module_basket_favorites');
			if (DatabaseSchema::tableExists($favorites)) {
				DB::query('INSERT IGNORE INTO ' . $favorites . ' (fav_user_id,fav_document_id,fav_created_at) SELECT %i,fav_document_id,fav_created_at FROM ' . $favorites . ' WHERE fav_user_id=%i', $targetId, $sourceId);
				DB::Delete($favorites, 'fav_user_id=%i', $sourceId);
			}

			$viewed = BasketTables::table('module_basket_viewed');
			if (DatabaseSchema::tableExists($viewed)) {
				DB::query('INSERT INTO ' . $viewed . ' (viewed_user_id,viewed_document_id,viewed_at) SELECT %i,source.viewed_document_id,source.viewed_at FROM ' . $viewed . ' source WHERE source.viewed_user_id=%i ON DUPLICATE KEY UPDATE viewed_at=GREATEST(' . $viewed . '.viewed_at,VALUES(viewed_at))', $targetId, $sourceId);
				DB::Delete($viewed, 'viewed_user_id=%i', $sourceId);
			}
		}

		protected static function notesTable()
		{
			return SystemTables::prefix() . '_customer_notes';
		}
	}
