# Перевод интерфейса модуля

Язык панели управления выбирается отдельно от языка сайта. Начальный язык
задаётся при установке, а переключатель находится в пользовательском меню
верхней панели и на экране входа. Смена языка панели не меняет адреса
документов, содержимое сайта и язык публичных страниц.

## Добавление перевода

Положите XML рядом с административной частью модуля:

```text
modules/example/admin/language/
├── ru/common.xml
└── en/common.xml
```

Для модуля, который существует только внутри панели:

```text
<admin-directory>/modules/Example/language/en/common.xml
```

Пример словаря:

```xml
<?xml version="1.0" encoding="utf-8"?>
<language>
    <phrase data="example_title">Example</phrase>
    <phrase data="example_saved">Example saved</phrase>
</language>
```

Начинайте ключи с кода модуля. Общие кнопки уже доступны как `btn_save`,
`btn_cancel`, `btn_delete`, `btn_close`.

В Twig:

```twig
<h1>{{ lang.example_title|default('Пример') }}</h1>
<button>{{ lang.btn_save|default('Сохранить') }}</button>
```

Обычный статический текст Twig также переводится автоматически. Стабильные
ключи нужны в первую очередь для фраз, которые используются из PHP и
JavaScript.

В JavaScript:

```js
Adminx.Toast.show(
  Adminx.t('example_saved', 'Пример сохранён'),
  'success'
);
```

Пункт меню переводится ключом `nav_<code>`, где `code` взят из секции
`navigation` файла `module.php`. Если перевода нет, панель показывает исходную
русскую подпись, поэтому неполный словарь не ломает работу.

После добавления фраз JavaScript выполните:

```bash
php tools/adminx-i18n.php --build-client
```

Команда создаст компактный `client.xml`. Полный серверный словарь в каждую
страницу не встраивается.

Справку модуля также можно перевести. Английская копия текущего файла должна
лежать в `help/en/modules/localization.md`; остальные языки используют такую же
структуру каталога.

Подробный технический контракт находится в
`docs/development/adminx-localization.md`.
