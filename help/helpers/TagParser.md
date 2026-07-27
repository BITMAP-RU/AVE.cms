# TagParser — разбор тегов в контенте

`App\Helpers\TagParser` — парсит спец-теги в шаблонах и контенте документов и
позволяет регистрировать собственные обработчики тегов.

```php
use App\Helpers\TagParser;
```

---

## Регистрация и разбор

### `registerHandler($tagName, $handler, $priority = 0, $pattern = null)`
Зарегистрировать обработчик тега. `$handler` вызывается при встрече тега и
возвращает замену. `$pattern` — своя регулярка (иначе стандартный синтаксис тега).
```php
TagParser::registerHandler('year', function () {
    return date('Y');
});
```

### `parse($text)`
Разобрать текст, заменив все известные теги результатами их обработчиков.
```php
$html = TagParser::parse($template);   // [year] → 2026
```

### `getRegisteredTags()` — список зарегистрированных тегов.

---

## Контекст документа

### `setCurrentDocument($document)` / `getCurrentDocument()`
Задать/получить документ, в контексте которого разбираются теги полей.

### `parseDocumentFields($content, $document = null)`
Подставить значения полей документа.
### `parseDocumentTags($content, $document = null)`
Разобрать теги документа.
### `parseModuleTags($template, $installModules = null)`
Разобрать теги модулей в шаблоне.

```php
TagParser::setCurrentDocument($doc);
$content = TagParser::parseDocumentFields($rawContent, $doc);
```

### `clearCache()` — сбросить внутренний кеш парсера.

---

## Рецепт

**Свой тег для вывода телефона из настроек:**
```php
TagParser::registerHandler('phone', function () {
    return Settings::get('shop.phone');
});
echo TagParser::parse($blockHtml);   // [phone] → +7 …
```
