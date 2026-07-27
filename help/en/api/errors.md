# HTTP API errors

← [To the “HTTP API” section](README.md)

Single error format:

```json
{
  "success": false,
  "error": {
    "code": "validation_failed",
    "message": "Проверьте данные документа",
    "fields": {
      "title": "Укажите название документа"
    }
  }
}
```

`fields` is present in validation errors and contains the machine path of the field → text.
Do not tie client logic to Russian `message`; use `code` and
HTTP status.

| HTTP | `error.code` | Reason |
| --- | --- | --- |
| 400 | `invalid_json` | The body is not a valid JSON object. |
| 401 | `unauthorized` | No Bearer, token/scope/role is invalid. |
| 404 | `not_found` | The document was not found or deleted. |
| 413 | `payload_too_large` | The body is larger than 2 MB or has not been read. |
| 415 | `unsupported_media_type` | `application/json` is not specified for the entry. |
| 422 | `validation_failed` | Error in document, field, hook or category code. |
| 429 | `rate_limited` | Exceeded 120 requests in 60 seconds. |
| 500 | `save_failed` | Unexpected save error. |

## Repeat request

- `400`, `401`, `404`, `413`, `415`, `422` do not repeat without correcting the request
  or access.
- When `429`, observe `Retry-After` and exponential backoff.
- With `500`, limited replay is allowed only for an idempotent operation.

Creation via POST does not have an external idempotency-key in v1. After the network
break, first try to find a document using a predefined unique alias,
rather than POST blindly a second time. For critical mass integration
use the stable `guid` and your own synchronization module.

## Client logging

Record the method, URL without token, HTTP status, `error.code`, request ID of the external
systems and duration. Do not log the `Authorization` header and full body,
if it contains personal data.
