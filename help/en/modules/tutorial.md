# Step by step: your module from scratch

← [Back to the “Modules” section](README.md)

Let's put together a minimal but working module **"Bookmarks"** (`bookmarks`) - a list of personal
admin links. Let's go all the way: folder → descriptor → migrations → routes →
controller/model → template → assets → check.

> Analysis of components - in [module.php](module-php.md), [files.md](files.md),
> [migrations.md](migrations.md), [contributions.md](contributions.md).

---

## 1. Folder

```
adminx/modules/Bookmarks/
├── module.php
├── Controller.php
├── Model.php
├── migrations/
│   ├── 001_create_admin_bookmarks.sql
│   └── uninstall.sql
├── view/
│   └── index.twig
└── assets/
    ├── bookmarks.less
    └── bookmarks.css   (собирается из .less)
```

---

## 2. Rights and menus

We will declare static rights and menu items directly in `module.php` in step 7. For them
no need for separate classes. Provider is written to the file only when it is calculated or
reused logic.

---

## 3. Migrations

`migrations/001_create_admin_bookmarks.sql`:

```sql
CREATE TABLE IF NOT EXISTS `{{prefix}}_admin_bookmarks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL DEFAULT '',
  `url`        VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```



`migrations/uninstall.sql`:

```sql
DROP TABLE IF EXISTS `{{prefix}}_admin_bookmarks`;
```

There is no need to register rights separately via SQL: when installing the manager
synchronizes the `permissions.items` array with the system registry.

---

## 4. Model – `Model.php`

```php
<?php
namespace App\Adminx\Bookmarks;
defined('BASEPATH') || die('Direct access to this location is not allowed.');

use App\Common\SystemTables;
use DB;

class Model
{
    public static function table() { return SystemTables::prefix() . '_admin_bookmarks'; }

    public static function all($userId)
    {
        return DB::query(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %i ORDER BY id DESC',
            (int) $userId
        )->getAll() ?: array();
    }

    public static function create($userId, $title, $url)
    {
        DB::Insert(self::table(), array(
            'user_id'    => (int) $userId,
            'title'      => trim((string) $title),
            'url'        => trim((string) $url),
            'created_at' => time(),
        ));
        return (int) DB::insertId();
    }

    public static function delete($id, $userId)
    {
        DB::Delete(self::table(), 'id = %i AND user_id = %i', (int) $id, (int) $userId);
        return DB::affectedRows() > 0;
    }
}
```

## 5. Controller - `Controller.php`

```php
<?php
namespace App\Adminx\Bookmarks;
defined('BASEPATH') || die('Direct access to this location is not allowed.');

use App\Common\Auth;
use App\Common\Controller as BaseController;
use App\Common\Permission;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Valid;

class Controller extends BaseController
{
    public function index(array $params = array())
    {
        if (!Permission::check('view_bookmarks')) { Response::forbidden(); return ''; }
        return $this->render('@bookmarks/index.twig', array(
            'bookmarks'  => Model::all(Auth::id()),
            'can_manage' => Permission::check('manage_bookmarks'),
        ));
    }

    public function store(array $params = array())
    {
        if (($err = $this->guard()) !== null) { return $err; }
        $errors = Valid::check($_POST, array('title' => 'required', 'url' => 'required|url'));
        if ($errors) { return $this->error('Проверьте поля', $errors); }
        Model::create(Auth::id(), Request::postStr('title'), Request::postStr('url'));
        return $this->success('Закладка добавлена');
    }

    public function delete(array $params = array())
    {
        if (($err = $this->guard()) !== null) { return $err; }
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if (!Model::delete($id, Auth::id())) { return $this->error('Не найдено', array(), 404); }
        return $this->success('Удалено');
    }

    protected function guard()
    {
        if (($err = $this->csrfGuard()) !== null) { return $err; }
        return Permission::check('manage_bookmarks') ? null : $this->error('Недостаточно прав', array(), 403);
    }
}
```

---

## 6. Template - `view/index.twig`

```twig
{% extends '@adminx/main.twig' %}
{% block title %}Закладки{% endblock %}
{% block content %}
<div class="page-header"><div class="between">
  <div><h1>Закладки</h1><p class="text-secondary">Личные ссылки</p></div>
</div></div>

<div class="card ax-list-card">
  <ul>
    {% for b in bookmarks %}
      <li><a href="{{ b.url }}" target="_blank" rel="noopener">{{ b.title }}</a></li>
    {% else %}
      <li class="ax-empty">Пока пусто</li>
    {% endfor %}
  </ul>
</div>
{% endblock %}
```



## 7. `module.php`

```php
<?php
use App\Adminx\Bookmarks\Controller;
defined('BASEPATH') || die('Direct access to this location is not allowed.');

return array(
    'code' => 'bookmarks', 'name' => 'Закладки', 'version' => '1.0.0',
    'description' => 'Личные закладки администратора.', 'author' => 'AVE.cms',
    'lifecycle' => array('managed' => true,
                         'uninstall' => array('migrations/uninstall.sql')),
    'permissions' => array(
        'key' => 'bookmarks',
        'items' => array(
            array('code' => 'view_bookmarks', 'group_code' => 'navigation',
                  'name' => 'Закладки: просмотр', 'sort_order' => 10),
            array('code' => 'manage_bookmarks', 'group_code' => 'content',
                  'name' => 'Закладки: управление', 'sort_order' => 20),
        ),
        'icon' => 'ti ti-bookmark', 'priority' => 48,
    ),
    'routes' => array(
        array('GET', '/bookmarks', array(Controller::class, 'index')),
        array('POST', '/bookmarks', array(Controller::class, 'store')),
        array('POST', '/bookmarks/{id}/delete', array(Controller::class, 'delete')),
    ),
    'migrations' => array(
        array('id' => '001_create_admin_bookmarks', 'file' => 'migrations/001_create_admin_bookmarks.sql'),
    ),
    'assets' => array(
        'styles' => array(array('url' => ADMINX_BASE . '/modules/Bookmarks/assets/bookmarks.css', 'priority' => 48)),
    ),
    'admin_extension' => array(
        'url' => '/bookmarks', 'icon' => 'ti ti-bookmark',
        'feature' => 'Личные закладки',
        'menu' => array(array(
            'code' => 'modules_bookmarks', 'label' => 'Закладки', 'url' => '/bookmarks',
            'icon' => 'ti ti-bookmark', 'permission' => 'view_bookmarks',
            'group' => 'Система', 'parent' => 'modules', 'sort_order' => 12,
            'match' => array('/bookmarks'),
        )),
    ),
);
```

---

## 8. Collect styles and check

```bash
# LESS → CSS (даже пустой файл нужно собрать, чтобы .css существовал)
./node_modules/.bin/lessc adminx/modules/Bookmarks/assets/bookmarks.less \
                          adminx/modules/Bookmarks/assets/bookmarks.css

# синтаксис PHP
php -l adminx/modules/Bookmarks/Controller.php
```

Next:
1. Open **System → Modules** and install the detected `bookmarks`.
2. Give the role the rights `view_bookmarks` / `manage_bookmarks` in “Roles and Rights”.
3. Make sure that “Bookmarks” appears in the menu and POST works via Ajax.
4. On a test copy, check switching off, turning on and uninstalling.

---

## Readiness checklist

- [ ] `code` is unique and matches the class namespace (`App\Adminx\Bookmarks`).
- [ ] The table name is built from `SystemTables::prefix()`, the prefix is ​​not hardcoded.
- [ ] Migrations are idempotent (`IF NOT EXISTS`, `ON DUPLICATE KEY UPDATE`).
- [ ] Each POST action: `csrfGuard()` + `Permission::check()`.
- [ ] Rights separated `view_*` / `manage_*`.
- [ ] LESS compiled in CSS; JS (if any) does not use native `confirm/alert`.
- [ ] There is `uninstall.sql` for tables belonging to the module; the rights will be removed by the manager.
- [ ] Installation, shutdown, update and uninstallation checked through the control panel.
