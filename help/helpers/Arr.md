# Arr — массивы

`App\Helpers\Arr` — утилиты для массивов. Ключевая фишка — доступ по **dot-нотации**
(`'shop.currency.default'`), плюс поиск, сортировка, конвертация и безопасная
(де)сериализация.

```php
use App\Helpers\Arr;
```

---

## Доступ по ключу и dot-нотации

### `get($array, $key, $default = null, $filter = null)`
Значение по ключу или по dot-пути. Если пути нет — вернёт `$default` (может быть
замыканием — тогда вызовется). `$filter` — необязательная функция-фильтр значения.
```php
$config = ['shop' => ['currency' => ['default' => 'RUB']]];

Arr::get($config, 'shop.currency.default');        // 'RUB'
Arr::get($config, 'shop.currency.rate', 1);        // 1 (пути нет → дефолт)
Arr::get($config, 'shop.currency.rate', function () { return loadRate(); }); // лениво
```

### `set(&$array, $key, $value)`
Устанавливает значение по dot-пути (создаёт вложенные массивы). Меняет исходный
массив по ссылке.
```php
$data = [];
Arr::set($data, 'meta.seo.title', 'Заголовок');
// $data === ['meta' => ['seo' => ['title' => 'Заголовок']]]
```

### `has($array, $keys)`
Есть ли **все** перечисленные ключи (строка или массив ключей, dot поддерживается).
```php
if (Arr::has($input, ['title', 'rubric_id'])) { /* оба поля пришли */ }
Arr::has($config, 'shop.currency.default');   // true
```

### `exists($array, $key)`
Существует ли ключ верхнего уровня (без dot-разбора, через `array_key_exists`).

---

## Выборка элементов

### `first($array, $callback = null, $default = null)` / `last(...)`
Первый/последний элемент; с колбэком — первый/последний подходящий.
```php
Arr::first($rows);                                   // первый элемент
Arr::first($rows, function ($row) { return $row['active']; }); // первый активный
Arr::last($rows, function ($r) { return $r['qty'] > 0; });
```

### `find($array, $key, $value)`
Найти элемент (строку) массива, у которого `элемент[$key] == $value`.
```php
$user = Arr::find($users, 'id', 42);
```

### `countInArray($array, $key, $value)`
Сколько элементов имеют заданное значение по ключу.

---

## Преобразования

### `dot($array, $prepend = '')`
Схлопывает вложенный массив в плоский с dot-ключами.
```php
Arr::dot(['a' => ['b' => 1, 'c' => 2]]);   // ['a.b' => 1, 'a.c' => 2]
```

### `toObject($array)` / `toArray($object)` / `objectToArray($object)`
Конвертация массив ↔ объект (рекурсивно).
```php
$obj = Arr::toObject($row);   // $obj->document_title
```

### `isAssoc($array)` / `is($value)`
Ассоциативный ли массив / является ли значение массивом.

---

## Сортировка

### `multiSort($array, $key, $sort_flags = SORT_REGULAR, $sort_way = SORT_ASC)`
Сортировка массива массивов по значению ключа.
```php
$rows = Arr::multiSort($rows, 'position');                 // по возрастанию
$rows = Arr::multiSort($rows, 'price', SORT_NUMERIC, SORT_DESC);
```
`sortArray($data, $field)` — упрощённый вариант сортировки по полю.

---

## Сериализация

### `safeSerialize(array $array)` / `safeUnserialize(string $string)`
Безопасная (де)сериализация (без выполнения произвольных объектов).
`arrayToSerial()` / `unserialToArray()` — парная сериализация в строку и обратно.
```php
$blob = Arr::safeSerialize(['a' => 1]);
$data = Arr::safeUnserialize($blob);      // ['a' => 1]
```

### `stripslashesArray($array)`
Рекурсивно снимает слэши (для данных из legacy-источников с magic quotes).

---

## Рецепты

**Безопасное чтение настроек с дефолтом:**
```php
$perPage = (int) Arr::get($settings, 'catalog.per_page', 25);
```

**Проверка обязательных полей формы:**
```php
if (!Arr::has($_POST, ['title', 'rubric_id'])) {
    return $this->error('Не хватает полей');
}
```

> Для чтения из `$_GET/$_POST` предпочитайте [Request](Request.md) — там есть
> типизация (`getInt`, `postStr`) и защита.
