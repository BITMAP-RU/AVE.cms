# Arrangement and storage of fields

← [To the section “Document fields”](README.md)

## Definition and meaning

The field description is stored in the content table `rubric_fields`. Main properties:

| Property | Destination |
| --- | --- |
| `Id` | Stable field ID. |
| `rubric_id` | The category to which the field belongs. |
| `rubric_field_type` | Registered type code. |
| `rubric_field_alias` | Human-readable stable key for APIs and templates. |
| `rubric_field_settings` | JSON with settings and validation rules. |
| `rubric_field_default` | Default value; for older types it may contain legacy configuration. |
| `rubric_field_numeric` | The need for a numerical index. |
| `rubric_field_template` | Dotted output in the document. |
| `rubric_field_template_request` | Dotted output in query/list. |

Document values are stored in pair `document_id + rubric_field_id` in
`document_fields`. The long tail of the value may lie in `document_fields_text`.
There is no need to write directly to these tables: the control panel and JSON API use a single
writer, which normalizes the field, updates both parts, and rebuilds the snapshot.

## Scalar and JSON

The `save()` method returns a string by contract. A simple field returns
a canonical scalar value, and a structural one encodes the array in JSON without
Unicode and URL escaping.

```php
public function save(FieldContext $ctx)
{
    return Json::encode(array(
        'width' => (float) $ctx->value['width'],
        'height' => (float) $ctx->value['height'],
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
```

When reading `FieldValueCodec::decodeStructured()` understands JSON and temporarily
understands old PHP `serialize` without class resolution.

Don't manually store JSON in a scalar field for the sake of a few visual options.
The view settings belong to `rubric_field_settings` and the document value
must contain only the data of a specific document.

## Numeric index

If `isNumeric()` returns `true`, writer additionally writes the number to
`field_number_value`. This index is used by sorting, query conditions, and
range filters. `save()` of this type must return a canonical number with
dot without spaces or units of measurement.

##FieldContext

Each type method receives `FieldContext`:

```php
$ctx->value;                 // текущее значение
$ctx->definition;            // строка rubric_fields
$ctx->document;              // документ, если доступен на этом этапе
$ctx->rubric;                // рубрика, если доступна
$ctx->mode;                  // edit, view, filter или save
$ctx->extra;                 // дополнительные данные вызывающего кода

$ctx->fieldId();
$ctx->alias();
$ctx->type();
$ctx->rubricId();
$ctx->inputName();           // fields[123]
$ctx->inputName('[]');       // fields[123][]
$ctx->settings();
$ctx->setting('unit', '');
$ctx->legacyDefault();
```

## Saving order

1. `content.field.normalizing` can change the input value and definition.
2. Called `FieldType::save()`.
3. `FieldValueCodec` converts the result to a storage string.
4. `content.field.normalized` can replace the normalized result.
5. `FieldValidator` checks the field rules.
6. `content.field.saving` can replace the value or unwrite the field.
7. Writer updates the short/text parts and the numeric index.
8. Called `content.field.saved`.
9. After saving the document, its JSON snapshot is reconstructed.

For all these steps to be carried out, the module does not have to update itself
value tables via SQL.
