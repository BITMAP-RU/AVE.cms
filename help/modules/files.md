# Файлы модуля

← [Назад к разделу «Модули»](README.md)

Классы модуля живут в неймспейсе `App\Adminx\<Module>\...` и автозагружаются.
Ниже — типовой состав на примере `Notes`.

---

## Меню в `module.php`

Статические пункты хранятся прямо в descriptor.

```php
'admin_extension' => array(
    'menu' => array(array(
            'code'       => 'modules_notes',
            'label'      => 'Заметки',
            'url'        => '/notes',
            'icon'       => 'ti ti-notes',
            'permission' => 'view_notes',   // пункт виден, только если есть право
            'group'      => 'Система',
            'parent'     => 'modules',
            'sort_order' => 9,
            'match'      => array('/notes'), // подсветка активного пункта
    )),
),
```

---

## Права в `module.php`

```php
'permissions' => array(
    'key' => 'notes',
    'items' => array(
            array('code' => 'view_notes',   'group_code' => 'navigation',
                  'name' => 'Заметки: просмотр',    'sort_order' => 10),
            array('code' => 'manage_notes', 'group_code' => 'content',
                  'name' => 'Заметки: управление',  'sort_order' => 20),
    ),
    'icon' => 'ti ti-notes',
    'priority' => 46,
),
```

Конвенция кодов: `view_*` — просмотр, `manage_*` — изменение, спец. (`execute_*`).
См. [Права](../core/permissions.md).

---

## Маршруты в `module.php`

```php
'routes' => array(
    array('GET', '/notes', array(Controller::class, 'index')),
    array('POST', '/notes', array(Controller::class, 'store')),
    array('POST', '/notes/{id}', array(Controller::class, 'update')),
    array('POST', '/notes/{id}/pin', array(Controller::class, 'togglePin')),
    array('POST', '/notes/{id}/delete', array(Controller::class, 'delete')),
),
```

Отдельные `Menu.php`, `Permissions.php` и `routes.php` оправданы только для
динамической или переиспользуемой регистрации. Подробнее —
[Маршрутизация](../core/routing.md).

---

## `Controller.php` — обработчики

Наследует `App\Common\Controller`; каждый метод принимает `array $params`.

```php
namespace App\Adminx\Notes;

use App\Common\Controller as BaseController;
use App\Common\Auth;
use App\Common\Permission;
use App\Helpers\Request;
use App\Helpers\Response;

class Controller extends BaseController
{
    public function index(array $params = array())
    {
        if (!Permission::check('view_notes')) { Response::forbidden(); return ''; }
        return $this->render('@notes/index.twig', array(
            'notes'      => Model::all(Auth::id()),
            'can_manage' => Permission::check('manage_notes'),
        ));
    }

    public function store(array $params = array())
    {
        if (($err = $this->csrfGuard()) !== null) { return $err; }
        if (!Permission::check('manage_notes')) { return $this->error('Недостаточно прав', array(), 403); }
        $note = Model::create(Auth::id(), Request::postStr('title'), Request::postStr('content'));
        return $this->success('Заметка добавлена', array('data' => array('note' => $note)));
    }
}
```

Единый JSON-контракт (`success`/`error`), CSRF, `render` — см.
[Контроллеры](../core/controllers.md).

---

## `Model.php` — данные

Работает с БД через `DB` и резолверы имён таблиц.

```php
namespace App\Adminx\Notes;

use App\Common\SystemTables;

class Model
{
    public static function table() { return SystemTables::table('admin_notes'); }

    public static function all($userId)
    {
        return DB::query(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %i ORDER BY pinned DESC, id DESC',
            (int) $userId
        )->getAll() ?: array();
    }
}
```

Подробнее — [Работа с БД](../database/README.md).

---

## `view/*.twig` — шаблоны

Неймспейс равен `code` модуля: файл `view/index.twig` модуля `notes` доступен как
`@notes/index.twig`. Частичные шаблоны для AJAX кладут в `view/partials/`.

```twig
{% extends '@adminx/main.twig' %}
{% block content %}
  ...
{% endblock %}
```

---

## `assets/*` — стили и скрипты

- LESS собирается в CSS: `notes.less → notes.css` (см. [assets в contributions](contributions.md)).
- JS расширяет глобальный объект `Adminx` (`Adminx.Notes = {...}`) и использует
  `Adminx.Ajax`, `Adminx.Toast`, `Adminx.Confirm`.

```js
(function (window, document) {
  var Adminx = window.Adminx || (window.Adminx = {});
  Adminx.Notes = {
    init: function () { /* делегирование событий, AJAX */ }
  };
  document.addEventListener('DOMContentLoaded', function () { Adminx.Notes.init(); });
})(window, document);
```
