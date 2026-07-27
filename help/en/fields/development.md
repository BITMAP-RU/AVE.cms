# Develop your own field type

← [To the section “Document fields”](README.md)

The new application type comes as an installable module. So his classes,
rights, migrations, and deletion remain one managed unit.

## 1. Type class

Example of a numerical rating:

```php
<?php

namespace App\Modules\Reviews\Fields;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

use App\Content\Fields\AbstractFieldType;
use App\Content\Fields\FieldContext;

class RatingField extends AbstractFieldType
{
    public function code()
    {
        return 'rating';
    }

    public function name()
    {
        return 'Рейтинг';
    }

    public function isNumeric()
    {
        return true;
    }

    public function settingsSchema()
    {
        return array(
            array('key' => 'maximum', 'type' => 'int',
                'label' => 'Максимум', 'default' => 5),
        );
    }

    public function renderEdit(FieldContext $ctx)
    {
        $max = max(1, min(100, (int) $ctx->setting('maximum', 5)));
        return '<input class="input" type="number" min="0" max="' . $max
            . '" name="' . $this->attr($ctx->inputName())
            . '" value="' . $this->attr($ctx->value) . '">';
    }

    public function renderView(FieldContext $ctx)
    {
        if (trim((string) $ctx->value) === '') {
            return '';
        }
        return $this->e($ctx->value) . '&nbsp;/&nbsp;'
            . max(1, (int) $ctx->setting('maximum', 5));
    }

    public function save(FieldContext $ctx)
    {
        $raw = trim(str_replace(',', '.', (string) $ctx->value));
        if ($raw === '') {
            return '';
        }
        if (!is_numeric($raw)) {
            throw new \RuntimeException('Рейтинг должен быть числом');
        }
        return (string) (0 + $raw);
    }
}
```

`AbstractFieldType` provides safe `e()`/`attr()`, standard
validation and empty filter implementations. The code must be unique and consist of
Latin letters, numbers and `_` do not change after release.

## 2. Placement in a package

```text
modules/reviews/
├── app/
│   ├── module.php
│   └── Fields/
│       └── RatingField.php
└── admin/
    └── module.php
```

The class is in `app`, and the type must be declared in **both** runtime-manifest. Public
`app/module.php` serves the site output:

```php
<?php

use App\Modules\Reviews\Fields\RatingField;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

return array(
    'code' => 'reviews',
    'name' => 'Отзывы',
    'version' => '1.0.0',
    'lifecycle' => array('managed' => true),
    'field_types' => array(
        array('class' => RatingField::class, 'creatable' => true),
    ),
);
```

Administrative `admin/module.php` registers the same class for the constructor
rubrics and document editor:

```php
<?php

use App\Modules\Reviews\Fields\RatingField;

defined('BASEPATH') || die('Direct access to this location is not allowed.');

return array(
    'code' => 'reviews',
    'name' => 'Отзывы',
    'version' => '1.0.0',
    'lifecycle' => array('managed' => true),
    'package' => array('removable' => true),
    'field_types' => array(
        array('class' => RatingField::class, 'creatable' => true),
    ),
    'admin_extension' => array(
        'url' => '/reviews',
        'icon' => 'ti ti-message-star',
        'feature' => 'Отзывы и тип поля рейтинга',
    ),
);
```

For a composite package, namespace `App\Modules\Reviews` automatically specifies
to `modules/reviews/app` and is available to both runtimes. Manual `require` is not needed.
If the type is declared only in `app/module.php`, the public site will read it, but
The control panel won't offer it in the designer; only in `admin/module.php` - reverse
error.

`creatable = false` is only used for a type that must continue
serve existing fields, but should no longer be offered for creation.

## 3. Installation and activation

1. Install the package in **System → Modules**.
2. Open **Categories and Fields → Field Types**.
3. Enable the new type to create.
4. Create a test rubric and field.
5. Check for empty, valid, and invalid values.
6. Check the output both on the document page and in the query.
7. Check the JSON API of the document: the type and normalized value must be
   predictable for external integration.

## 4. When filters are needed

A type participates in a directory if it overrides:

```php
public function getFilterTypes();
public function buildFilterOptions($fieldId, $rubricId);
public function normalizeFilterValue($value);
public function applyFilter($fieldId, $selected);
```

`applyFilter()` returns `array('sql' => ' ...', 'args' => array(...))`.
You cannot insert a custom value directly into SQL. Use
DB placeholders and limit the allowed options.

## 5. Review before release

- all PHP passes `php -l` on PHP 7.3;
- `code()` does not conflict with installed types;
- scalar is stored as a string, the structure is JSON;
- `renderEdit()` uses `$ctx->inputName()`;
- user data is escaped in HTML;
- `save()` normalizes the same input the same;
- an empty value does not turn into a dummy `0` or an empty object;
- the module cannot be deleted while the type is being used by the category;
- the new storage version is accompanied by migration and a description of the update.
