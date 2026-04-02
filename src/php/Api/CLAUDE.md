# Api Module

Tooling support layer for REPL autocompletion, function introspection, and documentation.

## Gacela Pattern

- **Facade**: `ApiFacade` implements `ApiFacadeInterface`
- **Factory**: `ApiFactory` extends `AbstractFactory<ApiConfig>`
- **Config**: `ApiConfig` — lists all documented namespaces (`phel\core`, `phel\str`, etc.)
- **Provider**: `ApiProvider` — injects `RunFacade` as `FACADE_RUN`

## Public API (Facade)

- `replComplete(string $input): array` — basic REPL autocompletion (candidate strings)
- `replCompleteWithTypes(string $input): array` — extended completion returning `CompletionResultTransfer` with type annotations
- `getPhelFunctions(array $namespaces = []): array` — all public Phel functions as `PhelFunction` transfers

## Dependencies

- **Run** (`RunFacade`) — namespace resolution, directory listing, file evaluation
- **Compiler** (`GlobalEnvironmentSingleton`) — alias resolution, current namespace, referred symbols
- **Lang** (`Phel`, `Keyword`, `Symbol`, `FnInterface`) — runtime type introspection

## Structure

```
Api/
├── Application/        ReplCompleter, PhelFnNormalizer, PhelFnGroupKeyGenerator
├── Domain/             Interfaces (ReplCompleterInterface, PhelFnNormalizerInterface, etc.)
├── Infrastructure/     PhelFnLoader (loads functions, native symbol docs)
├── Transfer/           PhelFunction, CompletionResultTransfer
└── Gacela files        ApiFacade, ApiFactory, ApiConfig, ApiProvider
```

## Key Constraints

- `ReplCompleter` lazy-loads Phel functions and caches PHP functions/classes
- Supports dual-context completion: PHP symbols (when input starts with `php/`) and Phel symbols
- `PhelFnLoader` provides hard-coded docs for ~40 native symbols/special forms
- `PhelFnNormalizer` filters private functions and removes duplicates
