# Crypt - string encryption

`App\Helpers\Crypt` - reversible string encryption and minor conversions.

```php
use App\Helpers\Crypt;
```

---

## Methods

### `_crypt($string)` / `_decrypt($string)`
Encrypt/decrypt a string (pair operations).

```php
$token = Crypt::_crypt($payload);
$data  = Crypt::_decrypt($token);   // исходное значение
```

###`crc32($value)`
CRC32 hash of the value (fast checksum, **not** for security).

```php
$key = 'cache_' . Crypt::crc32($sql);
```

###`convert64b32($int)`
Number base conversion (64 ↔ 32) - for compact identifiers.

---

## When to use what

| Problem | Tool |
| --- | --- |
| Reversibly hide value (token, payload) | `Crypt::_crypt()` / `_decrypt()` |
| String hash (comparison, cache key) | `Str::sha256()` / `Crypt::crc32()` |
| **Passwords** | `password_hash()` / `password_verify()` - **not** `Crypt` |
| Signature/comparison of secrets | `Str::equals()` (timing-safe) |

> `_crypt`/`_decrypt` are suitable for service data, but this is not a replacement for a full-fledged
> encryption of secrets at the infrastructure level.
