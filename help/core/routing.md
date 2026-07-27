# Маршрутизация (Router)

← [Назад к разделу «Ядро»](README.md)

`App\Common\Router` — лёгкий HTTP-роутер: сопоставляет путь и метод запроса с
обработчиком. В панели управления маршруты модулей объявляются в их
`routes.php`.

---

## Объявление маршрутов

```php
use App\Common\Router;
use App\Adminx\Notes\Controller;

Router::get('/notes',            array(Controller::class, 'index'));
Router::post('/notes',           array(Controller::class, 'store'));
Router::post('/notes/{id}',      array(Controller::class, 'update'));
Router::post('/notes/{id}/pin',  array(Controller::class, 'togglePin'));
Router::delete('/notes/{id}',    array(Controller::class, 'delete'));
```

Методы: `get`, `post`, `put`, `delete`, `any` — все принимают `($pattern, $handler)`.

---

## Параметры пути `{name}`

Фрагменты в фигурных скобках становятся именованными параметрами и приходят
обработчику первым аргументом — массивом `$params`.

```php
Router::get('/documents/{id}/edit', array(Controller::class, 'edit'));

public function edit(array $params = array())
{
    $id = isset($params['id']) ? (int) $params['id'] : 0;
    // ...
}
```

---

## Типы обработчиков

| Тип | Пример |
| --- | --- |
| `[class, method]` | `array(Controller::class, 'index')` — самый частый |
| `Closure` | `function (array $params) { ... }` |
| `string` | путь к PHP-файлу (include выполняет вызывающий код) |

```php
Router::get('/ping', function () { return 'pong'; });
```

---

## Группы маршрутов

`group($prefix, callable $fn)` — общий префикс для набора маршрутов.

```php
Router::group('/catalog', function () {
    Router::get('/products',        array(Ctrl::class, 'products'));
    Router::get('/products/{id}',   array(Ctrl::class, 'product'));
});
// → /catalog/products, /catalog/products/{id}
```

---

## Сопоставление и метод-override

`Router::match($path = null, $method = null)` возвращает
`['handler' => ..., 'params' => [...]]` или `null`. По умолчанию берёт путь и метод
из `$_SERVER`.

Поддерживается **`_method`-override**: HTML-форма может послать `PUT/DELETE/PATCH`
через `POST` со скрытым полем `_method`.

```html
<form method="post" action="/documents/5">
  <input type="hidden" name="_method" value="DELETE">
</form>
```

---

## Отладка

```php
Router::routes();   // все зарегистрированные маршруты
Router::reset();    // очистить (в тестах)
```

> В панели управления сопоставление и вызов обработчика делает
> `App\Common\Dispatcher` поверх
> `Router` — модулю достаточно объявить маршруты в `routes.php`.
