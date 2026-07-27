# Module files

← [Back to the “Modules” section](README.md)

The module classes live in the namespace `App\Adminx\<Module>\...` and are autoloaded.
Below is a typical composition using `Notes` as an example.

---

## Menu in `module.php`

Static items are stored directly in the descriptor.

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

## Rights in `module.php`

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

Code convention: `view_*` - view, `manage_*` - change, special. (`execute_*`).
See [Permissions](../core/permissions.md).

---

## Routes in `module.php`

```php
'routes' => array(
    array('GET', '/notes', array(Controller::class, 'index')),
    array('POST', '/notes', array(Controller::class, 'store')),
    array('POST', '/notes/{id}', array(Controller::class, 'update')),
    array('POST', '/notes/{id}/pin', array(Controller::class, 'togglePin')),
    array('POST', '/notes/{id}/delete', array(Controller::class, 'delete')),
),
```

Separate `Menu.php`, `Permissions.php` and `routes.php` are justified only for
dynamic or reusable registration. More details -
[Routing](../core/routing.md).

---

## `Controller.php` - handlers

Inherits `App\Common\Controller`; each method accepts `array $params`.

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

Single JSON contract (`success`/`error`), CSRF, `render` - see.
[Controllers](../core/controllers.md).

---

## `Model.php` - data

Works with the database via `DB` and table name resolvers.

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

More details - [Working with the database](../database/README.md).

---

## `view/*.twig` - templates

The namespace is `code` of the module: the file `view/index.twig` of the `notes` module is available as
`@notes/index.twig`. Partial templates for AJAX are placed in `view/partials/`.

```twig
{% extends '@adminx/main.twig' %}
{% block content %}
  ...
{% endblock %}
```

---

## `assets/*` - styles and scripts

- LESS is assembled in CSS: `notes.less → notes.css` (see [assets in contributions](contributions.md)).
- JS extends the global object `Adminx` (`Adminx.Notes = {...}`) and uses
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
