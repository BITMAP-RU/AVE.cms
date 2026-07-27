#Phone

← [To helpers](README.md)

`App\Helpers\Phone` converts user input to a single telephone number
format. The helper does not send SMS and does not work with users.

```php
use App\Helpers\Phone;
```

| Method | Result |
| --- | --- |
| `normalize($value)` | International number with `+` or an empty string. |
| `valid($value)` | Does the number pass basic E.164 verification? |
| `digits($value)` | Only digits of the normalized number. |
| `mask($value)` | Safe value for interface and audit. |

```php
$phone = Phone::normalize('8 (900) 123-45-67');
// +79001234567

$masked = Phone::mask($phone);
// +7******4567
```

Russian 10-digit numbers receive the code `+7`; leading `8` is replaced by
`+7`. The remaining numbers must comply with the international format: from 10 to
15 digits, the first digit is not zero.
