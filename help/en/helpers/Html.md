# Html - HTML output

`App\Helpers\Html` - output minification/compression, minor conversions, assembly
query-strings.

```php
use App\Helpers\Html;
```

---

## Output and compression

### `compressHtml(string $data)` — minify HTML (remove extra spaces/hyphens).
### `compress(string $data)` - compress data.
### `setGzip()` — enable gzip output compression.
### `output(string $data)` — send data to output.

```php
echo Html::compressHtml($renderedPage);
```

---

## Transformations

### `nl2br($string)` — line breaks → `<br>`.
### `htmlEncode($string)` / `htmlDecode($string)` - encoding/decoding entities.

```php
echo Html::nl2br(Str::escape($comment));   // безопасный многострочный вывод
```

---

## Query strings

### `buildQuery($query)` — collect a query string from an array.
### `appendQuery($url, $query)` — add parameters to the URL (taking into account existing ones).

```php
$next = Html::appendQuery('/catalog?rubric=5', ['page' => 2]);
// '/catalog?rubric=5&page=2'
```

---

## Recipes

**Link to the next page with saving filters:**

```php
$href = Html::appendQuery(Request::path() . '?' . Request::queryString(), ['page' => $page + 1]);
```

> To escape individual values ​​- `Str::escape()` or Twig `{{ }}`.
