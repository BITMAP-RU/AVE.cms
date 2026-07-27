# File — файлы

`App\Helpers\File` — операции с файлами: чтение/запись (в т.ч. атомарная), mime,
скачивание, кодировки, работа с именами.

```php
use App\Helpers\File;
```

---

## Чтение и запись

### `getContent($filename)` — прочитать файл в строку.
### `setContent($filename, $content, $create_file = true, $append = false, $chmod = 0666)`
Записать в файл (с созданием / дозаписью).
### `putAtomic($path, $content, $mode = 0664)`
**Атомарная** запись: пишет во временный файл и делает `rename` — читатели никогда
не увидят «полу-записанный» файл. Используйте для кеша/конфигов.
```php
File::putAtomic($cachePath, Json::payload($data));
$raw = File::getContent($path);
```

### `curlGetContent($url)` / `grab($url, $saveto, $dir)`
Скачать содержимое по URL / сохранить файл по URL в каталог.

---

## Имена и пути

| Метод | Пример |
| --- | --- |
| `ext($filename)` | `'photo.JPG'` → `'jpg'` |
| `stripExt($filename)` / `name($filename)` | имя без расширения / имя файла |
| `slugName($name)` | безопасное имя (slug) |
| `uniqueName($dir, $base, $ext)` | не занятое имя в каталоге |
| `pathCorrection($path)` | нормализовать путь |
| `cutRootPath($path)` / `cutRootPathAll($string)` | убрать корневой путь для вывода |

```php
$ext  = File::ext($upload['name']);              // 'pdf'
$name = File::uniqueName($dir, File::slugName($base), $ext);
```

---

## Операции

```php
File::exists($path);
File::copy($from, $to);
File::rename($from, $to);
File::delete($path);
File::writable($path);
File::lastChange($path);   // mtime
File::scan($folder, $type);   // список файлов (с фильтром по типу)
```

---

## Отдача пользователю

### `download($file, $content_type = null, $filename = null, $kbps = 0)`
Отдать на скачивание; `$kbps > 0` — троттлинг скорости.
### `display($file, $content_type = null, $filename = null)`
Показать inline (изображение/PDF в браузере).
```php
File::download($path, null, 'Отчёт.xlsx');
```

---

## MIME и кодировки

### `mime($file, $guess = true)` — MIME-тип файла.
### `fileEncoding($path, $to = 'utf')` — определить/привести кодировку.

---

## Рецепты

**Безопасная запись кеша:**
```php
File::putAtomic($cacheFile, $payload);   // без «рваных» файлов при гонках
```

**Приём загрузки с уникальным именем:**
```php
$ext  = File::ext($_FILES['f']['name']);
$dest = $dir . '/' . File::uniqueName($dir, File::slugName($base), $ext);
move_uploaded_file($_FILES['f']['tmp_name'], $dest);
```

> Каталоги — в [Dir](Dir.md), архивы — в [Zip](Zip.md).
