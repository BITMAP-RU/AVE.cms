# Antispam

← [Back to the “Modules” section](README.md)

The `antispam` module protects public forms with profiles. It does not have an insert
tag: security fields are requested by the form owner via hooks
`public.form.protection.render` and `public.form.protection.verify`.

After installation, profiles `registration`, `login`, `password_reset`,
`contacts`, `comments` and `polls`. Their codes are a contract between the form and
antispam; the name can be changed without editing the public code.

## Validation methods

| Method | What checks |
| --- | --- |
| Frequency Limit | The number of times one network ID was sent per time window. |
| Fill Time | The form was not submitted before the minimum and after the challenge deadline. |
| Hidden field | A random honeypot field is left empty. |
| CAPTCHA | The code for the built-in generator AVE.cms has been entered. |

Each challenge is one-time, associated with a profile and the current session. After
check it is marked used, so resending the same data
it won't work.

## Embedding in your form

Before displaying the form:

```php
$protection = Hooks::filter('public.form.protection.render', array(
	'profile' => 'contacts',
	'html' => '',
	'handled' => false,
	'context' => array('form_code' => 'feedback'),
));

$html .= !empty($protection['handled']) ? $protection['html'] : '';
```

`html` contains hidden `_antispam_token`, random honeypot and CAPTCHA if
they are included in the profile. This HTML must be inside the form being submitted.

Before saving data:

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

`reason` is for a clear response form, `method` contains the source
locks: `rate_limit`, `challenge`, `timing`, `honeypot`, `captcha`,
`provider` or `runtime`.

##Challenge API

```text
GET /api/v1/antispam/challenge/contacts
```

The response field `data` contains:

| Key | Meaning |
| --- | --- |
| `profile` | Code of the applied profile. |
| `token` | One-time token; usually already present in `html`. |
| `honeypot` | The name of the hidden field or an empty string. |
| `captcha` | Is CAPTCHA required? |
| `captcha_url` | CAPTCHA image URL. |
| `html` | Ready fields for insertion into the form. |

Endpoint is limited to 60 challenges per minute per IP. API issues
fields, but the final validation is still done by the form owner hook.

## Extension by external provider

Through `antispam.methods` you can add the name of the method to the panel. Real
the check is performed by `antispam.verifying`: the handler receives the profile, input,
challenge, `allowed` and `reason`. To reject the submission, return
`allowed = false` and fill in `reason`.

`antispam.verified` is called after success, `antispam.blocked` is called after failure.
In both cases, the profile, method and reason are available. The HMAC secret is stored in
protected storage of the module and is deleted when uninstalled along with the tables.
