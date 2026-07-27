# SearchText - search text

`App\Helpers\SearchText` - small utilities for preparing search queries.

```php
use App\Helpers\SearchText;
```

---

## Methods

###`containsRussian($text): bool`
Is there a Cyrillic alphabet in the text?

```php
SearchText::containsRussian('кресло');   // true
SearchText::containsRussian('chair');    // false
```

###`toRussian($input)`
Reduces the layout to Russian - “Latin, typed by mistake” → Cyrillic (according to
keyboard layout).

```php
SearchText::toRussian('rhtckj');   // 'кресло'
```

---

## Recipe

**Auto-correct layout before searching:**

```php
$q = Request::getStr('q');
if ($q !== '' && !SearchText::containsRussian($q)) {
    $fixed = SearchText::toRussian($q);
    // искать и по $q, и по $fixed — пользователь мог забыть переключить раскладку
}
```

> For morphology (different word forms) - [RussianStemmer](RussianStemmer.md).
