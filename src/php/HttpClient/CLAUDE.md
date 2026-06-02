# HttpClient Module

Outbound HTTP support for `phel.http-client`. No Gacela Facade: pure stateless utility classes consumed directly by Phel core via PHP interop.

## No Gacela Pattern

Stateless, no module config, no registry, no cross-module dependencies. Facade would add ceremony without value.

**Public-surface decision (#2261):** kept as-is. No PHP module consumes these classes; the only caller is user-facing Phel interop (`src/phel/http-client.phel` calls `\Phel\HttpClient\StreamTransport` by FQCN), so a Facade would have zero internal callers and renaming for `Shared` would break the public interop class name. `Shared` is also reserved for I/O-free utilities, and `StreamTransport` performs network I/O — so it cannot move there.

## Public API

**`StreamTransport::send(string $method, string $url, array<string, string> $headers, ?string $body, array<string, mixed> $options): array`**

Performs HTTP request via PHP stream context. Returns `{status: int, headers: array<string, string>, body: string, version: string, reason: string}`. Throws `RuntimeException` on fopen failure.

**`ResponseParser::parse(array<string> $rawHeaders): array`**

Parses PHP `$http_response_header` into `{status: int, version: string, reason: string, headers: array<string, string>}`. Handles redirect chains by resetting on new HTTP status line. Lowercases header names; keeps last value for duplicates.

## Structure

```
HttpClient/
├── ResponseParser.php
└── StreamTransport.php
```

## Key Constraints

- Both classes `final`; stable public APIs (addressable from user Phel via interop).
- `StreamTransport` honors `:timeout` option; throws `RuntimeException` on request failure.
- `ResponseParser` must handle redirect chains (status line resets on new response).
