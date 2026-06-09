# HttpClient Module

Outbound HTTP support for `phel.http-client`. No Gacela pattern: stateless utility classes consumed directly by Phel core via PHP interop.

**Public-surface decision (#2261):** kept as-is. No PHP module consumes these classes; the only caller is user-facing Phel interop (`src/phel/http-client.phel` calls `\Phel\HttpClient\StreamTransport` by FQCN), so a Facade would have zero internal callers and renaming for `Shared` would break the public interop class name. `Shared` is also reserved for I/O-free utilities, and `StreamTransport` performs network I/O, so it cannot move there.

## Public API

- `StreamTransport::send(method, url, headers, ?body, options): array`: HTTP request via PHP stream context. Returns `{status, headers, body, version, reason}`. Honors `:timeout` option; throws `RuntimeException` on failure.
- `ResponseParser::parse(array $rawHeaders): array`: parses PHP `$http_response_header` into `{status, version, reason, headers}`. Lowercases header names; keeps last value for duplicates.

## Key Constraints

- Both classes `final`; stable public APIs (addressable from user Phel via interop)
- `ResponseParser` must handle redirect chains (status line resets on new response)
