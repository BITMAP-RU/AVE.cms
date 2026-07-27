# SearchText — текст для поиска

`App\Helpers\SearchText` — мелкие утилиты подготовки поисковых запросов.

```php
use App\Helpers\SearchText;
```

---

## Методы

### `containsRussian($text): bool`
Есть ли в тексте кириллица.
```php
SearchText::containsRussian('кресло');   // true
SearchText::containsRussian('chair');    // false
```

### `toRussian($input)`
Приводит раскладку к русской — «латиница, набранная по ошибке» → кириллица (по
раскладке клавиатуры).
```php
SearchText::toRussian('rhtckj');   // 'кресло'
```

---

## Рецепт

**Автоисправление раскладки перед поиском:**
```php
$q = Request::getStr('q');
if ($q !== '' && !SearchText::containsRussian($q)) {
    $fixed = SearchText::toRussian($q);
    // искать и по $q, и по $fixed — пользователь мог забыть переключить раскладку
}
```

> Для морфологии (разные формы слова) — [RussianStemmer](RussianStemmer.md).
