# Публичный вывод и шаблоны полей

← [К разделу «Поля документов»](README.md)

## Два режима

- `doc` — поле на странице документа;
- `req` — поле внутри запроса, списка или карточки.

По умолчанию `doc` вызывает `renderView()`, а `req` — `renderFilter()`. Для
галерей и связей поведение request-режима может быть строже, чтобы тяжёлый HTML
не появлялся в каждой карточке без явного шаблона.

## Приоритет вывода

Runtime выбирает первый подходящий вариант:

1. непустой шаблон конкретного поля из БД;
2. точечный PHP-шаблон типа/поля;
3. стандартный метод типа `renderView()` или `renderFilter()`.

Это позволяет менять один сайт или одно поле без копирования всего класса типа.

## Точечные PHP-шаблоны

Шаблоны располагаются в:

```text
system/App/Content/Fields/Templates/<type>/
```

Для документного режима используются имена:

```text
field-doc-<field-id>-<template-id>.php
field-doc-<field-alias>-<template-id>.php
field-doc-<field-id>.php
field-doc-<field-alias>.php
field-doc.php
```

Для режима запроса замените `doc` на `req`. Более конкретный файл имеет
приоритет. `template-id` — необязательный ID шаблона документа/запроса,
переданный runtime.

В подключённом файле доступны:

```php
$template; // PublicFieldTemplateContext
$value;    // исходное значение
$items;    // нормализованный список элементов
$field;    // определение rubric_fields
```

Пример для изображения:

```php
<?php if (!$template->isEmpty()): ?>
  <?php $image = $template->first(); ?>
  <picture class="article-cover">
    <source srcset="<?= $template->escape($template->webp($image['url'])) ?>"
            type="image/webp">
    <img src="<?= $template->escape($image['url']) ?>"
         alt="<?= $template->escape($image['description']) ?>">
  </picture>
<?php endif; ?>
```

Методы контекста:

| Метод | Результат |
| --- | --- |
| `type()`, `mode()` | Тип и `doc`/`req`. |
| `value()` | Исходное значение. |
| `field()`, `fieldId()`, `alias()` | Метаданные поля. |
| `templateId()` | ID текущего шаблона. |
| `items()`, `first()`, `isEmpty()` | Нормализованные данные. |
| `escape($value)` | HTML-экранирование. |
| `thumbnail($url, $size)` | URL превью заданного размера. |
| `webp($url)` | Путь WebP-производной. |
| `host()` | Базовый адрес сайта. |

Режимы и размеры `thumbnail()`, жизненный цикл кеша и управление производными
файлами описаны в разделе [Медиа и миниатюры](../media/README.md).

Не выполняйте SQL в шаблоне. Если выводу нужны дополнительные данные, подготовьте
их в типе поля, сервисе модуля или hook до рендеринга.

## Шаблон из БД

Шаблоны `rubric_field_template` и `rubric_field_template_request` поддерживают:

- `[tag:value]` — всё значение;
- `[tag:parametr:0]`, `[tag:parametr:1]` — части legacy-значения;
- `[tag:label]`, `[tag:alias]`, `[tag:default]` — свойства поля;
- `[tag:if_empty]...[/tag:if_empty]` и `if_notempty` с `[tag:else]`;
- теги создания превью и `[tag:img:...]`.

Для нового структурного типа предпочтительнее метод класса или PHP-шаблон с
`PublicFieldTemplateContext`: он работает с именованными JSON-данными и не
зависит от позиций через `|`.
