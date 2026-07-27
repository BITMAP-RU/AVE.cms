# Secure - input sanitization and XSS

`App\Helpers\Secure` - sanitize user input at **input** (before
storage/processing). For safe **output** in HTML, Twig `{{ }}` is enough
or `Str::escape()`.

```php
use App\Helpers\Secure;
```

---

## Methods

###`sanitize($string, $trim = false)`
Basic cleaning: `FILTER_SANITIZE_STRING` + `trim` + `stripslashes` + `strip_tags`
+ replacement of some characters. Returns a "flat" safe string without tags.

```php
Secure::sanitize('<b>Привет</b>  ');   // 'Привет'
```

###`cleanOut($string)`
Decodes HTML entities and strips slashes (reverse preparation for repeated
use of value).

```php
Secure::cleanOut('Цена &lt;100&gt;');   // 'Цена <100>'
```

###`cleanSanitize($string, $trim = false, $end_char = '…')`
`cleanOut` + `sanitize` in one call (for dirty input from legacy).

###`arrayXss($array, $stripslashes = false)`
Recursive XSS scraping **of the entire array** (for example, `$_POST`).

```php
$clean = Secure::arrayXss($_POST);
```

###`decodeXss($string)`
Decode a previously encoded value.

###`sanitizePhoneNumber($phone)`
Normalize the phone (remove extra characters).

```php
Secure::sanitizePhoneNumber('+7 (999) 123-45-67');   // '79991234567'
```

---

## Recipes

**Clear title before recording:**

```php
$title = Secure::sanitize(Request::postStr('title'), true);
```

**Bulk form clearing:**

```php
$data = Secure::arrayXss($_POST);
```

> Do not use `Secure::sanitize()` for fields where HTML is acceptable (descriptions,
> WYSIWYG) - it will cut out the tags. There needs to be a separate HTML cleanup/whitelist.
