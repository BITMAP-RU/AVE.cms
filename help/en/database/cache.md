# Query cache

← [Back to DB section](README.md)

The `DB` facade can cache sample results. Settings are set **before**
request and act on **next** `query()` (reset after execution).
Implementation - `App\Common\Db\Core\QueryCache`.

---

## How it works

When the TTL is set, the result `getAll()` is stored in the cache according to the request key; at
Repeatedly making the same request, the data is taken from the cache, without accessing the database (see.
`queryHelper` → `QueryCache::get/save`).

```php
DB::setTtl(300);   // кешировать следующий запрос на 300 секунд
$rows = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i', 5)->getAll();
// повторный такой же запрос в течение 5 минут вернётся из кеша
```

---

## Methods

| Method | Destination |
| --- | --- |
| `setTtl($ttl = 0)` | Cache TTL for next request (sec). `0` - do not cache. |
| `setCache($cache_id = '', $extension = '')` | Explicit cache entry identifier/format. |
| `setTags($tags)` | Mark the cache with tags (for group invalidation). |
| `clearTags($tags)` | Reset cache by tags. |

All except `clearTags` return an instance - you can chain it:

```php
DB::setTtl(600)->setTags(array('products'));
$rows = DB::query('SELECT * FROM ' . $t . ' WHERE rubric_id = %i', 5)->getAll();
```

---

## Invalidation by tags

Tag caches and reset them when data changes:

```php
// чтение — с тегом
DB::setTtl(600)->setTags(array('products'));
$list = DB::query('SELECT * FROM ' . $products . ' WHERE active = 1')->getAll();

// запись — сбрасываем тег
DB::Insert($products, $newProduct);
DB::clearTags(array('products'));   // связанные кеши устареют
```

---

## Recommendations

- Cache **heavy/frequent** selections (lists, aggregates, directories), and not everything
  in a row.
- Always attach **tags** and reset them in the recording areas - otherwise you will get
  outdated data.
- TTL - “insurance” in case you forgot to reset the tag; set reasonable (minutes).
- The standard of thoughtful invalidation in the project - `DocumentRenderCache` /
  `ContentCacheInvalidator`.
