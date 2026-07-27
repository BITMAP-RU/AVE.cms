# Locales — локаль и локализация

`App\Helpers\Locales` — текущий язык, транслитерация, локализованные даты.

```php
use App\Helpers\Locales;
```

---

## Язык и локаль

### `language()` — текущий язык.
### `set()` — установить локаль окружения (обычно вызывается ядром).

---

## Транслитерация

### `transliterate($value)` — кириллица → латиница.
### `transliterateSlug($value)` — стабильный профиль транслитерации для ЧПУ.
### `lower($value)` — нижний регистр с учётом локали.
```php
Locales::transliterate('Кресло-коляска');   // 'Kreslo-kolyaska'
```

---

## Локализованные даты

### `prettyDate($value)` — «красивый» вывод даты с русскими месяцами.
### `translateDate($value)` — перевести названия месяцев/дней в готовой строке.
### `humanDate($timestamp)` — человекочитаемая дата с локалью.
### `datePhrase($number, array $titles)` — форма слова по числу (`[1, 2-4, 5-0]`).

```php
echo Locales::prettyDate($ts);                  // '14 июля 2026'
echo $n . ' ' . Locales::datePhrase($n, ['день','дня','дней']);
```

---

## Соотношение с другими хелперами

| Задача | Где |
| --- | --- |
| Транслитерация | `Locales::transliterate()` или `Str::slug()` (для ЧПУ) |
| «N назад» | `Locales::humanDate()` (`Date::humanDate()` оставлен как совместимый прокси) |
| Форматирование timestamp | `Date::formatTimestamp()` |
| Локализованная дата с месяцем-словом | `Locales::prettyDate()` |
