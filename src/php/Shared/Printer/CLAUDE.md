# Printer

Converts Phel data structures into string representations. Stateless strategy pattern, no I/O wiring, no module boundary; consumers instantiate via factory methods. Depends only on Lang types.

## Public API

- Factories: `Printer::readable()`, `Printer::readableWithColor()`, `Printer::nonReadable()`, `Printer::installAsTypeStringifier()` (installs as global `TypeStringifier` at startup)
- Instance: `print(mixed $form): string`

## Type Printers

One class per Phel/PHP type in `TypePrinter/` (26 strategies, each implements `TypePrinterInterface`); runtime dispatch by `gettype()` and `instanceof`. Non-obvious renderings:

- `BigDecimalPrinter` appends M suffix for reader round-trip; `UUIDPrinter` renders `#uuid "..."`; `VarPrinter` renders `#'ns/name`; `PersistentQueuePrinter` renders `<-(...)-<` to show FIFO direction
- Fallback chain for plain objects: `ToStringPrinter` (has `__toString`), `ObjectPrinter`, `AnonymousClassPrinter`, `NonPrintableClassPrinter`, `ResourcePrinter`

## Key Constraints

- Recursive printers construct child `PrinterInterface` at call site (reenters `Printer` dispatch)
- `WithColorTrait` provides `$withColor` boolean; each printer controls own color output
- `Printer` is final readonly; extend via new TypePrinter implementations only
