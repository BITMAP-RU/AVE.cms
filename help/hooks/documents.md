# Хуки сохранения документа

← [К разделу «Хуки и события»](README.md)

Панель управления, JSON API и системные writer-сервисы используют одинаковые
события.
Это основная точка интеграции для публикации в Telegram, Дзен, CRM и других
сервисах.

## События

| Имя | Когда |
| --- | --- |
| `content.document.saving` | До записи; данные можно изменить или отклонить. |
| `content.document.saved` | После успешного commit и сборки snapshot. |
| `content.document.created` | После создания. |
| `content.document.updated` | После обновления. |
| `content.document.published` | При первой публикации или переходе 0 → 1. |
| `content.document.unpublished` | При переходе 1 → 0. |

Все они получают `App\Content\Documents\DocumentSaveEvent`.

## Доступные данные

```php
public static function beforeSave(DocumentSaveEvent $event)
{
    $title = $event->value('title');
    $code = $event->field('product_code');

    $event->setValue('title', trim((string) $title));
    $event->setField('product_code', strtoupper((string) $code));
}
```

| Метод | Назначение |
| --- | --- |
| `phase()` | `saving` или `saved`. |
| `operation()` | `create` или `update`. |
| `source()` | Например, `adminx` или API writer. |
| `documentId()`, `rubricId()`, `actorId()` | Идентификаторы операции. |
| `isNew()` | Создаётся ли документ. |
| `data()`, `value()`, `setValue()` | Системные свойства документа. |
| `fields()`, `field()`, `setField()` | Значения полей по ID или alias. |
| `previous()` | Предыдущее состояние при обновлении. |
| `snapshot()` | Итоговый JSON-снимок в after-событиях. |
| `fail($message, $errors)` | Отклонение before-сохранения. |

Короткие имена `title`, `alias`, `status`, `published_at`, `expire_at`,
`parent_id`, `template_id`, `breadcrumb_title`, `excerpt`, `tags`, `property` и
`in_search` автоматически сопоставляются колонкам документа. Старое имя
`teaser` читается как alias для `excerpt`, но в новом коде используйте `excerpt`.

## Отклонение сохранения

```php
public static function validateCertificate(DocumentSaveEvent $event)
{
    if ($event->rubricId() !== 12 || trim((string) $event->field('certificate')) !== '') {
        return;
    }

    $event->fail('Документ не сохранён', array(
        'fields[certificate]' => 'Укажите номер сертификата',
    ));
}
```

`fail()` учитывается только до записи. Writer превращает его в контролируемую
ошибку и не сохраняет частичные данные.

After-события выполняются после commit. Их исключения журналируются и не
откатывают уже сохранённый документ. Поэтому обработчик должен быть
идемпотентным: повторный вызов не создаёт второй пост, платёж или задачу.

## Внешняя публикация

Не отправляйте медленный HTTP-запрос непосредственно в `saving`: это удержит
пользователя и транзакцию. В `published` запишите небольшую задачу в outbox с
уникальным ключом `service + document_id + revision`. Web-runner обработает её
ограниченными пакетами, сохранит попытки и позволит повторить ошибку из панели
управления.
