# Request - incoming HTTP request

`App\Helpers\Request` is a single point of access to request data. **Always** prefer
typed getters (`getInt`, `postStr`, `postBool` ...) direct `$_GET/$_POST`:
they lead the type and give a predictable default.

```php
use App\Helpers\Request;
```

---

## Request method and type

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

## Read from GET

| Method | Return |
| --- | --- |
| `get($key, $default = null)` | Raw value from `$_GET`. |
| `getAll()` | All `$_GET`. |
| `getInt($key, $default = 0)` | Whole. |
| `getStr($key, $default = '')` | Line. |
| `getFloat($key, $default = 0.0)` | Fractional. |
| `getBool($key, $default = false)` | Boolean. |

```php
$page = Request::getInt('page', 1);
$q    = Request::getStr('q', '');
$rid  = Request::getInt('rubric_id', 0);
```

## Reading from POST

| Method | Return |
| --- | --- |
| `post($key, $default = null)` / `postAll()` | Raw / everything. |
| `postInt / postStr / postFloat / postBool ($key, $default)` | Typed. |
| `postNullableInt($key)` | `int` or `null` (for nullable fields). |
| `postJsonArray($key, array $default = [])` | JSON string from field → array. |

```php
$title  = Request::postStr('title', '');
$active = Request::postBool('active', false);
$parent = Request::postNullableInt('parent_id');   // null, если не прислали
$ids    = Request::postJsonArray('ids');           // '[1,2,3]' → [1,2,3]
```

---

## Combined input and request body

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

## Request metadata

```php
Request::ip();            // IP клиента (учитывает прокси-заголовки)
Request::userAgent();
Request::referer();
Request::fullUrl();       // полный URL
Request::path();          // путь
Request::queryString();   // строка запроса
```

---

## Recipes

**Pagination + search in controller:**

```php
$filters = [
    'page'      => Request::getInt('page', 1),
    'q'         => Request::getStr('q', ''),
    'rubric_id' => Request::getInt('rubric_id', 0),
];
```

**Receiving a JSON body (fetch):**

```php
$payload = Request::json();               // ['ids' => [...], ...]
$ids = isset($payload['ids']) ? $payload['ids'] : [];
```

> In the successor controllers `App\Common\Controller` there is `wantsJson()`,
> `verifyCsrf()` / `csrfGuard()` - check CSRF on all POST actions.
