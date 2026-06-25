# HttpClient Module

Outbound HTTP for `phel.http-client` via PHP's built-in stream context (no cURL/Guzzle). No Gacela: stateless static utilities called directly from Phel interop by FQCN.

## Public API

| Class::method | Behavior |
|---|---|
| `StreamTransport::send(method, url, headers, ?body, options): array` | HTTP request via stream context. Returns `{status, headers, body, version, reason}`. Throws `RuntimeException` on transport failure. |
| `ResponseParser::parse(rawHeaders): array` | Parses PHP `$http_response_header` into `{status, version, reason, headers}`. Internal helper of `StreamTransport`; not called from Phel. |

### `send` options (all keys optional)

| Key | Default | Notes |
|---|---|---|
| `timeout` | `30.0` | Coerced via `ScalarCoercion::toFloat` (numeric strings accepted) |
| `follow_redirects` | `true` | Sets `follow_location` |
| `verify_ssl` | `true` | Sets `verify_peer` + `verify_peer_name` |

## Dependencies

- `Phel\Shared\ScalarCoercion` (pure utility) — option coercion in `StreamTransport`. No module facades.

## Structure

- `StreamTransport.php` — public interop entry; builds stream context, calls `file_get_contents`.
- `ResponseParser.php` — header parsing.

## Key Constraints

- **Do not rename either class** — `\Phel\HttpClient\StreamTransport` is referenced by FQCN in `src/phel/http-client.phel`. Renaming breaks the public interop surface.
- **Cannot move to `Shared`** — `Shared` is reserved for I/O-free utilities; `StreamTransport` does network I/O. (Public-surface decision #2261: no Facade, kept as-is — zero internal PHP callers.)
- Both classes `final` with stable static public APIs (addressable from user Phel).
- `send` sets `ignore_errors => true` so non-2xx responses return a body (and parse) instead of throwing; only true transport failures throw.
- `ResponseParser` lowercases header names and keeps the **last** value for duplicates (e.g. `Set-Cookie`) — flat `string => string` map; callers needing all values must read raw lines.
- `ResponseParser` resets status/version/reason on each new `HTTP/...` status line to handle redirect chains.
