# Date — дата и время

`App\Helpers\Date` — конвертация между SQL-строками и Unix-timestamp.
Локализованный вывод делегируется `Locales`, старые вызовы `Date::humanDate()` и
`Date::datePhrase()` сохранены для совместимости.

```php
use App\Helpers\Date;
```

---

## Текущее время

### `nowSql()` — текущее время в формате SQL `Y-m-d H:i:s`
```php
$row['created_at'] = Date::nowSql();   // '2026-07-14 12:30:00'
```

### `nowTimestamp()` — текущий Unix-timestamp (`time()`).

---

## Ввод из форм → БД

### `htmlDateTimeToSql($value)`
Значение из `<input type="datetime-local">` (`2026-07-14T12:30`) → SQL-строка.
Пустое значение → `null`. Секунды дополняются нулями.
```php
Date::htmlDateTimeToSql('2026-07-14T12:30');   // '2026-07-14 12:30:00'
Date::htmlDateTimeToSql('');                    // null
```

---

## Конвертации timestamp ↔ строка

| Метод | Направление |
| --- | --- |
| `formatTimestamp($ts, $format = 'Y-m-d H:i:s')` | timestamp → строка по формату |
| `timestampToDate($ts)` / `timestampToTime($ts)` | timestamp → дата / время |
| `dateToTimestamp($date)` / `datetime2timestamp($s)` | строка → timestamp |
| `sqlDateToTimestamp($d)` / `sqlTimeToTimestamp($dt)` | SQL-строка → timestamp |
| `_make_timestamp($string)` | произвольная строка → timestamp |

```php
Date::formatTimestamp($row['published'], 'd.m.Y H:i');   // '14.07.2026 12:30'
$ts = Date::sqlDateToTimestamp('2026-07-14');
```

---

## Человекочитаемый вид

### `humanDate($date)`
Принимает **timestamp** и возвращает «N единиц назад» с правильным склонением.
```php
Date::humanDate(time() - 3600);        // '1 час назад'
Date::humanDate(time() - 5 * 86400);   // '5 дней назад'
```

### `datePhrase($number, $titles)`
Только правильная **форма слова** (без числа) по русским правилам. `$titles` —
`[1, 2-4, 5-0]`.
```php
Date::datePhrase(3, ['день', 'дня', 'дней']);   // 'дня'
echo $n . ' ' . Date::datePhrase($n, ['минута','минуты','минут']);
```

---

## Рецепты

**Сохранить дату публикации из формы:**
```php
$data['document_published'] = Date::htmlDateTimeToSql($_POST['published']);
```

**Показать «изменено N назад» в списке:**
```php
echo 'изменено ' . Date::humanDate($doc['document_changed']);
```

> Для локализованного вывода месяцев/дней («14 июля 2026») см.
> [Locales](Locales.md): `prettyDate()`, `translateDate()`.
