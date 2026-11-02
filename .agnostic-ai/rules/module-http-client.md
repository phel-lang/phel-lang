---
name: module-http-client
description: 'Load when editing src/php/HttpClient: outbound HTTP for `phel.http-client` over PHP stream contexts.'
scope: src/php/HttpClient
---

# HttpClient Module

Outbound HTTP for `phel.http-client` via PHP's built-in stream context (no cURL/Guzzle). No Gacela: stateless static utilities called from Phel interop by FQCN.

## Public API

| Class::method | Behavior |
|---|---|
| `StreamTransport::send(method, url, headers, ?body, options): array` | HTTP request via stream context. Returns `{status, headers, body, version, reason}`. Throws `InvalidArgumentException` for a URL or redirect target that is not http or https; `RuntimeException` on transport failure, a body over `max_body_bytes` or more than 20 redirects. |
| `ResponseParser::parse(rawHeaders): array` | Parses PHP `$http_response_header` into `{status, version, reason, headers}`. Internal helper of `StreamTransport`; not called from Phel. |

### `send` options (all optional)

| Key | Default | Notes |
|---|---|---|
| `timeout` | `30.0` | Coerced via `ScalarCoercion::toFloat` (numeric strings accepted) |
| `follow_redirects` | `true` | Followed by `send`, not the stream wrapper: a redirect to another origin keeps only `accept`, `accept-encoding`, `accept-language`, `user-agent` and `content-type`; a 303, or a 301/302 after a POST, continues as a GET without the body |
| `verify_ssl` | `true` | Sets `verify_peer` + `verify_peer_name` |
| `max_body_bytes` | `null` (unlimited) | Native non-negative integer or null; rejects an oversized body before buffering it. Applies to every response, redirects included. Phel option: `:max-body-bytes`. |

## Dependencies

- `Phel\Shared\ScalarCoercion` (pure utility): option coercion in `StreamTransport`. No module facades.

## Structure

- `StreamTransport.php`: public interop entry; builds the stream context, opens and reads the response stream with an optional body limit.
- `ResponseParser.php`: header parsing.

## Key Constraints

- **Do not rename either class**: `\Phel\HttpClient\StreamTransport` is referenced by FQCN in `src/phel/http-client.phel`; renaming breaks the public interop surface.
- **Cannot move to `Shared`**: `Shared` is for I/O-free utilities; `StreamTransport` does network I/O. (Decision #2261: no Facade, kept as-is, zero internal PHP callers.)
- Both classes are `final` with stable static public APIs (addressable from user Phel).
- `send` sets `ignore_errors => true` so non-2xx responses return a body instead of throwing; only transport failures throw.
- `ResponseParser` lowercases header names and keeps the **last** value for duplicates (e.g. `Set-Cookie`): a flat `string => string` map; callers needing all values read raw lines.
- `ResponseParser` resets status/version/reason on each new `HTTP/...` status line to handle redirect chains.
