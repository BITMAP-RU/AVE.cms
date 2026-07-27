# Number - numbers

`App\Helpers\Number` - formatting numbers, sizes and Russian declensions.

```php
use App\Helpers\Number;
```

---

## Formatting

###`numFormat($number, $decimal = 0, $after = ',', $thousand = '.')`
Wrapper `number_format`. **Important:** for "empty"/null value returns
the empty string `''`, not `0`.

```php
Number::numFormat(1234567, 0, ',', ' ');   // '1 234 567'
Number::numFormat(1234.5, 2, '.', ' ');    // '1 234.50'
Number::numFormat(0);                       // '' ← учтите в вёрстке
```

###`decimal($number, $scale = 2)`
Normalizes the number for **DECIMAL database fields**: always with a dot, the zero is not hidden.

```php
Number::decimal('1 234,5', 2);   // '1234.50'
Number::decimal(0);              // '0.00'
```

### `numberFormat($string, $type = null)` / `numberDeFormat($string)`
Formatting by type and reverse formatting (string → “raw” number).

---

## Dimensions

### `formatSize($size)` / `getTextSize($size)`
Bytes → human readable size.

```php
Number::formatSize(1048576);   // '1 МБ'
Number::formatSize(2560);      // '2.5 КБ'
```

---

## Russian declensions (important for UI)

###`declensionNum($number, $titles)`
Returns the **number + correct form** of the word. `$titles` - array of 3 forms:
`[1, 2-4, 5-0]`.

```php
Number::declensionNum(1,  ['товар', 'товара', 'товаров']);   // '1 товар'
Number::declensionNum(3,  ['товар', 'товара', 'товаров']);   // '3 товара'
Number::declensionNum(25, ['товар', 'товара', 'товаров']);   // '25 товаров'
```

> If you only need the form of the word **without a number** - this is `Locales::datePhrase($n, $titles)`.

###`numberToWords($number)`
Number in words (English alphabet words).

---

## Comparison and stuff

###`compareNumbers($float1, $float2, $operator = '=')`
Comparing floating point numbers via epsilon (0.00001) - avoids errors
`0.1 + 0.2 != 0.3`. Operators: `=`/`eq`, `<`/`lt`, `>`/`gt`, `<=`, `>=`.

```php
if (Number::compareNumbers($sum, $expected, '=')) { ... }   // безопасно для float
if (Number::compareNumbers($price, 0, '>')) { ... }
```

###`priceCourse($price, $format = true, $float = null)`
Preparing the price for output: `$float === false` — round to the nearest whole; `$format` —
apply formatting.

```php
Number::priceCourse(1234.7, true, false);   // '1 235' (округлено + формат)
```

### `randomNumber($digits = 1)`, `microtimeDiff($a, $b)`
A random number of a given bit depth; the difference between the two is `microtime`.

---

## Recipes

**Counter in the interface:**

```php
echo Number::declensionNum($count, ['документ', 'документа', 'документов']);
```

**Price in product card:**

```php
echo Number::numFormat($product['price'], 0, ',', ' ') . ' ₽';
```

**Recording prices in the database (DECIMAL):**

```php
$row['price'] = Number::decimal($input['price'], 2);
```
