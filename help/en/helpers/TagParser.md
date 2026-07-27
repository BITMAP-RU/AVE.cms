# TagParser - parsing tags in content

`App\Helpers\TagParser` - parses special tags in document templates and content and
allows you to register your own tag handlers.

```php
use App\Helpers\TagParser;
```

---

## Registration and parsing

###`registerHandler($tagName, $handler, $priority = 0, $pattern = null)`
Register a tag handler. `$handler` is called when a tag is encountered and
returns replacement. `$pattern` - its own regular character (aka standard tag syntax).

```php
TagParser::registerHandler('year', function () {
    return date('Y');
});
```

###`parse($text)`
Parse the text, replacing all known tags with the results of their handlers.

```php
$html = TagParser::parse($template);   // [year] → 2026
```

### `getRegisteredTags()` - list of registered tags.

---

## Document context

### `setCurrentDocument($document)` / `getCurrentDocument()`
Set/get the document in the context of which field tags are parsed.

###`parseDocumentFields($content, $document = null)`
Substitute document field values.
###`parseDocumentTags($content, $document = null)`
Parse document tags.
###`parseModuleTags($template, $installModules = null)`
Parse module tags in the template.

```php
TagParser::setCurrentDocument($doc);
$content = TagParser::parseDocumentFields($rawContent, $doc);
```

### `clearCache()` — reset the internal parser cache.

---

## Recipe

**Custom tag for displaying phone from settings:**

```php
TagParser::registerHandler('phone', function () {
    return Settings::get('shop.phone');
});
echo TagParser::parse($blockHtml);   // [phone] → +7 …
```
