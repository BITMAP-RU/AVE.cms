# Str - strings (UTF-8)

`App\Helpers\Str` - working with UTF-8 encoded strings. All the methods under the hood
they use `mbstring`, so they correctly calculate the length and cut the Cyrillic alphabet.
The class is static, the instance is not created.

```php
use App\Helpers\Str;
```

> Why not built-in `strlen`/`substr`? They count **bytes**, not characters:
> `strlen('Hello')` will return 12, and `Str::length('Hello')` will return 6. For any text
> with Cyrillic use `Str`.

---

## Basic operations

###`length($str): int`
Length of the string in characters.

```php
Str::length('Привет');        // 6
```

### `upper($str)` / `lower($str)`
Register taking into account the Cyrillic alphabet.

```php
Str::upper('привет');         // ПРИВЕТ
Str::lower('ТОВАР');          // товар
```

### `ucfirst($str)` - the first letter is capitalized, `title($str)` - each word is capitalized

```php
Str::ucfirst('кресло-коляска');   // Кресло-коляска
Str::title('медицинское оборудование'); // Медицинское Оборудование
```

###`trim / ltrim / rtrim ($str, $characters = null)`
Like inline ones, but safely result in a string. The second argument is a list of characters.

```php
Str::trim('  текст  ');          // 'текст'
Str::trim('/catalog/', '/');     // 'catalog'
```

###`nullIfEmpty($str)`
Trims and returns `null` if there is an empty string left. Convenient for recording
nullable database fields.

```php
Str::nullIfEmpty('   ');   // null
Str::nullIfEmpty(' abc '); // 'abc'
```

### `substr($str, $start, $length = null)`, `pos($haystack, $needle, $offset = 0)`
Multibyte analogs of `substr`/`strpos`.

```php
Str::substr('Привет мир', 0, 6);   // 'Привет'
Str::pos('Привет', 'вет');         // 3 (в символах, не байтах)
```

### `pad`, `repeat`, `replace`
Wrappers over `str_pad` / `str_repeat` / `str_replace`.

---

## Search and checks

###`contains($haystack, $needle): bool`

```php
if (Str::contains($title, 'УЗИ')) { ... }
```



### `startsWith($str, $prefix)` / `endsWith($str, $suffix): bool`

```php
Str::startsWith('/catalog/1', '/catalog');  // true
Str::endsWith('photo.JPG', '.JPG');         // true
```

###`is($pattern, $value): bool`
Match the mask with wildcards **`*`** (any symbols) and **`?`** (one symbol).

```php
Str::is('doc_*', 'doc_37');     // true
Str::is('img?.png', 'img5.png'); // true
```

---

## Trimming text

###`limit($str, $limit = 100, $end = '...')`
Trims by the number of **characters**, adds a suffix only if real
trimmed. Can break a word in the middle.

```php
Str::limit('Длинное описание товара', 10);   // 'Длинное оп...'
```

###`words($str, $words = 10, $end = '...')`
Trims by number of **words**.

```php
Str::words('один два три четыре', 2);   // 'один два...'
```

###`truncateSmart($string, $length = 80, $suffix = '...', $breakWords = false, $middle = false)`
"Smart" pruning. By default **does not tear words** (cuts to the last whole).
`$middle = true` - cuts out the middle, leaving the beginning and end.

```php
Str::truncateSmart('Кресло-коляска электрическая Model X', 20);
// 'Кресло-коляска...'   (не рвёт слово)
Str::truncateSmart('очень_длинное_имя_файла.pdf', 18, '…', false, true);
// 'очень_д…йла.pdf'    (середина вырезана)
```

###`truncateText($string, $length = 100, $suffix = '&#8230;')`
Trims at the first whole word **after** the given length (i.e. not shorter than `$length`).
The default suffix is ​​the HTML mnemonic ellipsis.

> `truncate()` - deprecated alias `limit()`.

---

## Masking

###`mask($str, $start, $length = 0, $char = '*')`
Replaces part of a string with wildcard characters. `$start < 0` — counting from the end, `$length = 0` —
to the end of the line.

```php
Str::mask('4111111111111234', 0, 12);  // '************1234'
Str::mask('secret', -3);               // 'sec***'
```

###`maskEmail($email)`
Masks the local part of the email, showing ~⅓ characters.

```php
Str::maskEmail('ivanov@mail.ru');  // 'iv****@mail.ru'
Str::maskEmail('ab@x.ru');         // '**@x.ru' (короткое имя скрывается полностью)
```

---

## Naming and slug

### `camel` / `studly` / `snake ($str, $delimiter = '_')`
Convert naming styles.

```php
Str::camel('rubric_field_id');    // 'rubricFieldId'
Str::studly('rubric_field_id');   // 'RubricFieldId'
Str::snake('rubricFieldId');      // 'rubric_field_id'
```

###`slug($str, $separator = '-')`
Text → URL-slug. **Transliterates Cyrillic**, clears special characters, collapses
separators.

```php
Str::slug('Кресло-коляска ЕК-6012');   // 'kreslo-kolyaska-ek-6012'
Str::slug('Маски (упаковка 50 шт.)');  // 'maski-upakovka-50-sht'
```

###`machineAlias($str, $separator = '-')`
For technical keys: **doesn't** transliterate, only clears to `a-z0-9_.-` and
collapses separators. The Cyrillic alphabet will be removed - apply to Latin keys.

```php
Str::machineAlias('My Cache.Key v2');  // 'my-cache.key-v2'
```

---

## Generation

###`random($length = 16, $chars = 'a-z0-9')`
Cryptographically strong random string (uses `random_bytes`).

```php
Str::random(32);                       // 'k3f9...'
Str::random(6, '0123456789');          // '481902' (только цифры)
```

###`uuid()`
UUID version 4.

```php
Str::uuid();   // '3f2504e0-4f89-41d3-9a0c-0305e82c3301'
```

---

## Security and withdrawal

###`equals($a, $b): bool`
String comparison resistant to timing attacks (`hash_equals`). For tokens/signatures.

```php
if (Str::equals($providedToken, $expected)) { ... }
```

### `sha256($str)` - SHA-256 hash of the string.
> This is **not** suitable for passwords - use `password_hash()`.

###`stripTags($str, $allowedTags = '')`
Removes HTML tags **and** decodes entities.

```php
Str::stripTags('<b>Цена</b>&nbsp;100');   // 'Цена 100'
```

###`escape($str)`
Escapes for safe HTML output (`htmlspecialchars`, ENT_QUOTES|ENT_HTML5).

```php
echo Str::escape($userInput);   // < > " ' & становятся безопасными
```

---

## Recipes

**NC from document title:**

```php
$alias = Str::slug($input['title']);
if ($alias === '') { $alias = 'doc-' . Str::random(6); }
```

**Short announcement for the card without word breaks:**

```php
$teaser = Str::truncateSmart(Str::stripTags($html), 140);
```

**Secure Custom Text Output:**

```php
echo Str::escape($comment);
```
