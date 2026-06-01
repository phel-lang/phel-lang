# Printer

Converts Phel data structures into readable string representations. Stateless strategy pattern, no I/O wiring, no module boundary; consumers instantiate via factory methods.

## No Gacela Pattern

Entry point: `Printer` class. Stateless, no config, no dependencies from other modules.

## Public API

**Printer class factory methods**:
- `Printer::readable()`: readable text, no ANSI colors
- `Printer::readableWithColor()`: readable text with ANSI colors
- `Printer::nonReadable()`: machine-oriented output
- `Printer::installAsTypeStringifier()`: install as global `TypeStringifier` (used at startup)

**Instance method**:
- `print(mixed $form): string`: convert any value to string

## Type Printers (26 strategies)

Each implements `TypePrinterInterface` and handles one type. Runtime dispatch based on `gettype()` and `instanceof`. Recursive printers receive `PrinterInterface` via constructor.

| Printer | Handles |
|---------|---------|
| `StringPrinter` | Strings; handles escape sequences and UTF-8 |
| `NumberPrinter` | Integers, floats |
| `BooleanPrinter` | true, false |
| `NullPrinter` | null |
| `KeywordPrinter` | Keyword objects |
| `SymbolPrinter` | Symbol objects |
| `RatioPrinter` | Ratio numbers (n/d) |
| `BigDecimalPrinter` | BigDecimal; appends M suffix for reader round-trip |
| `UUIDPrinter` | UUID objects; rendered as #uuid "..." |
| `AtomPrinter` | Atom objects; recursive |
| `VarPrinter` | PhelVar handles; rendered as #'ns/name |
| `ConsPrinter` | Cons cells (LazySeq head); recursive |
| `PersistentListPrinter` | Lists; recursive |
| `PersistentVectorPrinter` | Vectors; recursive |
| `PersistentMapPrinter` | Maps; recursive |
| `PersistentHashSetPrinter` | Sets; recursive |
| `PersistentQueuePrinter` | Queues; recursive; rendered as <-(...)-< to show FIFO direction |
| `StructPrinter` | Structs; recursive |
| `LazySeqPrinter` | Lazy sequences; recursive |
| `ArrayPrinter` | PHP arrays; recursive |
| `FnPrinter` | Functions; non-readable |
| `ToStringPrinter` | Objects with __toString() |
| `ObjectPrinter` | Generic objects; non-readable |
| `AnonymousClassPrinter` | Anonymous classes |
| `NonPrintableClassPrinter` | Non-printable objects |
| `ResourcePrinter` | PHP resources |

## Dependencies

Lang module: Keyword, Symbol, Atom, FnInterface, PhelVar, Ratio, BigDecimal, UUID, all collection interfaces (PersistentListInterface, PersistentVectorInterface, etc.).

## Structure

```
Printer/
├── Printer.php
├── PrinterInterface.php
└── TypePrinter/
    ├── TypePrinterInterface.php
    ├── WithColorTrait.php (constructor: $withColor bool)
    └── 26 printer classes
```

## Key Constraints

- Recursive printers construct child `PrinterInterface` at call site (reenters `Printer` dispatch)
- `WithColorTrait` provides $withColor boolean; each printer controls own color output
- `Printer` is final readonly; extend via new TypePrinter implementations only
