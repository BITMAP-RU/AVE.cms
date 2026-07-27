# Controllers

← [Back to the “Kernel” section](README.md)

The base class is `App\Common\Controller`. Panel controllers inherit it and receive
template rendering, single JSON contract and CSRF protection.

```php
namespace App\Adminx\Notes;

use App\Common\Controller as BaseController;
use App\Common\Permission;
use App\Helpers\Request;
use App\Helpers\Response;

class Controller extends BaseController
{
    public function index(array $params = array())
    {
        if (!Permission::check('view_notes')) { Response::forbidden(); return ''; }
        return $this->render('@notes/index.twig', array('notes' => Model::all()));
    }
}
```

Each handler method accepts `array $params` - [route parameters](routing.md).

---

## Render templates

| Method | Destination |
| --- | --- |
| `render($template, array $data = [])` | Render a Twig template (with module globals) → line. |
| `renderStatus($template, $data, $status)` | The same + HTTP code. |
| `partial($template, $data, $status = 200)` | Fragment without layout (for AJAX updating of part of the page). |

```php
return $this->render('@notes/index.twig', array('notes' => $notes));
```

The Twig namespace of the template is `code` of the module: `@notes`, `@kanban`, `@seoaudit`…

---

## Single JSON contract (Ajax)

The frontend of the panel (`Adminx.Ajax`) is waiting for a response in a single format. Don't assemble it by hand
- use wrappers:

###`success($message = '', array $extra = [], $status = 200)`

```php
return $this->success('Сохранено', array(
    'data'     => array('id' => $id),
    'html'     => array('row' => $this->render('@notes/partials/note.twig', ...)),
    'redirect' => $this->base() . '/notes',
));
```



### `error($message = '', array $errors = [], $status = 422)`

```php
return $this->error('Проверьте поля', array('title' => 'Укажите заголовок'));
```

Both return JSON like:

```json
{ "success": true|false, "message": "...", "data": {}, "html": {}, "redirect": null, "errors": {} }
```

### `json($data, $status = 200)` - arbitrary JSON (low level).

---

## Defining the request format

```php
$this->wantsJson();     // клиент ждёт JSON (Ajax / Accept: application/json / ?format=json)
$this->wantsPartial();  // клиент ждёт фрагмент без layout
```

---

##CSRF

On any action that changes state (POST/PUT/DELETE), check the CSRF.

### `csrfGuard()` — return a ready-made error response if the token is incorrect:

```php
public function store(array $params = array())
{
    if (($err = $this->csrfGuard()) !== null) { return $err; }   // 403 «Сессия устарела»
    if (!Permission::check('manage_notes')) { return $this->error('Недостаточно прав', array(), 403); }
    // ... сохранить ...
    return $this->success('Добавлено');
}
```

### `verifyCsrf()` - Throws `RuntimeException(419)` on mismatch (low level).

The token is read from the header `X-CSRF-Token` (Ajax) or field `_csrf` (form). See
[Sessions and CSRF](sessions-csrf.md).

---

## Redirects and database

```php
$this->redirect($url);   // выполнить переход
$this->base();           // префикс админки (ADMINX_BASE) для сборки ссылок
```

---

## Typical action skeleton

```php
public function update(array $params = array())
{
    if (($err = $this->csrfGuard()) !== null) { return $err; }
    if (!Permission::check('manage_x')) { return $this->error('Недостаточно прав', array(), 403); }

    $id = isset($params['id']) ? (int) $params['id'] : 0;
    $errors = Valid::check($_POST, array('title' => 'required'));
    if ($errors) { return $this->error('Проверьте поля', $errors); }

    Model::save($id, array('title' => Request::postStr('title')));
    return $this->success('Сохранено', array('data' => array('id' => $id)));
}
```
