---
name: module-shared-printer
description: 'Load when editing src/php/Shared/Printer: converting Phel/PHP values to strings.'
scope: src/php/Shared/Printer
---

# Printer

Part of the Shared module. Converts Phel/PHP values to strings: one `TypePrinter/` strategy per type, no I/O. `Printer::readable()` gives reader literals where supported, `nonReadable()` human output (`str`), `readableWithColor()` adds ANSI.

## Key Constraints

- Dispatch lives in `Printer::createObjectTypePrinter()` (`instanceof` match) and `createScalarTypePrinter()` (`gettype()` match). Extend only by adding a strategy plus a branch there; `Printer` is `final readonly`.
- Recursive printers (collections, struct, atom, array) receive `$this` and re-enter dispatch for children; never construct a fresh `Printer`. The instance tracks atoms being printed (`WeakMap`) and prints a self-containing atom as `#<cycle>`; a fresh printer loses that guard.
- Object fallback order: `__toString` -> `ToStringPrinter`; anonymous class -> `AnonymousClassPrinter`; else `NonPrintableClassPrinter` (`#<Fully\Qualified\Class>`). An unknown scalar type uses `ObjectPrinter` in non-readable mode and throws `RuntimeException` in readable mode. Resources print `#<PHP Resource id #N>` in both modes.
- `NonPrintableClassPrinter` renders one whitespace-free token on purpose: Command's `ExceptionArgsPrinter` joins stack-frame arguments with spaces, so a multi-word rendering reads as several arguments (#3265).
- Each printer owns its color (`WithColorTrait`, `ColorizeTrait`, own `COLOR` code); there is no central color pass.
- Readable forms must round-trip through the reader: `BigDecimalPrinter` appends `M`, `UUIDPrinter` prints `#uuid "..."`, `VarPrinter` `#'ns/name`. `PersistentQueuePrinter` prints `<-(...)-<` to show FIFO direction.
