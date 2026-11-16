---
name: module-http-client
description: 'Load when editing src/php/HttpClient: outbound HTTP for `phel.http-client` over PHP stream contexts.'
scope: src/php/HttpClient
---

# HttpClient Module

Outbound HTTP for `phel.http-client` via PHP's built-in stream context (no cURL or Guzzle). No Gacela pattern: stateless static utilities called from Phel interop by class name. No module facades.

## Public API

`StreamTransport::send(method, url, headers, ?body, options): array` returns `{status, headers, body, version, reason}`.

- Throws `InvalidArgumentException` for a URL or redirect target that is not http or https, an invalid header name, a CR or LF in a header value, or a bad `max_body_bytes`. Throws `RuntimeException` on transport failure, a body over `max_body_bytes`, or more than 20 redirects.
- Sets `ignore_errors => true`, so a non-2xx response returns its body; only transport failures throw.

| Option | Default | Notes |
|---|---|---|
| `timeout` | `30.0` | `ScalarCoercion::toFloat`, numeric strings accepted |
| `follow_redirects` | `true` | followed by `send`, not the stream wrapper. A cross-origin redirect keeps only `accept`, `accept-encoding`, `accept-language`, `user-agent`, `content-type`. A 303 (except after HEAD), or a 301/302 after a POST, continues as a GET without the body |
| `verify_ssl` | `true` | sets `verify_peer` and `verify_peer_name` |
| `max_body_bytes` | `null` (unlimited) | native non-negative integer or null; rejects an oversized body before buffering it, on every response including redirects. Phel option `:max-body-bytes` |

`ResponseParser::parse()` turns `$http_response_header` into `{status, version, reason, headers}`. It lowercases header names and keeps the **last** value for duplicates (`Set-Cookie`): callers needing all values read raw lines. It resets status, version and reason on each `HTTP/...` line to handle redirect chains.

## Key Constraints

- Do not rename `StreamTransport`: `src/phel/http-client.phel` calls it by class name. Both classes are `final` with static public APIs that user Phel can reach, so keep their signatures stable.
- Cannot move to Shared: Shared is for I/O-free utilities, and `StreamTransport` does network I/O. No facade by decision (#2261).
