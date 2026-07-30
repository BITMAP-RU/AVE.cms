# MCP integration

The module provides a protected read-only channel between AVE.cms and an MCP
client. It cannot change documents, settings, or files.

## Available now

- inspect a public page by URL or document ID;
- read fields and their actual values;
- inspect the template, block, navigation, and module component chain;
- diagnose missing templates, required fields, and broken references;
- explain why a document is included in or excluded from a saved request;
- retrieve machine-readable contracts and input schemas.

The module includes a separate Streamable HTTP transport. It converts MCP calls
to protected JSON bridge requests without direct database, configuration, or
filesystem access. It supports MCP 2026-07-28 and stateless 2025 clients.

## Setup and permissions

1. Install and enable **MCP integration** under **Modules**.
2. Grant module view or management access under **Roles and permissions**.
3. Open **Modules → MCP integration**.
4. Create a separate connection for one client.
5. Copy the secret token. It is shown only once.

Only a SHA-256 token hash is stored. A connection can be revoked immediately.
Uninstalling the module removes its connection tokens.

Both token scopes and user permissions are required:

- scopes `content.read` and `structure.read`;
- user permissions `view_documents` and `view_public_site`;
- the module must be installed and enabled.

## Starting the MCP endpoint

The transport requires Node.js 20 or newer. The module page shows a ready-to-use
command:

```bash
AVE_MCP_SITE_URL=https://example.org \
node modules/mcp/transport/dist/server.mjs
```

By default it listens on `127.0.0.1:3090` and exposes:

```text
http://127.0.0.1:3090/mcp
```

Publish remote access through an HTTPS reverse proxy instead of exposing the
Node.js port directly. Add the public hostname to the allowlists:

```bash
AVE_MCP_SITE_URL=https://example.org \
AVE_MCP_ALLOWED_HOSTS=mcp.example.org \
AVE_MCP_ALLOWED_ORIGINS=mcp.example.org \
node modules/mcp/transport/dist/server.mjs
```

After a connection is created, the panel shows client JSON containing the URL
and `Authorization` header. AVE.cms validates the token and its owner on every
bridge call.

## Tools and resource

The first version intentionally exposes a small surface:

- `diagnose.page` explains which documents, fields, templates, blocks,
  navigation entries, and modules compose a public page;
- `requests.explain` explains why a document matches or does not match a saved
  request;
- `ave://introspection/contracts` describes the available read-only contracts.

Both tools are declared read-only, non-destructive, and idempotent. Save,
publish, SQL, PHP, shell, and filesystem operations are not registered.

## Routes

All responses are marked `read-only` and contain a `request_id`.

```text
GET /api/internal/v1/introspection/contracts
GET /api/internal/v1/introspection/page?target=/news/example
GET /api/internal/v1/introspection/request?request_id=2&document_id=42
```

Public page parameters can be provided as a query string or a JSON object.

```bash
curl -H "Authorization: Bearer <token>" \
  "https://example.org/api/internal/v1/introspection/page?target=/news/example"
```

An external trace identifier can be provided through `X-Request-ID`.

## Security limits

- `GET` only;
- 60 requests per minute per connection;
- 1 MB maximum response;
- up to 50 page parameters and 10 KB input;
- a browser-supplied `Origin` must match the site;
- sensitive fields are redacted before the response is built;
- template source, SQL, stack traces, and absolute paths are not returned;
- PHP, arbitrary SQL, shell, and filesystem tools are not exposed.

## Audit

Actual tool calls are visible under **System → Events**. The audit entry stores
the connection, tool, outcome, duration, and request ID. It does not store the
token, document contents, or the full inspected URL.
