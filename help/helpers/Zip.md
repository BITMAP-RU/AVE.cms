# Zip — архивы

`App\Helpers\Zip` — обёртка над `ZipArchive` для создания и распаковки архивов.
Используется как **инстанс** (`new Zip()`).

```php
use App\Helpers\Zip;

$zip = new Zip();
```

---

## Использование

Класс инкапсулирует работу с `ZipArchive`: создание архива, добавление файлов и
каталогов, сохранение и распаковку. Точный набор методов и их сигнатуры смотрите в
`system/App/Helpers/Zip.php` (API повторяет логику `ZipArchive`).

Типичный сценарий:

```php
$zip = new Zip();
// открыть/создать архив, добавить файлы каталога, закрыть — по методам класса
// затем отдать пользователю:
File::download(BASEPATH . '/exports/backup.zip', null, 'backup.zip');
```

---

## Рецепт

**Отдать сгенерированный архив на скачивание:**
```php
// ... сборка архива через $zip ...
Response::download($archivePath, 'export.zip');
```

> Для отдачи готового файла используйте [File::download()](File.md) или
> [Response::download()](Response.md). Каталоги — [Dir](Dir.md).
