---
name: module-shared-printer
description: 'Load when editing src/php/Shared/Printer: converting Phel/PHP values to strings.'
scope: src/php/Shared/Printer
---

# Printer

Converts Phel/PHP values into strings. Stateless strategy pattern, no I/O, no Gacela boundary; consumers instantiate via factories.

## Public API (`Printer`, `final readonly`)

| Member | Purpose |
|--------|---------|
| `Printer::readable()` | reader literals where supported, opaque markers for host values |
| `Printer::readableWithColor()` | readable + ANSI color |
| `Printer::nonReadable()` | human-facing output (e.g. `str`) |
| `print(mixed $form): string` | instance method; dispatches by type |

## Structure

- `TypePrinter/`: 27 strategy classes, each implements `TypePrinterInterface<T>`. Dispatch in `Printer::createObjectTypePrinter()` (`instanceof` match) and `createScalarTypePrinter()` (`gettype()` match).
- `PrinterInterface`: contract for `Printer`.
- `TypePrinter/WithColorTrait`: `__construct(private bool $withColor = false)`; mixed into color-aware printers. `TypePrinter/ColorizeTrait`: `colorize($str, $code)` wraps in an ANSI escape when `$withColor`; each printer keeps its own `COLOR` code.

## Dependencies

- `Phel\Lang` types only (`Keyword`, `Symbol`, `PhelVar`, `BigDecimal`, `UUID`, `Ratio`, persistent collections, …).

## Non-obvious renderings

- `BigDecimalPrinter` appends `M` for reader round-trip; `UUIDPrinter` → `#uuid "..."`; `VarPrinter` → `#'ns/name`; `PersistentQueuePrinter` → `<-(...)-<` to show FIFO direction.
- Plain-object fallback order: `__toString` → `ToStringPrinter`; anonymous class → `AnonymousClassPrinter`; else `NonPrintableClassPrinter` → `#<Fully\Qualified\Class>`. Other scalar fallbacks use `ObjectPrinter` only in non-readable mode.
- Live PHP resources use `ResourcePrinter` in both modes, nested values included, and render the opaque `#<PHP Resource id #N>` marker.

## Key Constraints

- Recursive printers (collections, struct, atom, array) receive `$this` Printer and re-enter dispatch for children; never construct a fresh `Printer`.
- `createScalarTypePrinter` throws `RuntimeException` for an unprintable type in readable mode; missing object cases fall through `match` to `NonPrintableClassPrinter`.
- Each printer owns its color output (gated by `$withColor`); there is no central color pass.
- `NonPrintableClassPrinter` renders one whitespace-free token on purpose: `ExceptionArgsPrinter` joins a stack-trace frame's arguments with spaces, so a multi-word rendering reads as several arguments (#3265).
- Extend only by adding a `TypePrinter` strategy + a branch in `Printer`; `Printer` is `final readonly`, do not subclass.
