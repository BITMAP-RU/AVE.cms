# Document saving hooks

← [To the section “Hooks and events”](README.md)

The control panel, JSON API and system writer services use the same
events.
This is the main integration point for publishing in Telegram, Zen, CRM and others
services.

## Events

| Name | When |
| --- | --- |
| `content.document.saving` | Before recording; the data can be changed or rejected. |
| `content.document.saved` | After a successful commit and snapshot build. |
| `content.document.created` | After creation. |
| `content.document.updated` | After the update. |
| `content.document.published` | On first publication or transition 0 → 1. |
| `content.document.unpublished` | During the transition 1 → 0. |

They all receive `App\Content\Documents\DocumentSaveEvent`.

## Available data

```php
public static function beforeSave(DocumentSaveEvent $event)
{
    $title = $event->value('title');
    $code = $event->field('product_code');

    $event->setValue('title', trim((string) $title));
    $event->setField('product_code', strtoupper((string) $code));
}
```

| Method | Destination |
| --- | --- |
| `phase()` | `saving` or `saved`. |
| `operation()` | `create` or `update`. |
| `source()` | For example, `adminx` or API writer. |
| `documentId()`, `rubricId()`, `actorId()` | Operation IDs. |
| `isNew()` | Is the document being created? |
| `data()`, `value()`, `setValue()` | System properties of the document. |
| `fields()`, `field()`, `setField()` | Field values ​​by ID or alias. |
| `previous()` | Previous state when updating. |
| `snapshot()` | The final JSON snapshot in after-events. |
| `fail($message, $errors)` | Before-save rejection. |

Short names `title`, `alias`, `status`, `published_at`, `expire_at`,
`parent_id`, `template_id`, `breadcrumb_title`, `excerpt`, `tags`, `property` and
`in_search` are automatically mapped to document columns. Old name
`teaser` is read as an alias for `excerpt`, but in the new code use `excerpt`.

## Reject save

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

`fail()` is only taken into account before the entry. Writer turns it into a controlled
error and does not save partial data.

After events are executed after commit. Their exceptions are logged and not
roll back an already saved document. Therefore the handler must be
idempotent: calling again does not create a second post, payment or task.

## External publication

Don't send a slow HTTP request directly to `saving`: this will hold
user and transaction. In `published` write a small task in outbox with
unique key `service + document_id + revision`. Web-runner will process it
limited packages, will save attempts and allow you to repeat the error from the panel
management.
