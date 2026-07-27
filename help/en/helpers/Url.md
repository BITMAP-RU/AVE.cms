# Url - links and redirects

`App\Helpers\Url` - link building, CNC conversion, collection of redirect goals.

```php
use App\Helpers\Url;
```

> Most methods return a **reference string** - they do not perform the transition themselves.
> Do the redirect itself via `Request::redirect($url)` / header `Location`.

---

## Link building

###`to($path = '')`
Normalized path from the site root (always with leading `/`).

```php
Url::to('catalog/products');   // '/catalog/products'
Url::to('/media');             // '/media'
```

### `asset($path)` - the same as `to()` (for statics).

###`uploads($path = '')`
Link to the download directory (the base is the constant `UPLOAD_URL`, by default `/uploads`).

```php
Url::uploads('articles/doc_37/1.jpg');   // '/uploads/articles/doc_37/1.jpg'
```

### `site()` / `home()`
Base site URL / home link.

---

## CNC and normalization

###`rewrite($value)`
CNC value conversion - **works only if `REWRITE_MODE` is enabled**,
otherwise it returns the value as is.

### `prepare($url)` / `prepareUrl($url)` - normalize the URL.
### `canonical($url)` - canonical link.

---

## Redirects and referrer

| Method | Return |
| --- | --- |
| `redirect($exclude = '')` | The redirect target string (the parameter can be excluded). |
| `getRedirectLink($exclude = '')` | Same thing, no side effects. |
| `referer()` / `getRefererLink()` | Referrer. |
| `printLink()` | Print version link. |

```php
$back = Url::getRedirectLink();
// затем выполнить переход:
Request::redirect($back);
```

---

## Recipes

**Link to product image in template:**

```php
$img = Url::uploads($product['image']);
```

**Back button after action:**

```php
Request::redirect(Url::getRefererLink() ?: Url::home());
```
