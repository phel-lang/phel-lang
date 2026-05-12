# HttpClient Module

Outbound HTTP support for `phel\http-client`. Two standalone classes consumed directly from the Phel core library; no Gacela Facade because there is no module state, no config, no cross-module collaboration.

## Public surface

| Class | Purpose |
|-------|---------|
| `StreamTransport` | Performs the actual `fopen`/`stream_context_create` request, returns headers + body. Used by `phel\http-client/{get,post,put,patch,delete,head}` via `(php/new \Phel\HttpClient\StreamTransport ...)`. |
| `ResponseParser` | Parses PHP's `$http_response_header` array into `{status, headers}`. Pure utility. |

## Why no Facade

- Stateless: no module config, no registry, no I/O wiring.
- No cross-module deps: imports only PHP std lib.
- Phel core (`src/phel/http-client.phel`) is the sole consumer and instantiates the classes directly via PHP interop, which is the intended API.

If a future feature needs `phel-config.php` knobs (proxies, default headers, TLS roots), promote to a Gacela facade then. Until then a Facade would be ceremony without value.

## Structure

```
HttpClient/
├── ResponseParser.php
└── StreamTransport.php
```

## Key Constraints

- Both classes must remain `final` (Phel core does not subclass them) and have stable public APIs : they are addressable from user Phel code through interop.
- Never block on a single host; honour the `:timeout` option Phel passes through.
- `StreamTransport` must surface fopen failures as `RuntimeException`; Phel core wraps them into `phel\http-client/error?` results.
