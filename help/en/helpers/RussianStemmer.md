# RussianStemmer - stemming Russian words

`App\Helpers\RussianStemmer` - converts a Russian word to its stem (Porter’s algorithm) for
fuzzy search: different forms of one word find each other. Used as
**instance**.

```php
use App\Helpers\RussianStemmer;

$stemmer = new RussianStemmer();
```

---

## Methods

###`stemWord($word)`
Return the stem of the word.

```php
$stemmer->stemWord('кресла');    // 'кресл'
$stemmer->stemWord('креслом');   // 'кресл'
$stemmer->stemWord('кресел');    // 'кресел' → приводится к общей основе с прочими формами
```

###`clearCache()`
Clear internal computed basis cache.

---

## Recipe

**Text indexing for search:**

```php
$stemmer = new RussianStemmer();
$stems = array();
foreach (preg_split('/\s+/u', mb_strtolower($text, 'UTF-8')) as $word) {
    $word = trim($word);
    if ($word !== '') { $stems[] = $stemmer->stemWord($word); }
}
// $stems сохраняем в поисковый индекс; запрос стеммим так же
```

> So “buy chairs” will find a document with the word “chair”. To correct the layout
> - [SearchText](SearchText.md).
