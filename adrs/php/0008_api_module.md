# Api Module

## Motivation

Describe the responsibilities of the Api module.

## Decision

This module is the place for the `doc` command.

Additionally, the `ApiFacade` allows you to get all public phel functions by namespaces, eg:

- `phel\\core`
- `phel\\http`
- `phel\\html`
- `phel\\test`
- `phel\\json`

```php 
interface ApiFacadeInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array;
}
```

## Consequences

The API module exposes public Phel functions and documentation in a single place, making integration simpler for consumers.
