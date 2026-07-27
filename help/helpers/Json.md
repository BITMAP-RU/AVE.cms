# Json — JSON

`App\Helpers\Json` — безопасные encode/decode с разумными дефолтами и поддержкой
кириллицы.

```php
use App\Helpers\Json;
```

---

## Кодирование

### `encode($data, $flags = 0)`
Обычное кодирование.

### `payload($value, $flags = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)`
Кодирование «для API»: читаемая кириллица, слеши не экранируются. **Возвращает
`null`** для пустых значений (`null`, `''`, `[]`) — удобно, чтобы не гонять пустоту.
```php
echo Json::payload(['title' => 'Кресло', 'price' => 100]);
// {"title":"Кресло","price":100}   ← без \u04..
Json::payload([]);   // null
```

---

## Декодирование

### `decode($json, $assoc = true)`
По умолчанию — в массив; `$assoc = false` — в объект.
```php
$data = Json::decode($raw);          // массив
$obj  = Json::decode($raw, false);   // объект
```

### `toArray($value, array $default = [])`
Гарантированно массив: если уже массив — вернёт как есть, если строка — распарсит,
при ошибке — `$default`. Идеально для JSON-полей БД.
```php
$settings = Json::toArray($row['settings']);          // всегда массив
$tags     = Json::toArray($row['tags'], ['default']);
```

---

## Вспомогательное

| Метод | Назначение |
| --- | --- |
| `show(array $array, $shutdown = false, $cyrillic = false)` | Вывести JSON (и опц. завершить запрос). |
| `cyrillic($data)` | Привести экранированную кириллицу к читаемой. |
| `fix($json)` | Попытка «починить» кривой JSON (одинарные кавычки, мусор). |

```php
$data = Json::decode(Json::fix($messyJson));   // last resort для «грязного» входа
```

---

## Рецепты

**Чтение JSON-настроек из строки БД без падений:**
```php
$config = Json::toArray($row['config']);
$perPage = isset($config['per_page']) ? (int) $config['per_page'] : 25;
```

> Для полноценного HTTP-ответа удобнее [Response::json()](Response.md) — он ставит
> заголовки и код. `Json` — про сериализацию как таковую.
