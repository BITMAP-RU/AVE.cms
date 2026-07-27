# Document fields

The field defines more than just the HTML input. One type owns a complete value contract:

- editing a document in the control panel;
- field settings in the category constructor;
- normalization and server validation;
- storage format and numeric index;
- output in the document and in query elements;
- available catalog filters;
- events before and after processing the value.

Types are registered by `App\Content\Fields\FieldRegistry`. The kernel contains a basic set
in `system/App/Content/Fields/Types`, and the application type is recommended to be supplied
along with the module. The control panel and public site use the same registry,
therefore
There is no separate field handler for the old admin panel.

## Section map

| Page | Destination |
| --- | --- |
| [Type reference](types.md) | What each standard type does and how the recommended types differ from legacy types. |
| [Device and storage](storage.md) | Tables, `FieldContext`, scalar/JSON, numeric index and processing steps. |
| [Settings and Validation](settings.md) | `settingsSchema()`, available controls and standard verification rules. |
| [Development of your own type](development.md) | Working class field, registration via module and verification. |
| [Public output and templates](templates.md) | `doc`/`req`, dot PHP templates and accessible context. |
| [Type Management](operations.md) | Inclusion for new categories, compatibility and safe removal of the module. |

## Basic type selection

For a new project, first use generic native types:

| Problem | Type |
| --- | --- |
| Plain, rich text or code | `content` |
| Number, price, percentage, unit of measurement | `number` |
| One or more stable options | `choice` |
| Date or date with time | `date_time` |
| Email, phone or URL | `contact` |
| Value range | `range` |
| Length, width and height | `dimensions` |
| Multiple cargo spaces | `packages` |
| Structured address | `address` |
| Period with beginning and ending | `period` |

Old types (`single_line`, `multi_line`, `drop_down`, `image_*` and others)
remain full-fledged contracts for existing data. They cannot be en masse
rename without separate migration of values and templates.

## Where to manage

- **Categories and fields → Field types** - what installed types are available when
  creating a new field.
- **Headings and fields → Headings** — adding a field, its settings, width,
  default value and position in the document editor.
- **Documents**—enter a value according to the rules of the selected type.

Disabling a type only hides it from the creation list. Already existing fields
continue to be read, edited and published.
