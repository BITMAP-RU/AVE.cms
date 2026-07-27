# Str — строки (UTF-8)

`App\Helpers\Str` — работа со строками в кодировке UTF-8. Все методы под капотом
используют `mbstring`, поэтому корректно считают длину и режут кириллицу.
Класс статический, инстанс не создаётся.

```php
use App\Helpers\Str;
```

> Почему не встроенные `strlen`/`substr`? Они считают **байты**, а не символы:
> `strlen('Привет')` вернёт 12, а `Str::length('Привет')` — 6. Для любого текста
> с кириллицей используйте `Str`.

---

## Базовые операции

### `length($str): int`
Длина строки в символах.
```php
Str::length('Привет');        // 6
```

### `upper($str)` / `lower($str)`
Регистр с учётом кириллицы.
```php
Str::upper('привет');         // ПРИВЕТ
Str::lower('ТОВАР');          // товар
```

### `ucfirst($str)` — первая буква заглавная, `title($str)` — каждое слово с заглавной
```php
Str::ucfirst('кресло-коляска');   // Кресло-коляска
Str::title('медицинское оборудование'); // Медицинское Оборудование
```

### `trim / ltrim / rtrim ($str, $characters = null)`
Как встроенные, но безопасно приводят к строке. Второй аргумент — список символов.
```php
Str::trim('  текст  ');          // 'текст'
Str::trim('/catalog/', '/');     // 'catalog'
```

### `nullIfEmpty($str)`
Триммит и возвращает `null`, если осталась пустая строка. Удобно для записи в
nullable-поля БД.
```php
Str::nullIfEmpty('   ');   // null
Str::nullIfEmpty(' abc '); // 'abc'
```

### `substr($str, $start, $length = null)`, `pos($haystack, $needle, $offset = 0)`
Многобайтовые аналоги `substr`/`strpos`.
```php
Str::substr('Привет мир', 0, 6);   // 'Привет'
Str::pos('Привет', 'вет');         // 3 (в символах, не байтах)
```

### `pad`, `repeat`, `replace`
Обёртки над `str_pad` / `str_repeat` / `str_replace`.

---

## Поиск и проверки

### `contains($haystack, $needle): bool`
```php
if (Str::contains($title, 'УЗИ')) { ... }
```

### `startsWith($str, $prefix)` / `endsWith($str, $suffix): bool`
```php
Str::startsWith('/catalog/1', '/catalog');  // true
Str::endsWith('photo.JPG', '.JPG');         // true
```

### `is($pattern, $value): bool`
Соответствие маске с wildcards **`*`** (любые символы) и **`?`** (один символ).
```php
Str::is('doc_*', 'doc_37');     // true
Str::is('img?.png', 'img5.png'); // true
```

---

## Обрезка текста

### `limit($str, $limit = 100, $end = '...')`
Обрезает по количеству **символов**, добавляет суффикс, только если реально
обрезал. Может разорвать слово посередине.
```php
Str::limit('Длинное описание товара', 10);   // 'Длинное оп...'
```

### `words($str, $words = 10, $end = '...')`
Обрезает по количеству **слов**.
```php
Str::words('один два три четыре', 2);   // 'один два...'
```

### `truncateSmart($string, $length = 80, $suffix = '...', $breakWords = false, $middle = false)`
«Умная» обрезка. По умолчанию **не рвёт слова** (обрезает до последнего целого).
`$middle = true` — вырезает середину, оставляя начало и конец.
```php
Str::truncateSmart('Кресло-коляска электрическая Model X', 20);
// 'Кресло-коляска...'   (не рвёт слово)
Str::truncateSmart('очень_длинное_имя_файла.pdf', 18, '…', false, true);
// 'очень_д…йла.pdf'    (середина вырезана)
```

### `truncateText($string, $length = 100, $suffix = '&#8230;')`
Обрезает по первому целому слову **после** заданной длины (т.е. не короче `$length`).
Суффикс по умолчанию — HTML-мнемоника многоточия.

> `truncate()` — устаревший алиас `limit()`.

---

## Маскирование

### `mask($str, $start, $length = 0, $char = '*')`
Заменяет часть строки символами маски. `$start < 0` — отсчёт от конца, `$length = 0` —
до конца строки.
```php
Str::mask('4111111111111234', 0, 12);  // '************1234'
Str::mask('secret', -3);               // 'sec***'
```

### `maskEmail($email)`
Маскирует локальную часть e-mail, показывая ~⅓ символов.
```php
Str::maskEmail('ivanov@mail.ru');  // 'iv****@mail.ru'
Str::maskEmail('ab@x.ru');         // '**@x.ru' (короткое имя скрывается полностью)
```

---

## Именование и slug

### `camel` / `studly` / `snake ($str, $delimiter = '_')`
Преобразование стилей именования.
```php
Str::camel('rubric_field_id');    // 'rubricFieldId'
Str::studly('rubric_field_id');   // 'RubricFieldId'
Str::snake('rubricFieldId');      // 'rubric_field_id'
```

### `slug($str, $separator = '-')`
Текст → URL-slug. **Транслитерирует кириллицу**, чистит спецсимволы, схлопывает
разделители.
```php
Str::slug('Кресло-коляска ЕК-6012');   // 'kreslo-kolyaska-ek-6012'
Str::slug('Маски (упаковка 50 шт.)');  // 'maski-upakovka-50-sht'
```

### `machineAlias($str, $separator = '-')`
Для технических ключей: **не** транслитерирует, только чистит до `a-z0-9_.-` и
схлопывает разделители. Кириллица будет удалена — применяйте к латинским ключам.
```php
Str::machineAlias('My Cache.Key v2');  // 'my-cache.key-v2'
```

---

## Генерация

### `random($length = 16, $chars = 'a-z0-9')`
Криптографически стойкая случайная строка (использует `random_bytes`).
```php
Str::random(32);                       // 'k3f9...'
Str::random(6, '0123456789');          // '481902' (только цифры)
```

### `uuid()`
UUID версии 4.
```php
Str::uuid();   // '3f2504e0-4f89-41d3-9a0c-0305e82c3301'
```

---

## Безопасность и вывод

### `equals($a, $b): bool`
Сравнение строк, устойчивое к timing-атакам (`hash_equals`). Для токенов/подписей.
```php
if (Str::equals($providedToken, $expected)) { ... }
```

### `sha256($str)` — SHA-256 хеш строки.
> Для паролей это **не** подходит — используйте `password_hash()`.

### `stripTags($str, $allowedTags = '')`
Убирает HTML-теги **и** декодирует сущности.
```php
Str::stripTags('<b>Цена</b>&nbsp;100');   // 'Цена 100'
```

### `escape($str)`
Экранирует для безопасного вывода в HTML (`htmlspecialchars`, ENT_QUOTES|ENT_HTML5).
```php
echo Str::escape($userInput);   // < > " ' & становятся безопасными
```

---

## Рецепты

**ЧПУ из заголовка документа:**
```php
$alias = Str::slug($input['title']);
if ($alias === '') { $alias = 'doc-' . Str::random(6); }
```

**Короткий анонс для карточки без разрыва слов:**
```php
$teaser = Str::truncateSmart(Str::stripTags($html), 140);
```

**Безопасный вывод пользовательского текста:**
```php
echo Str::escape($comment);
```
