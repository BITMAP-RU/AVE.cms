# Контроллеры

← [Назад к разделу «Ядро»](README.md)

Базовый класс — `App\Common\Controller`. Контроллеры панели наследуют его и получают
рендер шаблонов, единый JSON-контракт и защиту CSRF.

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

Каждый метод-обработчик принимает `array $params` — [параметры маршрута](routing.md).

---

## Рендер шаблонов

| Метод | Назначение |
| --- | --- |
| `render($template, array $data = [])` | Отрендерить Twig-шаблон (с глобалями модулей) → строка. |
| `renderStatus($template, $data, $status)` | То же + HTTP-код. |
| `partial($template, $data, $status = 200)` | Фрагмент без layout (для AJAX-обновления части страницы). |

```php
return $this->render('@notes/index.twig', array('notes' => $notes));
```

Twig-неймспейс шаблона равен `code` модуля: `@notes`, `@kanban`, `@seoaudit`…

---

## Единый JSON-контракт (Ajax)

Фронтенд панели (`Adminx.Ajax`) ждёт ответ единого формата. Не собирайте его вручную
— используйте обёртки:

### `success($message = '', array $extra = [], $status = 200)`
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

Оба возвращают JSON вида:
```json
{ "success": true|false, "message": "...", "data": {}, "html": {}, "redirect": null, "errors": {} }
```

### `json($data, $status = 200)` — произвольный JSON (низкоуровнево).

---

## Определение формата запроса

```php
$this->wantsJson();     // клиент ждёт JSON (Ajax / Accept: application/json / ?format=json)
$this->wantsPartial();  // клиент ждёт фрагмент без layout
```

---

## CSRF

На любом действии, меняющем состояние (POST/PUT/DELETE), проверяйте CSRF.

### `csrfGuard()` — вернуть готовый ответ-ошибку при неверном токене:
```php
public function store(array $params = array())
{
    if (($err = $this->csrfGuard()) !== null) { return $err; }   // 403 «Сессия устарела»
    if (!Permission::check('manage_notes')) { return $this->error('Недостаточно прав', array(), 403); }
    // ... сохранить ...
    return $this->success('Добавлено');
}
```

### `verifyCsrf()` — бросает `RuntimeException(419)` при несовпадении (низкоуровнево).

Токен читается из заголовка `X-CSRF-Token` (Ajax) или поля `_csrf` (форма). См.
[Сессии и CSRF](sessions-csrf.md).

---

## Редиректы и база

```php
$this->redirect($url);   // выполнить переход
$this->base();           // префикс админки (ADMINX_BASE) для сборки ссылок
```

---

## Типовой скелет действия

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
