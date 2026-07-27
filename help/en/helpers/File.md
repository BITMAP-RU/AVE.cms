# File - files

`App\Helpers\File` - file operations: read/write (including atomic), mime,
downloading, encodings, working with names.

```php
use App\Helpers\File;
```

---

## Read and write

### `getContent($filename)` - read the file into a string.
###`setContent($filename, $content, $create_file = true, $append = false, $chmod = 0666)`
Write to a file (with creation/addition).
###`putAtomic($path, $content, $mode = 0664)`
**Atomic** write: writes to a temporary file and makes `rename` - readers never
will not see the “half-written” file. Use for cache/configs.

```php
File::putAtomic($cachePath, Json::payload($data));
$raw = File::getContent($path);
```

### `curlGetContent($url)` / `grab($url, $saveto, $dir)`
Download content by URL / save file by URL to a directory.

---

## Names and paths

| Method | Example |
| --- | --- |
| `ext($filename)` | `'photo.JPG'` → `'jpg'` |
| `stripExt($filename)` / `name($filename)` | name without extension / file name |
| `slugName($name)` | safename (slug) |
| `uniqueName($dir, $base, $ext)` | unused directory name |
| `pathCorrection($path)` | normalize path |
| `cutRootPath($path)` / `cutRootPathAll($string)` | remove root path for output |

```php
$ext  = File::ext($upload['name']);              // 'pdf'
$name = File::uniqueName($dir, File::slugName($base), $ext);
```

---

## Operations

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

## Return to user

###`download($file, $content_type = null, $filename = null, $kbps = 0)`
Submit for download; `$kbps > 0` - speed throttling.
###`display($file, $content_type = null, $filename = null)`
Show inline (image/PDF in browser).

```php
File::download($path, null, 'Отчёт.xlsx');
```

---

## MIME and encodings

### `mime($file, $guess = true)` — MIME file type.
### `fileEncoding($path, $to = 'utf')` — determine/bring the encoding.

---

## Recipes

**Secure Cache Write:**

```php
File::putAtomic($cacheFile, $payload);   // без «рваных» файлов при гонках
```

**Accepting downloads with a unique name:**

```php
$ext  = File::ext($_FILES['f']['name']);
$dest = $dir . '/' . File::uniqueName($dir, File::slugName($base), $ext);
move_uploaded_file($_FILES['f']['tmp_name'], $dest);
```

> Directories - in [Dir](Dir.md), archives - in [Zip](Zip.md).
