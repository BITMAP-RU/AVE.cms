# Response — HTTP-ответ

`App\Helpers\Response` — формирование ответов и статус-кодов. Методы сами ставят
заголовки и код, поэтому обычно это последнее, что делает обработчик.

```php
use App\Helpers\Response;
```

---

## JSON

### `json($data, $status = 200, $flags = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)`
JSON-ответ. Дефолтные флаги отдают читаемую кириллицу и не экранируют слеши.
```php
Response::json(['items' => $items, 'total' => $total]);
Response::json(['error' => 'bad'], 400);
```

### `jsonSuccess($data = null, $message = '')` / `jsonError($message, $status = 400, $data = null)`
Ответы в едином «успех/ошибка» формате.
```php
Response::jsonSuccess($saved, 'Сохранено');
Response::jsonError('Документ не найден', 404);
```

---

## Другие форматы

```php
Response::html($html, 200);
Response::text($plain, 200);
Response::xml($xml, 200);
```

## Файлы

### `download($filePath, $fileName = null, $mimeType = 'application/octet-stream')`
Отдать файл как вложение (скачивание).
```php
Response::download(BASEPATH . '/uploads/report.xlsx', 'Отчёт.xlsx');
```

### `inline($filePath, $mimeType = null, $fileName = null)`
Показать файл в браузере (PDF, изображение).

---

## Готовые статусы

| Метод | Код | Когда |
| --- | --- | --- |
| `noContent()` | 204 | Успех без тела (после удаления). |
| `notFound($msg = 'Not Found')` | 404 | Ресурс не найден. |
| `forbidden($msg = 'Forbidden')` | 403 | Нет прав. |
| `unauthorized($msg)` | 401 | Не авторизован. |
| `unprocessable($errors, $msg = 'Validation failed')` | 422 | Ошибки валидации (по полям). |
| `tooManyRequests($msg, $retryAfter = null)` | 429 | Троттлинг (+`Retry-After`). |
| `serverError($msg)` | 500 | Внутренняя ошибка. |

```php
if (!Permission::check('view_x')) { Response::forbidden(); return ''; }
$errors = Valid::check($_POST, $rules);
if ($errors) { Response::unprocessable($errors); return ''; }
```

---

## Рецепты

**Валидация → 422 с ошибками по полям:**
```php
$errors = Valid::check($_POST, ['email' => 'required|email']);
if ($errors) { Response::unprocessable($errors); return ''; }
Response::jsonSuccess(['id' => $id], 'Готово');
```

> В контроллерах панели чаще используют обёртки `success()` / `error()` базового
> `App\Common\Controller` — они возвращают единый контракт
> (`success/message/data/html/redirect/errors`), который понимает `Adminx.Ajax`.
> `Response::*` — низкоуровневый слой под ними.
