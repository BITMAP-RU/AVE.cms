# Number — числа

`App\Helpers\Number` — форматирование чисел, размеров и русские склонения.

```php
use App\Helpers\Number;
```

---

## Форматирование

### `numFormat($number, $decimal = 0, $after = ',', $thousand = '.')`
Обёртка `number_format`. **Важно:** для «пустого»/нулевого значения возвращает
пустую строку `''`, а не `0`.
```php
Number::numFormat(1234567, 0, ',', ' ');   // '1 234 567'
Number::numFormat(1234.5, 2, '.', ' ');    // '1 234.50'
Number::numFormat(0);                       // '' ← учтите в вёрстке
```

### `decimal($number, $scale = 2)`
Нормализует число для **DECIMAL-полей БД**: всегда с точкой, ноль не скрывает.
```php
Number::decimal('1 234,5', 2);   // '1234.50'
Number::decimal(0);              // '0.00'
```

### `numberFormat($string, $type = null)` / `numberDeFormat($string)`
Форматирование по типу и обратное снятие форматирования (строка → «сырое» число).

---

## Размеры

### `formatSize($size)` / `getTextSize($size)`
Байты → человекочитаемый размер.
```php
Number::formatSize(1048576);   // '1 МБ'
Number::formatSize(2560);      // '2.5 КБ'
```

---

## Русские склонения (важно для UI)

### `declensionNum($number, $titles)`
Возвращает **число + правильную форму** слова. `$titles` — массив из 3 форм:
`[1, 2-4, 5-0]`.
```php
Number::declensionNum(1,  ['товар', 'товара', 'товаров']);   // '1 товар'
Number::declensionNum(3,  ['товар', 'товара', 'товаров']);   // '3 товара'
Number::declensionNum(25, ['товар', 'товара', 'товаров']);   // '25 товаров'
```
> Если нужна только форма слова **без числа** — это `Locales::datePhrase($n, $titles)`.

### `numberToWords($number)`
Число прописью (английский алфавит слов).

---

## Сравнение и прочее

### `compareNumbers($float1, $float2, $operator = '=')`
Сравнение чисел с плавающей точкой через эпсилон (0.00001) — избегает ошибок
`0.1 + 0.2 != 0.3`. Операторы: `=`/`eq`, `<`/`lt`, `>`/`gt`, `<=`, `>=`.
```php
if (Number::compareNumbers($sum, $expected, '=')) { ... }   // безопасно для float
if (Number::compareNumbers($price, 0, '>')) { ... }
```

### `priceCourse($price, $format = true, $float = null)`
Подготовка цены к выводу: `$float === false` — округлить до целого; `$format` —
применить форматирование.
```php
Number::priceCourse(1234.7, true, false);   // '1 235' (округлено + формат)
```

### `randomNumber($digits = 1)`, `microtimeDiff($a, $b)`
Случайное число заданной разрядности; разница между двумя `microtime`.

---

## Рецепты

**Счётчик в интерфейсе:**
```php
echo Number::declensionNum($count, ['документ', 'документа', 'документов']);
```

**Цена в карточке товара:**
```php
echo Number::numFormat($product['price'], 0, ',', ' ') . ' ₽';
```

**Запись цены в БД (DECIMAL):**
```php
$row['price'] = Number::decimal($input['price'], 2);
```
