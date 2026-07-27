# Date - date and time

`App\Helpers\Date` - conversion between SQL strings and Unix timestamp.
Localized output is delegated to `Locales`, old calls to `Date::humanDate()` and
`Date::datePhrase()` retained for compatibility.

```php
use App\Helpers\Date;
```

---

## Current time

### `nowSql()` - current time in SQL format `Y-m-d H:i:s`

```php
$row['created_at'] = Date::nowSql();   // '2026-07-14 12:30:00'
```

### `nowTimestamp()` — current Unix-timestamp (`time()`).

---

## Input from forms → DB

###`htmlDateTimeToSql($value)`
Value from `<input type="datetime-local">` (`2026-07-14T12:30`) → SQL string.
Empty value → `null`. Seconds are padded with zeros.

```php
Date::htmlDateTimeToSql('2026-07-14T12:30');   // '2026-07-14 12:30:00'
Date::htmlDateTimeToSql('');                    // null
```

---

## Conversions timestamp ↔ string

| Method | Direction |
| --- | --- |
| `formatTimestamp($ts, $format = 'Y-m-d H:i:s')` | timestamp → format string |
| `timestampToDate($ts)` / `timestampToTime($ts)` | timestamp → date/time |
| `dateToTimestamp($date)` / `datetime2timestamp($s)` | string → timestamp |
| `sqlDateToTimestamp($d)` / `sqlTimeToTimestamp($dt)` | SQL string → timestamp |
| `_make_timestamp($string)` | arbitrary string → timestamp |

```php
Date::formatTimestamp($row['published'], 'd.m.Y H:i');   // '14.07.2026 12:30'
$ts = Date::sqlDateToTimestamp('2026-07-14');
```

---

## Human readable view

###`humanDate($date)`
Takes **timestamp** and returns "N units back" with the correct declination.

```php
Date::humanDate(time() - 3600);        // '1 час назад'
Date::humanDate(time() - 5 * 86400);   // '5 дней назад'
```

###`datePhrase($number, $titles)`
Only the correct **word form** (without number) according to Russian rules. `$titles` —
`[1, 2-4, 5-0]`.

```php
Date::datePhrase(3, ['день', 'дня', 'дней']);   // 'дня'
echo $n . ' ' . Date::datePhrase($n, ['минута','минуты','минут']);
```

---

## Recipes

**Save publication date from form:**

```php
$data['document_published'] = Date::htmlDateTimeToSql($_POST['published']);
```

**Show “modified N ago” in the list:**

```php
echo 'изменено ' . Date::humanDate($doc['document_changed']);
```

> For localized display of months/days (“July 14, 2026”), see
> [Locales](Locales.md): `prettyDate()`, `translateDate()`.
