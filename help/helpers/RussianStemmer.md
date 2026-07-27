# RussianStemmer — стемминг русских слов

`App\Helpers\RussianStemmer` — приводит русское слово к основе (алгоритм Портера) для
нечёткого поиска: разные формы одного слова находят друг друга. Используется как
**инстанс**.

```php
use App\Helpers\RussianStemmer;

$stemmer = new RussianStemmer();
```

---

## Методы

### `stemWord($word)`
Вернуть основу слова.
```php
$stemmer->stemWord('кресла');    // 'кресл'
$stemmer->stemWord('креслом');   // 'кресл'
$stemmer->stemWord('кресел');    // 'кресел' → приводится к общей основе с прочими формами
```

### `clearCache()`
Очистить внутренний кеш вычисленных основ.

---

## Рецепт

**Индексация текста для поиска:**
```php
$stemmer = new RussianStemmer();
$stems = array();
foreach (preg_split('/\s+/u', mb_strtolower($text, 'UTF-8')) as $word) {
    $word = trim($word);
    if ($word !== '') { $stems[] = $stemmer->stemWord($word); }
}
// $stems сохраняем в поисковый индекс; запрос стеммим так же
```

> Так «купить кресла» найдёт документ со словом «кресло». Для исправления раскладки
> — [SearchText](SearchText.md).
