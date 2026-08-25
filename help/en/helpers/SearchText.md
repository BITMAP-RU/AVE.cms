# SearchText - search text

`App\Helpers\SearchText` provides shared query preparation for documents,
products, public suggestions, and control-panel global search.

```php
use App\Helpers\SearchText;
```

---

## Methods

### `containsRussian($text): bool`
Checks whether the text contains Cyrillic characters.

```php
SearchText::containsRussian('кресло');   // true
SearchText::containsRussian('chair');    // false
```

### `toLatin($input)` and `toRussian($input)`
Create transliterated variants. They do not switch the keyboard layout.

```php
SearchText::toLatin('ДБ-11'); // 'DB-11'
SearchText::toRussian('DB-11'); // 'ДБ-11'
```

### `switchKeyboardLayout($input)`
Corrects a query typed with the wrong keyboard layout.

```php
SearchText::switchKeyboardLayout('ВИ-11'); // 'DB-11'
```

### `variants($text): array`
Returns the literal spelling, transliteration, and corrected keyboard-layout
forms. For model codes without a separator it also adds a dash at letter-number
boundaries.

```php
SearchText::variants('ДБ11');
// includes: ДБ11, ДБ-11, DB11, DB-11

SearchText::variants('ВИ11');
// includes DB11 and DB-11 after correcting the layout
```

The literal query remains first and receives the highest search weight. Added
forms only broaden matching; stored product codes are not modified.

### `termGroups($text): array`
Splits a phrase into terms. Variants of one term form an OR group, while search
layers may require different groups with AND. Russian terms also receive a stem.

---

## Usage

Do not replace the original query. Pass all variants to the search layer:

```php
foreach (SearchText::variants(Request::getStr('q')) as $variant) {
    // rank the literal form higher and use the rest as fallbacks
}
```

> For morphology (different word forms) - [RussianStemmer](RussianStemmer.md).
