# Printer (Phel\Shared\Printer)

Converts Phel data structures and values into readable string representations. Sub-module of `Phel\Shared` so any module Factory may `new Printer::readable()` without crossing a module boundary.

## No Gacela Pattern

Standalone strategy-pattern submodule. No Facade/Factory/Provider — `Printer` class is the entry point. Stateless, no module config, no I/O wiring.

## Public API

**Factory methods** (on `Printer` class):
- `Printer::readable()` — readable output without colors
- `Printer::readableWithColor()` — readable output with ANSI colors
- `Printer::nonReadable()` — machine-oriented output

**Instance method**:
- `print(mixed $form): string` — convert any value to string

## Type Printers (Strategy pattern)

Each implements `TypePrinterInterface`. Selected at runtime based on value type:

| Printer | Handles |
|---------|---------|
| `StringPrinter` | Strings (escape sequences, UTF-8) |
| `NumberPrinter` | Integers, floats |
| `BooleanPrinter` | true/false |
| `NullPrinter` | null |
| `KeywordPrinter` | Keyword objects |
| `SymbolPrinter` | Symbol objects |
| `RationalPrinter` | Rational numbers (`n/d`) |
| `BigDecimalPrinter` | BigDecimal values (appends the `M` suffix so the readable form round-trips through the reader) |
| `UuidPrinter` | UUID values (rendered as `#uuid "..."`) |
| `VariablePrinter` | Variable objects (recursive) |
| `PersistentListPrinter` | Lists (recursive) |
| `PersistentVectorPrinter` | Vectors (recursive) |
| `PersistentMapPrinter` | Maps (recursive) |
| `PersistentHashSetPrinter` | Sets (recursive) |
| `PersistentQueuePrinter` | Queues (recursive, rendered as `<-(...)-<` so the FIFO direction reads left-to-right) |
| `StructPrinter` | Structs (recursive) |
| `LazySeqPrinter` | Lazy sequences (recursive) |
| `ArrayPrinter` | PHP arrays (recursive) |
| `FnPrinter` | Functions |
| `ToStringPrinter` | Objects with `__toString()` |
| `ObjectPrinter` | Generic objects (non-readable) |
| `AnonymousClassPrinter` | Anonymous classes |
| `NonPrintableClassPrinter` | Non-printable objects |
| `ResourcePrinter` | PHP resources |

## Dependencies

- **Lang** — `Keyword`, `Symbol`, `Variable`, `FnInterface`, all collection interfaces

## Structure

```
Printer/
├── Printer.php                 Main class (final readonly)
├── PrinterInterface.php        Core interface
└── TypePrinter/                One class per type
    ├── TypePrinterInterface.php
    ├── WithColorTrait.php      ANSI color support
    └── ...20 printer classes
```

## Key Constraints

- Recursive printers receive `PrinterInterface` via constructor for nested structures
- `WithColorTrait` provides `$withColor` boolean — each printer controls its own color output
- `Printer` is `final readonly` — extend via new `TypePrinter` implementations, not subclassing
