# Json - JSON

`App\Helpers\Json` - secure encode/decode with reasonable defaults and support
Cyrillic alphabet.

```php
use App\Helpers\Json;
```

---

## Coding

###`encode($data, $flags = 0)`
Regular coding.

###`payload($value, $flags = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)`
Encoding "for the API": readable Cyrillic, slashes are not escaped. **Returns
`null`** for empty values (`null`, `''`, `[]`) - convenient, so as not to drive empty space.

```php
echo Json::payload(['title' => 'Кресло', 'price' => 100]);
// {"title":"Кресло","price":100}   ← без \u04..
Json::payload([]);   // null
```

---

## Decoding

###`decode($json, $assoc = true)`
By default - to an array; `$assoc = false` - to the object.

```php
$data = Json::decode($raw);          // массив
$obj  = Json::decode($raw, false);   // объект
```

###`toArray($value, array $default = [])`
An array is guaranteed: if it’s already an array, it will be returned as is, if it’s a string, it will be parsed,
in case of error - `$default`. Ideal for JSON database fields.

```php
$settings = Json::toArray($row['settings']);          // всегда массив
$tags     = Json::toArray($row['tags'], ['default']);
```

---

## Auxiliary

| Method | Destination |
| --- | --- |
| `show(array $array, $shutdown = false, $cyrillic = false)` | Output JSON (and optionally complete the request). |
| `cyrillic($data)` | Make escaped Cyrillic alphabet readable. |
| `fix($json)` | An attempt to “fix” crooked JSON (single quotes, garbage). |

```php
$data = Json::decode(Json::fix($messyJson));   // last resort для «грязного» входа
```

---

## Recipes

**Reading JSON settings from a database string without crashes:**

```php
$config = Json::toArray($row['config']);
$perPage = isset($config['per_page']) ? (int) $config['per_page'] : 25;
```

> For a full HTTP response, it is more convenient to [Response::json()](Response.md) - it puts
> headers and code. `Json` - about serialization as such.
