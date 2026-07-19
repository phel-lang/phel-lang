# Printer

Converts Phel/PHP values into string representations. Stateless strategy pattern, no I/O, no Gacela boundary — consumers instantiate via factories.

## Public API (`Printer`, `final readonly`)

| Member | Purpose |
|--------|---------|
| `Printer::readable()` | reader round-trippable output |
| `Printer::readableWithColor()` | readable + ANSI color |
| `Printer::nonReadable()` | human-facing output (e.g. `str`) |
| `print(mixed $form): string` | instance method; dispatches by type |

## Structure

- `TypePrinter/` — 27 strategy classes, each implements `TypePrinterInterface<T>`. Dispatch in `Printer::createObjectTypePrinter()` (`instanceof` match) and `createScalarTypePrinter()` (`gettype()` match).
- `PrinterInterface` — contract for `Printer`.
- `WithColorTrait` — `__construct(private bool $withColor)` + `color()` helper; mixed into color-aware printers.

## Dependencies

- `Phel\Lang` types only (`Keyword`, `Symbol`, `PhelVar`, `BigDecimal`, `UUID`, `Ratio`, persistent collections, …).

## Non-obvious renderings

- `BigDecimalPrinter` appends `M` for reader round-trip; `UUIDPrinter` → `#uuid "..."`; `VarPrinter` → `#'ns/name`; `PersistentQueuePrinter` → `<-(...)-<` to show FIFO direction.
- Plain-object fallback order: `__toString` → `ToStringPrinter`; anonymous class → `AnonymousClassPrinter`; else `NonPrintableClassPrinter`. Scalars in non-readable mode fall to `ObjectPrinter`/`ResourcePrinter`.

## Key Constraints

- Recursive printers (collections, struct, atom, array) receive `$this` Printer and re-enter dispatch for children — never construct a fresh `Printer`.
- `createScalarTypePrinter` throws `RuntimeException` for an unprintable type in readable mode; missing object cases fall through `match` to `NonPrintableClassPrinter`.
- Each printer owns its own color output (gated by `$withColor`); there is no central color pass.
- Extend only by adding a `TypePrinter` strategy + a branch in `Printer`; `Printer` is `final readonly` — do not subclass.
