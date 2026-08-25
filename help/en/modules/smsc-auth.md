# Login via SMS via SMSC

← [To modules](README.md)

Module `smsc_auth` sends one-time code via SMSC. Code checking,
user creation and public session are performed by the AVE.cms core. Thanks
Therefore, disabling or replacing SMSC does not change users and their orders.

## What does the visitor get?

A form with a phone number appears on the login page. After sending the message she
is replaced by a code field. There are two possible scenarios:

1. The phone already belongs to the active user - his profile opens.
2. There is no phone number yet - a site user is created in the registration group.

Both scenarios use the same user table. Separate “SMS accounts”
no. The phone is stored in an international format, for example `+79001234567`, and cannot
simultaneously belong to two profiles.

## Preparing SMSC

1. Create an account on [smsc.ru](https://smsc.ru/).
2. In your SMSC account, issue a separate API key for the site.
3. If necessary, register the sender's name.
4. Top up your balance and check if sending to the desired destinations is allowed.

The sender name must contain Latin letters and digits and be no longer than 11
characters, or be a numeric sender up to 15 digits. Register it in SMSC and wait
until it is approved. If the field is empty, SMSC uses its default sender.

You do not need to enter your account login and password in AVE.cms. Use a separate
An API key that can be revoked without changing the SMSC owner's password.

## Installation and configuration

1. Open `Modules -> Management`.
2. Install and enable `Login via SMS (SMSC)`.
3. Open `Modules -> Login via SMS`.
4. Paste the API key and, if necessary, the sender's name.
5. Leave the tag `{code}` in the SMS text.
6. Save your settings.
7. In the lower block, send the test code to your number.
8. Enable public login only after a successful test.

The general ability to create users and their group is configured in
`System -> Site Users -> Registration`. Select `By phone` or `By email or phone`
there. Module switch
`Create accounts` may additionally prohibit registration by phone,
leaving existing users logged in.

## Code restrictions

| Setting | Destination |
| --- | --- |
| Code Length | From 4 to 8 digits. For a regular site, 6 is recommended. |
| The code is valid | After this time the code is rejected. |
| Resubmission | Minimum pause before a new SMS in the current session. |
| Input attempts | How many invalid inputs are allowed per challenge. |
| SMS per day | Daily limit for one room. |
| Keep history | Number of days to retain send and login events. |

Additionally, the kernel limits the frequency of requests by IP and number. New code
cancels the previous one, is associated with the current PHP session and can be used
only once. The bcrypt hash of the code is stored in the database, not the code itself.

If account creation is disabled and the number is not found, the public response is still
looks like a successful request, but the SMS is not sent. It doesn't allow iterating
registered phones and does not spend balance on unknown numbers.

## Balance and login history

The `Settings` tab shows the current SMSC balance, connection state,
and the selected sender status. `Refresh SMSC data` requests fresh information;
the result is cached for one minute between checks.

The separate `Login history` tab records successful and failed sends, rate limits, invalid, expired,
or reused codes, successful logins, and account creation. It includes the date,
masked and hashed phone, IP, browser, user, and SMSC message ID. The one-time code
and full phone number are never stored in this history.

Filters search by the phone mask, IP, or SMSC ID and narrow the list by event and
outcome. Old rows are removed automatically according to `Keep history`.

## Secrets and environment variables

Default settings are in private storage
`storage/secrets/modules/smsc_auth.php`. They can be overridden:

```dotenv
SMSC_AUTH_ENABLED=1
SMSC_API_KEY=your-api-key
SMSC_SENDER=AVEcms
```

Do not add the secrets file and working API key to the repository, module archive,
public template or error log.

## Public templates

The system form is in
`system/App/Frontend/Auth/view/phone.twig`. For a custom login template
Array `phone_providers` is available. If the template does not display it, AVE.cms will add
working form after the main login form, so old themes don't break.

Public Ajax addresses:

| Method and address | Destination |
| --- | --- |
| `POST /auth/phone/request` | Normalizes the phone and sends the code. |
| `POST /auth/phone/verify` | Checks the challenge and opens the session. |

Both addresses require a CSRF token. The first one also passes the general protection of public
forms and rate limit.

## Removing a module

When deleted, SMSC settings, incomplete challenges, and login history are erased. Users,
confirmed phone numbers, orders and profiles are saved because they are shared
AVE.cms data. Without an installed SMS provider, the phone login form is simple
not shown.

## Connecting another SMS provider

New transport is being implemented
`App\Frontend\Auth\Phone\ProviderInterface` and registers the instance via:

```php
use App\Frontend\Auth\Phone\ProviderRegistry;

ProviderRegistry::register(new MySmsProvider());
```

The provider is only responsible for setting up and sending `sendCode($phone, $code)`.
It must not independently create a user, store open source code, or
manage the session.
