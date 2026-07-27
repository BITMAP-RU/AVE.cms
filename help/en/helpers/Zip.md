# Zip - archives

`App\Helpers\Zip` - a wrapper around `ZipArchive` for creating and unpacking archives.
Used as **instance** (`new Zip()`).

```php
use App\Helpers\Zip;

$zip = new Zip();
```

---

## Usage

The class encapsulates working with `ZipArchive`: creating an archive, adding files and
directories, saving and unpacking. For the exact set of methods and their signatures, see
`system/App/Helpers/Zip.php` (API repeats the logic of `ZipArchive`).

Typical scenario:

```php
$zip = new Zip();
// открыть/создать архив, добавить файлы каталога, закрыть — по методам класса
// затем отдать пользователю:
File::download(BASEPATH . '/exports/backup.zip', null, 'backup.zip');
```

---

## Recipe

**Submit the generated archive for download:**

```php
// ... сборка архива через $zip ...
Response::download($archivePath, 'export.zip');
```

> To upload the finished file, use [File::download()](File.md) or
> [Response::download()](Response.md). Directories - [Dir](Dir.md).
