# Request — входящий HTTP-запрос

`App\Helpers\Request` — единая точка доступа к данным запроса. **Всегда** предпочитайте
типизированные геттеры (`getInt`, `postStr`, `postBool` …) прямому `$_GET/$_POST`:
они приводят тип и дают предсказуемый дефолт.

```php
use App\Helpers\Request;
```

---

## Метод и тип запроса

```php
Request::method();     // 'GET' | 'POST' | ...
Request::isGet();  Request::isPost();  Request::isPut();  Request::isDelete();  Request::isPatch();
Request::isAjax();     // XHR (X-Requested-With)
Request::isPjax();     // PJAX-навигация
```
```php
if (Request::isPost() && Request::isAjax()) { /* обработать AJAX-форму */ }
```

---

## Чтение из GET

| Метод | Возврат |
| --- | --- |
| `get($key, $default = null)` | Сырое значение из `$_GET`. |
| `getAll()` | Весь `$_GET`. |
| `getInt($key, $default = 0)` | Целое. |
| `getStr($key, $default = '')` | Строка. |
| `getFloat($key, $default = 0.0)` | Дробное. |
| `getBool($key, $default = false)` | Булево. |

```php
$page = Request::getInt('page', 1);
$q    = Request::getStr('q', '');
$rid  = Request::getInt('rubric_id', 0);
```

## Чтение из POST

| Метод | Возврат |
| --- | --- |
| `post($key, $default = null)` / `postAll()` | Сырое / всё. |
| `postInt / postStr / postFloat / postBool ($key, $default)` | Типизированно. |
| `postNullableInt($key)` | `int` или `null` (для nullable-полей). |
| `postJsonArray($key, array $default = [])` | JSON-строка из поля → массив. |

```php
$title  = Request::postStr('title', '');
$active = Request::postBool('active', false);
$parent = Request::postNullableInt('parent_id');   // null, если не прислали
$ids    = Request::postJsonArray('ids');           // '[1,2,3]' → [1,2,3]
```

---

## Объединённый ввод и тело запроса

```php
Request::input('title', '');       // из GET+POST
Request::all();                     // весь ввод
Request::only(['title', 'rubric_id']);
Request::except(['_csrf']);
Request::has('title');

Request::json();                    // тело как JSON → массив (для fetch/JSON API)
Request::rawBody();                 // сырое тело
```

---

## Метаданные запроса

```php
Request::ip();            // IP клиента (учитывает прокси-заголовки)
Request::userAgent();
Request::referer();
Request::fullUrl();       // полный URL
Request::path();          // путь
Request::queryString();   // строка запроса
```

---

## Рецепты

**Пагинация + поиск в контроллере:**
```php
$filters = [
    'page'      => Request::getInt('page', 1),
    'q'         => Request::getStr('q', ''),
    'rubric_id' => Request::getInt('rubric_id', 0),
];
```

**Приём JSON-тела (fetch):**
```php
$payload = Request::json();               // ['ids' => [...], ...]
$ids = isset($payload['ids']) ? $payload['ids'] : [];
```

> В контроллерах-наследниках `App\Common\Controller` есть `wantsJson()`,
> `verifyCsrf()` / `csrfGuard()` — проверяйте CSRF на всех POST-действиях.
