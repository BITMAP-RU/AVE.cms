# Locales - locale and localization

`App\Helpers\Locales` - current language, transliteration, localized dates.

```php
use App\Helpers\Locales;
```

---

## Language and locale

### `language()` is the current language.
### `set()` - Set the environment locale (usually called by the kernel).

---

## Transliteration

### `transliterate($value)` - Cyrillic → Latin.
### `transliterateSlug($value)` - stable transliteration profile for CNC.
### `lower($value)` - lower case, taking into account the locale.

```php
Locales::transliterate('Кресло-коляска');   // 'Kreslo-kolyaska'
```

---

## Localized dates

### `prettyDate($value)` - “beautiful” date output with Russian months.
### `translateDate($value)` — translate the names of months/days in the finished string.
### `humanDate($timestamp)` is a human-readable date with locale.
### `datePhrase($number, array $titles)` — word form by number (`[1, 2-4, 5-0]`).

```php
echo Locales::prettyDate($ts);                  // '14 июля 2026'
echo $n . ' ' . Locales::datePhrase($n, ['день','дня','дней']);
```

---

## Correlation with other helpers

| Problem | Where |
| --- | --- |
| Transliteration | `Locales::transliterate()` or `Str::slug()` (for CNC) |
| "N back" | `Locales::humanDate()` (`Date::humanDate()` left as a compatible proxy) |
| Formatting timestamp | `Date::formatTimestamp()` |
| Localized date with month-word | `Locales::prettyDate()` |
