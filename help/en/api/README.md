# HTTP API

The system provides versioned JSON API documents for server integrations.
Through it you can get a document by ID or alias, create a new one and update it
existing. The entry goes through the same fields, validation, category code, hooks,
revisions, terms and JSON snapshot, as in the control panel.

## Routes v1

| Method and URL | Scope | Destination |
| --- | --- | --- |
| `GET /api/v1/documents/{id}` | `documents:read` | Receive a document by ID. |
| `GET /api/v1/documents/by-alias?alias=...` | `documents:read` | Get the full alias. |
| `POST /api/v1/documents` | `documents:write` | Create a document. |
| `PUT /api/v1/documents/{id}` | `documents:write` | Update document. |
| `PATCH /api/v1/documents/{id}` | `documents:write` | Partially update the document. |

## API of installed modules

Managed modules can add their own routes and access rules. After
installation of the module **Site Search** public endpoint available:

| Method and URL | Access | Destination |
| --- | --- | --- |
| `GET /api/v1/search?q=...` | Public, rate limit | Search with custom field projection and formats `json`, `html`, `both`. |

The composition of the search response is set in the module settings and is not related to the scopes API.
document token. Full setup, parameters and examples are given in
[search instructions](../modules/search.md).

In v1 there is no listing and deletion of documents. Don't go through IDs to download everything
content: such integration requires a separate limited endpoint of the module.

## Section map

| Page | Destination |
| --- | --- |
| [Tokens and Security](authentication.md) | Issue, scopes, Bearer, term, revocation, rights and rate limit. |
| [Documents](documents.md) | Reading format, full payload records, curl fields and examples. |
| [Errors](errors.md) | HTTP statuses, JSON contract and retry strategy. |

All answers have `Content-Type: application/json; charset=UTF-8`. The client must
check both the HTTP status and `success`, rather than searching for the message text.
