# Routing

← [Back to the “Kernel” section](README.md)

`App\Common\Router` - lightweight HTTP router: matches the path and method of the request with
handler. In the control panel, module routes are declared in their
`routes.php`.

---

## Announcing routes

```php
use App\Common\Router;
use App\Adminx\Notes\Controller;

Router::get('/notes',            array(Controller::class, 'index'));
Router::post('/notes',           array(Controller::class, 'store'));
Router::post('/notes/{id}',      array(Controller::class, 'update'));
Router::post('/notes/{id}/pin',  array(Controller::class, 'togglePin'));
Router::delete('/notes/{id}',    array(Controller::class, 'delete'));
```

Methods: `get`, `post`, `put`, `delete`, `any` - all accept `($pattern, $handler)`.

---

## Path parameters `{name}`

The fragments in curly braces become named parameters and come
to the handler with the first argument - the array `$params`.

```php
Router::get('/documents/{id}/edit', array(Controller::class, 'edit'));

public function edit(array $params = array())
{
    $id = isset($params['id']) ? (int) $params['id'] : 0;
    // ...
}
```

---

## Types of handlers

| Type | Example |
| --- | --- |
| `[class, method]` | `array(Controller::class, 'index')` - most common |
| `Closure` | `function (array $params) { ... }` |
| `string` | path to the PHP file (include executes the calling code) |

```php
Router::get('/ping', function () { return 'pong'; });
```

---

## Route groups

`group($prefix, callable $fn)` is a common prefix for a set of routes.

```php
Router::group('/catalog', function () {
    Router::get('/products',        array(Ctrl::class, 'products'));
    Router::get('/products/{id}',   array(Ctrl::class, 'product'));
});
// → /catalog/products, /catalog/products/{id}
```

---

## Mapping and override method

`Router::match($path = null, $method = null)` returns
`['handler' => ..., 'params' => [...]]` or `null`. By default takes the path and method
from `$_SERVER`.

**`_method`-override** supported: HTML form can send `PUT/DELETE/PATCH`
via `POST` with hidden field `_method`.

```html
<form method="post" action="/documents/5">
  <input type="hidden" name="_method" value="DELETE">
</form>
```

---

## Debugging

```php
Router::routes();   // все зарегистрированные маршруты
Router::reset();    // очистить (в тестах)
```

> In the control panel, the mapping and calling the handler does
>`App\Common\Dispatcher` on top
> `Router` - the module only needs to declare routes in `routes.php`.
