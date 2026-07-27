# Tokens and API Security

← [To the “HTTP API” section](README.md)

## Creating a token

1. In the control panel, open **Documents → JSON API**.
2. Click create token.
3. Provide a friendly name for the integration.
4. Select the minimum required scopes.
5. If necessary, set the expiration date.
6. Copy the secret immediately: it will not be shown again.

To manage tokens, the `manage_document_api` right is required.

## Transfer Bearer

```http
Authorization: Bearer ave_...
Accept: application/json
```

Don't pass the token to a query string, HTML, public JavaScript, or log
requests. The secret is intended for server-to-server integration and is stored in
secrets of the external service environment.

In the database, the system stores only SHA-256 hash and a short prefix. Restore
a lost secret is not allowed: revoke it and create a new one.

## Scopes and user rights

| Scope | Allows |
| --- | --- |
| `documents:read` | GET by ID and alias. |
| `documents:write` | POST/PUT/PATCH. |
| `documents:*` | Read and write. |

Scope does not replace roles. The token owner must remain an active user
and have `view_documents` for reading or `manage_documents` for writing. Role
`admin` and `all_permissions` pass this test.

If a user is disabled, their role is no longer eligible, their token has expired or been revoked,
The API responds with `401 unauthorized`.

## Deadline and review

The token can be perpetual or have a future expiration date. The panel shows
last used with updates no more than once every five minutes. When
compromise, first revoke the key, then check the audit of its actions
owner and create a new secret.

## Limiting requests

Each token can make up to 120 API requests in 60 seconds. If exceeded:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 17
```

The client must wait for the number of seconds from `Retry-After`, then retry
request from backoff. Don't switch between multiple tokens to bypass
limit.
