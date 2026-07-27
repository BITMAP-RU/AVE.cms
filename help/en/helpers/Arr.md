# Arr - arrays

`App\Helpers\Arr` - utilities for arrays. The key feature is access using **dot notation**
(`'shop.currency.default'`), plus search, sort, convert and secure
(de)serialization.

```php
use App\Helpers\Arr;
```

---

## Access by key and dot notation

###`get($array, $key, $default = null, $filter = null)`
Value by key or dot path. If there is no path, it will return `$default` (maybe
short circuit - then it will be called). `$filter` is an optional value filter function.

```php
$config = ['shop' => ['currency' => ['default' => 'RUB']]];

Arr::get($config, 'shop.currency.default');        // 'RUB'
Arr::get($config, 'shop.currency.rate', 1);        // 1 (пути нет → дефолт)
Arr::get($config, 'shop.currency.rate', function () { return loadRate(); }); // лениво
```

###`set(&$array, $key, $value)`
Sets the value by dot path (creates nested arrays). Changes the original
array by reference.

```php
$data = [];
Arr::set($data, 'meta.seo.title', 'Заголовок');
// $data === ['meta' => ['seo' => ['title' => 'Заголовок']]]
```

###`has($array, $keys)`
Are **all** the keys listed (string or array of keys, dot supported).

```php
if (Arr::has($input, ['title', 'rubric_id'])) { /* оба поля пришли */ }
Arr::has($config, 'shop.currency.default');   // true
```

###`exists($array, $key)`
Does a top-level key exist (without dot parsing, via `array_key_exists`).

---

## Selection of elements

### `first($array, $callback = null, $default = null)` / `last(...)`
First/last element; with callback - first/last suitable.

```php
Arr::first($rows);                                   // первый элемент
Arr::first($rows, function ($row) { return $row['active']; }); // первый активный
Arr::last($rows, function ($r) { return $r['qty'] > 0; });
```

###`find($array, $key, $value)`
Find an element (string) of the array whose `element[$key] == $value`.

```php
$user = Arr::find($users, 'id', 42);
```

###`countInArray($array, $key, $value)`
How many elements have a given value by key.

---

## Transformations

###`dot($array, $prepend = '')`
Collapses a nested array into a flat one with dot keys.

```php
Arr::dot(['a' => ['b' => 1, 'c' => 2]]);   // ['a.b' => 1, 'a.c' => 2]
```

### `toObject($array)` / `toArray($object)` / `objectToArray($object)`
Convert array ↔ object (recursively).

```php
$obj = Arr::toObject($row);   // $obj->document_title
```

### `isAssoc($array)` / `is($value)`
Whether the array is associative / whether the value is an array.

---

## Sorting

###`multiSort($array, $key, $sort_flags = SORT_REGULAR, $sort_way = SORT_ASC)`
Sort an array of arrays by key value.

```php
$rows = Arr::multiSort($rows, 'position');                 // по возрастанию
$rows = Arr::multiSort($rows, 'price', SORT_NUMERIC, SORT_DESC);
```

`sortArray($data, $field)` - a simplified version of sorting by field.

---

## Serialization

### `safeSerialize(array $array)` / `safeUnserialize(string $string)`
Safe (de)serialization (without executing arbitrary objects).
`arrayToSerial()` / `unserialToArray()` - paired serialization to a string and back.

```php
$blob = Arr::safeSerialize(['a' => 1]);
$data = Arr::safeUnserialize($blob);      // ['a' => 1]
```

###`stripslashesArray($array)`
Recursively removes slashes (for data from legacy sources with magic quotes).

---

## Recipes

**Safe reading of default settings:**

```php
$perPage = (int) Arr::get($settings, 'catalog.per_page', 25);
```

**Checking required form fields:**

```php
if (!Arr::has($_POST, ['title', 'rubric_id'])) {
    return $this->error('Не хватает полей');
}
```

> To read from `$_GET/$_POST`, prefer [Request](Request.md) - there is
> typing (`getInt`, `postStr`) and protection.
