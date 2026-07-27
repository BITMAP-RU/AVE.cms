# Ip - IP address and geo

`App\Helpers\Ip` - IP determination and validation, geo-data, conversion. Unlike
most helpers, has both instance mode (`new Ip($options)`) and static
methods.

```php
use App\Helpers\Ip;
```

---

## Definition and verification

###`getIp()`
Client IP taking into account proxy headers (`X-Forwarded-For`, etc.).

```php
$ip = Ip::getIp();
```

###`isValid($ip = null)`
Is the IP valid (current by default).

```php
if (Ip::isValid($ip)) { ... }
```

---

## Geo data

### `geoBaseData()` - data from the geo-base.
### `getValue($key = false, $cookie = true)` — geo/IP value by key (with cache in cookie).
### `parseString($string)` — parse a string with IP/geo.

---

## Conversion

### `ip2hex($ip)` / `hex2ip($hex)`
IP ↔ hex representation (compact storage/indexing).

```php
$hex = Ip::ip2hex('192.168.0.1');
$ip  = Ip::hex2ip($hex);
```

---

## Instance mode

```php
$geo = new Ip(['some' => 'options']);
// далее методы экземпляра согласно реализации
```

> For IP specifically **current request** `Request::ip()` is simpler. `Ip` is needed for
> validation, geo and conversions.
