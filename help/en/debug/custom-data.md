# Custom Debug calls

← [To the section “Public debugging”](README.md)

`App\Helpers\Debug::panel()` sends an arbitrary value to the tab
**Data** of the public Debug Toolbar. The method does not insert HTML into the page, it does not
stops execution and does not write data to the database or file.

## Main call

```php
use App\Helpers\Debug;

Debug::panel($value, 'Понятная подпись');
```

Signature is optional. If it is not passed, helper will try to determine the name
variable from source file line:

```php
Debug::panel($catalogSettings);
```

An explicit signature is more secure for stored PHP code, multiline expressions, and
several calls nearby.

## What can be transferred

```php
Debug::panel(array(
    'document_id' => $documentId,
    'rubric_id' => $rubricId,
    'fields' => $fieldValues,
), 'Данные перед сохранением');

Debug::panel($documentObject, 'Загруженный документ');
Debug::panel($sql, 'Сформированный SQL');
Debug::panel($responseCode, 'Код ответа шлюза');
```

- arrays open up like a tree;
- the class and its public properties are shown for the object;
- the recursive link is marked, and does not loop the panel;
- resource is replaced with a text description;
- maximum normalization depth is 8 levels.

A very large array still takes up memory and makes the panel inconvenient.
Transfer a diagnostic slice, not an entire catalog or collection of documents.

## The method returns the original value

This allows you to temporarily view the result inside an expression:

```php
$document = Debug::panel(
    $repository->find($documentId),
    'Результат DocumentRepository::find'
);
```

For persistent code, a separate call is better readable and easier to remove.

## In the system block or category code

The stored PHP is executed through a compatible runtime. Can be used
helper's full name:

```php
\App\Helpers\Debug::panel($fields, 'Поля из кода рубрики');
```

The value will only appear in a request in which this section actually appeared
completed. If the condition did not work or the result was taken from the cache before execution
code, there will be no entry in the tab.

## Request diagnostics

The **Show SQL** query setup uses the same secure channel. SQL
appears in **Data** with a signature like `SQL query #12`, but is not output in
visitor card or HTML.

For regular SQL, a separate `Debug::panel()` is often not needed: the **SQL** tab is already
shows the completed request, duration and location of the call. Add yours
call if you need to see a request **before** execution or compare several
construction options.

## What not to use on a public page

```php
Debug::echo($value);
Debug::print($value);
Debug::exp($value);
```

These legacy methods print debugging HTML directly into the response. He can be seen
visitor; it is also capable of breaking JSON, headers, redirection or layout.

`Debug::dump()` writes `debug.html` to disk and is not a Toolbar tab.
For public diagnostics use `Debug::panel()`.

## Secrets and personal data

You cannot transfer passwords, Bearer tokens, payment keys, full cookies or data
bank card. Automasking works on array keys and does not recognize
secret passed as a separate line:

```php
// Нельзя: scalar будет показан как есть.
Debug::panel($apiToken, 'API token');

// Допустимо для проверки структуры без раскрытия значения.
Debug::panel(array(
    'token' => '***',
    'expires_at' => $expiresAt,
), 'Состояние токена');
```

Records only live until the end of the current PHP request. They do not replace auditing,
application log or integration task log.
