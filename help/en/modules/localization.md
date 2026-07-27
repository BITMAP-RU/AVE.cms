# Translation of the module interface

The control panel language is selected separately from the site language. Primary language
is set during installation, and the switch is in the user menu
top bar and login screen. Changing the panel language does not change the address
documents, website content and language of public pages.

## Adding translation

Place the XML next to the admin part of the module:

```text
modules/example/admin/language/
├── ru/common.xml
└── en/common.xml
```

For a module that only exists inside a panel:

```text
<admin-directory>/modules/Example/language/en/common.xml
```

Dictionary example:

```xml
<?xml version="1.0" encoding="utf-8"?>
<language>
    <phrase data="example_title">Example</phrase>
    <phrase data="example_saved">Example saved</phrase>
</language>
```

Start the keys with the module code. Common buttons are already available as `btn_save`,
`btn_cancel`, `btn_delete`, `btn_close`.

In Twig:

```twig
<h1>{{ lang.example_title|default('Пример') }}</h1>
<button>{{ lang.btn_save|default('Сохранить') }}</button>
```

Plain static Twig text is also translated automatically. Stable
keys are needed primarily for phrases that are used from PHP and
JavaScript.

In JavaScript:

```js
Adminx.Toast.show(
  Adminx.t('example_saved', 'Пример сохранён'),
  'success'
);
```

The menu item is translated by the key `nav_<code>`, where `code` is taken from the section
`navigation` file `module.php`. If there is no translation, the panel shows the original
Russian signature, so an incomplete dictionary does not break the work.

After adding the JavaScript phrases, run:

```bash
php tools/adminx-i18n.php --build-client
```

The command will create a compact `client.xml`. Full server dictionary in each
The page is not embedded.

The module help can also be translated. The English copy of the current file should
lie in `help/en/modules/localization.md`; other languages use the same
directory structure.

The detailed technical contract is in
`docs/development/adminx-localization.md`.
