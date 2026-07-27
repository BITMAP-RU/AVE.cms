# Response - HTTP response

`App\Helpers\Response` — generation of responses and status codes. Methods set themselves
headers and code, so this is usually the last thing the handler does.

```php
use App\Helpers\Response;
```

---

## JSON

###`json($data, $status = 200, $flags = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)`
JSON response. The default flags display readable Cyrillic and do not escape slashes.

```php
Response::json(['items' => $items, 'total' => $total]);
Response::json(['error' => 'bad'], 400);
```

### `jsonSuccess($data = null, $message = '')` / `jsonError($message, $status = 400, $data = null)`
Responses in a single “success/failure” format.

```php
Response::jsonSuccess($saved, 'Сохранено');
Response::jsonError('Документ не найден', 404);
```

---

## Other formats

```php
Response::html($html, 200);
Response::text($plain, 200);
Response::xml($xml, 200);
```

## Files

###`download($filePath, $fileName = null, $mimeType = 'application/octet-stream')`
Give the file as an attachment (download).

```php
Response::download(BASEPATH . '/uploads/report.xlsx', 'Отчёт.xlsx');
```

###`inline($filePath, $mimeType = null, $fileName = null)`
Show file in browser (PDF, image).

---

## Ready statuses

| Method | Code | When |
| --- | --- | --- |
| `noContent()` | 204 | Success without a body (after removal). |
| `notFound($msg = 'Not Found')` | 404 | Resource not found. |
| `forbidden($msg = 'Forbidden')` | 403 | No rights. |
| `unauthorized($msg)` | 401 | Not authorized. |
| `unprocessable($errors, $msg = 'Validation failed')` | 422 | Validation errors (by fields). |
| `tooManyRequests($msg, $retryAfter = null)` | 429 | Throttling (+`Retry-After`). |
| `serverError($msg)` | 500 | Internal error. |

```php
if (!Permission::check('view_x')) { Response::forbidden(); return ''; }
$errors = Valid::check($_POST, $rules);
if ($errors) { Response::unprocessable($errors); return ''; }
```

---

## Recipes

**Validation → 422 with errors in fields:**

```php
$errors = Valid::check($_POST, ['email' => 'required|email']);
if ($errors) { Response::unprocessable($errors); return ''; }
Response::jsonSuccess(['id' => $id], 'Готово');
```

> Panel controllers often use wrappers `success()` / `error()` of the base
> `App\Common\Controller` - they return a single contract
> (`success/message/data/html/redirect/errors`), which understands `Adminx.Ajax`.
> `Response::*` is the low-level layer below them.
