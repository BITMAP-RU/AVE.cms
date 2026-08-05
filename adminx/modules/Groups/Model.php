<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Groups/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Groups;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\Language;
	use App\Common\ModuleManager;
	use App\Common\SystemTables;

	/** Роли и их права (RBAC) поверх {prefix}_roles / _role_permissions / _permissions. */
	class Model
	{
		protected static function roles()        { return SystemTables::table('roles'); }
		protected static function rolePerms()    { return SystemTables::table('role_permissions'); }
		protected static function permissions()  { return SystemTables::table('permissions'); }
		protected static function users()        { return SystemTables::table('users'); }

		/** Список ролей со счётчиками пользователей и прав. */
		public static function all()
		{
			return DB::query(
				'SELECT r.id, r.code, r.name, r.is_system,
                (SELECT COUNT(*) FROM ' . self::users() . ' u WHERE u.role = r.code) AS user_count,
                (SELECT COUNT(*) FROM ' . self::rolePerms() . ' rp WHERE rp.role_id = r.id) AS perm_count
             FROM ' . self::roles() . ' r
             ORDER BY r.is_system DESC, r.name ASC'
			)->getAll();
		}

		public static function find($id)
		{
			return DB::query('SELECT * FROM ' . self::roles() . ' WHERE id = %i LIMIT 1', (int) $id)->getObject();
		}

		/** Сводка для карточек: всего ролей / системных / всего прав. */
		public static function stats()
		{
			return [
				'roles'       => (int) DB::query('SELECT COUNT(*) FROM ' . self::roles())->getValue(),
				'system'      => (int) DB::query('SELECT COUNT(*) FROM ' . self::roles() . ' WHERE is_system = 1')->getValue(),
				'permissions' => (int) DB::query('SELECT COUNT(*) FROM ' . self::permissions())->getValue(),
			];
		}

		public static function codeExists($code, $exceptId = 0)
		{
			return (bool) DB::query(
				'SELECT id FROM ' . self::roles() . ' WHERE code = %s AND id != %i LIMIT 1',
				(string) $code, (int) $exceptId
			)->getValue();
		}

		public static function usersCount($code)
		{
			return (int) DB::query('SELECT COUNT(*) FROM ' . self::users() . ' WHERE role = %s', (string) $code)->getValue();
		}

		/** Коды прав, назначенных роли. */
		public static function permissionCodes($roleId)
		{
			$rows = DB::query('SELECT permission_code FROM ' . self::rolePerms() . ' WHERE role_id = %i', (int) $roleId)->getAll();
			$out = [];
			foreach ($rows ?: [] as $row) {
				$out[] = (string) ((array) $row)['permission_code'];
			}

			return $out;
		}

		/** Все зарегистрированные права, сгруппированные по модулю. */
		public static function allPermissionsGrouped()
		{
			$rows = DB::query(
				'SELECT code, module, group_code, name, description FROM ' . self::permissions() . ' ORDER BY module ASC, sort_order ASC, code ASC'
			)->getAll();

			$groups = [];
			foreach ($rows ?: [] as $row) {
				$row = (array) $row;
				$row['name'] = Language::translateSource(isset($row['name']) ? $row['name'] : '');
				$row['description'] = Language::translateSource(isset($row['description']) ? $row['description'] : '');
				$mod = $row['module'] !== '' ? $row['module'] : 'other';
				$groups[$mod][] = $row;
			}

			$labels = self::permissionModuleLabels();
			uksort($groups, function ($left, $right) use ($labels) {
				$leftLabel = isset($labels[$left]) ? $labels[$left] : $left;
				$rightLabel = isset($labels[$right]) ? $labels[$right] : $right;
				return strnatcasecmp($leftLabel, $rightLabel);
			});

			return $groups;
		}

		public static function permissionModuleLabels()
		{
			$labels = array(
				'admin' => 'Ядро',
				'antispam' => 'Антиспам',
				'auth' => 'Доступ',
				'benchmark' => 'Производительность',
				'blocks' => 'Блоки',
				'catalog' => 'Каталог',
				'commerce' => 'Корзина и заказы',
				'console' => 'PHP-консоль',
				'contacts' => 'Контактные формы',
				'customers' => 'Пользователи сайта',
				'dashboard' => 'Дашборд',
				'database' => 'База данных',
				'document_jobs' => 'Операции с документами',
				'documents' => 'Документы',
				'events' => 'Системные события',
				'feeds' => 'Товарные фиды',
				'file_security' => 'Безопасность файлов',
				'groups' => 'Роли и права',
				'help' => 'Справка',
				'interactions' => 'Взаимодействия',
				'media' => 'Медиа',
				'modules' => 'Модули',
				'navigation' => 'Навигация',
				'notfound' => 'Ошибки 404',
				'other' => 'Прочее',
				'products' => 'Товары',
				'ratings' => 'Рейтинги',
				'related' => 'Похожие документы',
				'requests' => 'Запросы',
				'rss' => 'RSS-каналы',
				'rubrics' => 'Рубрики и поля',
				'search' => 'Поиск по сайту',
				'security' => 'Безопасность',
				'seoaudit' => 'SEO-аудит',
				'settings' => 'Настройки',
				'site_readiness' => 'Готовность сайта',
				'system_health' => 'Мониторинг здоровья',
				'templates' => 'Шаблоны',
				'todo' => 'Задачи Todo',
				'updates' => 'Обновления',
				'users' => 'Пользователи',
				'vk_oauth' => 'Вход через VK ID',
				'yandex_oauth' => 'Вход через Яндекс',
			);
			foreach (ModuleManager::all() as $module) {
				$code = isset($module['code']) ? trim((string) $module['code']) : '';
				$name = isset($module['name']) ? trim((string) $module['name']) : '';
				if ($code !== '' && $name !== '' && !isset($labels[$code])) { $labels[$code] = $name; }
			}

			foreach ($labels as $code => $label) {
				$labels[$code] = Language::translateSource($label);
			}

			return $labels;
		}

		/** Все валидные коды прав (для фильтрации входящего набора). */
		public static function validCodes()
		{
			$rows = DB::query('SELECT code FROM ' . self::permissions())->getAll();
			$out = [];
			foreach ($rows ?: [] as $row) {
				$out[] = (string) ((array) $row)['code'];
			}

			return $out;
		}

		public static function create($code, $name)
		{
			$now = date('Y-m-d H:i:s');
			DB::Insert(self::roles(), [
				'code'       => (string) $code,
				'name'       => (string) $name,
				'is_system'  => 0,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			return DB::insertId();
		}

		public static function update($id, $name)
		{
			DB::Update(self::roles(), [
				'name'       => (string) $name,
				'updated_at' => date('Y-m-d H:i:s'),
			], 'id = %i', (int) $id);
		}

		/** Переписать набор прав роли (только валидные коды). */
		public static function setPermissions($roleId, array $codes)
		{
			$roleId = (int) $roleId;
			$valid = array_flip(self::validCodes());

			DB::Delete(self::rolePerms(), 'role_id = %i', $roleId);

			foreach (array_unique($codes) as $code) {
				$code = trim((string) $code);
				if ($code === '' || !isset($valid[$code])) {
					continue;
				}

				DB::query(
					'INSERT IGNORE INTO ' . self::rolePerms() . ' (role_id, permission_code) VALUES (%i, %s)',
					$roleId, $code
				);
			}
		}

		/**
		 * Копия роли вместе с набором прав.
		 *
		 * Новая роль всегда обычная: системный признак не наследуется, иначе
		 * копию нельзя было бы ни переименовать, ни удалить. Пользователи в
		 * копию не переносятся — их назначают отдельно.
		 */
		public static function copy($id, $code, $name)
		{
			$source = self::find($id);
			if (!$source) { throw new \RuntimeException('Роль не найдена'); }

			//-- Без явного режима исключений ошибка уникальности кода оборвала бы
			//-- выполнение мимо rollback и оставила роль без прав.
			$previousMode = DB::$throw_exception_on_error;
			DB::$throw_exception_on_error = true;
			$newId = 0;
			DB::startTransaction();
			try {
				$newId = (int) self::create($code, $name);
				if ($newId < 1) { throw new \RuntimeException('Не удалось создать копию роли'); }
				self::setPermissions($newId, self::permissionCodes((int) $source->id));
				DB::commit();
			} catch (\Throwable $e) {
				if (DB::$transaction_in_progress) { DB::rollback(); }
				throw $e;
			} finally {
				DB::$throw_exception_on_error = $previousMode;
			}

			return $newId;
		}

		/** Свободный код вида role_copy, role_copy_2 — на случай повторных копий. */
		public static function freeCode($base)
		{
			$base = trim(preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) $base)), '_');
			if ($base === '') { $base = 'role'; }
			$base = substr($base, 0, 40) . '_copy';
			$candidate = $base;
			$suffix = 2;
			while (self::codeExists($candidate) && $suffix < 100) {
				$candidate = $base . '_' . $suffix;
				$suffix++;
			}

			return $candidate;
		}

		public static function delete($id)
		{
			$id = (int) $id;
			DB::Delete(self::rolePerms(), 'role_id = %i', $id);
			DB::Delete(self::roles(), 'id = %i', $id);
		}
	}
