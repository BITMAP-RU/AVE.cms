# Антиспам

← [Назад к разделу «Модули»](README.md)

Модуль `antispam` защищает публичные формы профилями. Он не имеет вставочного
тега: поля защиты запрашивает владелец формы через hooks
`public.form.protection.render` и `public.form.protection.verify`.

После установки создаются профили `registration`, `login`, `password_reset`,
`contacts`, `comments` и `polls`. Их коды являются контрактом между формой и
антиспамом; название можно менять без правки публичного кода.

## Методы проверки

| Метод | Что проверяет |
| --- | --- |
| Ограничение частоты | Число отправок одного сетевого идентификатора за окно времени. |
| Время заполнения | Форма не отправлена раньше минимума и позже срока challenge. |
| Скрытое поле | Случайное honeypot-поле осталось пустым. |
| CAPTCHA | Введён код встроенного генератора AVE.cms. |

Каждый challenge одноразовый, связан с профилем и текущей сессией. После
проверки он помечается использованным, поэтому повторная отправка тех же данных
не пройдёт.

## Встраивание в свою форму

Перед выводом формы:

```php
$protection = Hooks::filter('public.form.protection.render', array(
	'profile' => 'contacts',
	'html' => '',
	'handled' => false,
	'context' => array('form_code' => 'feedback'),
));

$html .= !empty($protection['handled']) ? $protection['html'] : '';
```

`html` содержит скрытый `_antispam_token`, случайный honeypot и CAPTCHA, если
они включены в профиле. Этот HTML должен находиться внутри отправляемой формы.

Перед сохранением данных:

```php
$check = Hooks::filter('public.form.protection.verify', array(
	'profile' => 'contacts',
	'input' => Request::postAll(),
	'context' => array('form_code' => 'feedback'),
	'allowed' => true,
	'handled' => false,
));

if (!empty($check['handled']) && empty($check['allowed'])) {
	throw new RuntimeException($check['reason']);
}
```

`reason` предназначен для понятного ответа формы, `method` содержит источник
блокировки: `rate_limit`, `challenge`, `timing`, `honeypot`, `captcha`,
`provider` или `runtime`.

## Challenge API

```text
GET /api/v1/antispam/challenge/contacts
```

Поле `data` ответа содержит:

| Ключ | Значение |
| --- | --- |
| `profile` | Код применённого профиля. |
| `token` | Одноразовый токен; обычно уже присутствует в `html`. |
| `honeypot` | Имя скрытого поля либо пустая строка. |
| `captcha` | Требуется ли CAPTCHA. |
| `captcha_url` | URL изображения CAPTCHA. |
| `html` | Готовые поля для вставки в форму. |

Endpoint ограничен 60 выдачами challenge в минуту для одного IP. API выдаёт
поля, но окончательная проверка всё равно выполняется hook владельца формы.

## Расширение внешним провайдером

Через `antispam.methods` можно добавить название метода в панель. Реальную
проверку выполняет `antispam.verifying`: обработчик получает профиль, input,
challenge, `allowed` и `reason`. Чтобы отклонить отправку, верните
`allowed = false` и заполните `reason`.

`antispam.verified` вызывается после успеха, `antispam.blocked` — после отказа.
В обоих случаях доступны профиль, метод и причина. Секрет HMAC хранится в
защищённом хранилище модуля и удаляется при uninstall вместе с таблицами.
