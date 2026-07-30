# Field settings and validation

← [To the section “Document fields”](README.md)

## settingsSchema()

The type describes settings with an array of descriptors. `FieldSettingsForm` builds from
They control the category constructor and symmetrically normalize POST to JSON.

```php
public function settingsSchema()
{
    return array(
        array(
            'key' => 'unit',
            'type' => 'text',
            'label' => 'Единица измерения',
            'default' => 'балл',
            'hint' => 'Показывается после значения.',
        ),
        array(
            'key' => 'presentation',
            'type' => 'select',
            'label' => 'Вид',
            'options' => array(
                'number' => 'Число',
                'stars' => 'Звёзды',
            ),
            'default' => 'number',
        ),
    );
}
```

Supported types of controls:

| `type` | Meaning |
| --- | --- |
| `text` | Line. |
| `int` | Integer. |
| `number` | Number. |
| `bool` | Switch. |
| `select` | One option from `options`. |
| `list` | An array of strings, one per line. |
| `map` | List of stable pairs `key → signature`. |
| `rubric` | Array of category IDs. |
| `directory` | Select a shared value directory. |

There is no need to read settings manually from JSON:

```php
$unit = (string) $ctx->setting('unit', '');
$all = $ctx->settings();
```

## validationSchema()

`AbstractFieldType` already adds suitable basic rules depending on
`isNumeric()`, `isMultiple()`, `isFile()` and `isChoice()`. The method can be
redefine completely:

```php
public function validationSchema()
{
    return array(
        array('key' => 'required', 'type' => 'bool', 'label' => 'Обязательное'),
        array('key' => 'min', 'type' => 'number', 'label' => 'Минимум'),
        array('key' => 'max', 'type' => 'number', 'label' => 'Максимум'),
    );
}
```

Standard rules:

| Rule | Destination |
| --- | --- |
| `required` | The value is required. |
| `min`, `max` | Number boundaries. |
| `minLength`, `maxLength` | Text length. |
| `minCount`, `maxCount` | Number of structure elements. |
| `allowedValues` | Only allowed keys. |
| `allowedExtensions` | File extensions separated by commas. |
| `regex` | Regular expression. |
| `date`, `email`, `url`, `numeric` | Format checks. |

## Special module check

If the standard rules are not enough, subscribe to
`content.field.validating`. The handler must be limited to its type:

```php
use App\Common\LifecycleEvent;

public static function validate(LifecycleEvent $event)
{
    if ($event->value('type') !== 'rating') {
        return;
    }

    $value = (int) $event->value('value', 0);
    if ($value < 0 || $value > 10) {
        $event->setResult('Рейтинг должен быть от 0 до 10');
    }
}
```

`null` as a result means there is no error, the line is the message next to
field. The subscription is announced in `module.php`; the full contract is described in the section
[Field hooks](../hooks/fields.md).

## Changing settings

Changing the caption, visual format and unit of measurement should not require
rewriting documents. Changing the storage form (`scalar → JSON`, new
key semantics) is a data migration and requires a new version of the module,
separate migration and backward reading compatibility for the update period.
