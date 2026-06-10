<?php

declare(strict_types=1);

namespace Phel\Shared\Printer;

use Phel\Lang\Atom;
use Phel\Lang\BigDecimal;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LazySeq\Cons;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Queue\PersistentQueue;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Lang\PhelVar;
use Phel\Lang\Ratio;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Lang\TypeStringifier;
use Phel\Lang\UUID;
use Phel\Shared\Printer\TypePrinter\AnonymousClassPrinter;
use Phel\Shared\Printer\TypePrinter\ArrayPrinter;
use Phel\Shared\Printer\TypePrinter\AtomPrinter;
use Phel\Shared\Printer\TypePrinter\BigDecimalPrinter;
use Phel\Shared\Printer\TypePrinter\BooleanPrinter;
use Phel\Shared\Printer\TypePrinter\ConsPrinter;
use Phel\Shared\Printer\TypePrinter\FnPrinter;
use Phel\Shared\Printer\TypePrinter\KeywordPrinter;
use Phel\Shared\Printer\TypePrinter\LazySeqPrinter;
use Phel\Shared\Printer\TypePrinter\NonPrintableClassPrinter;
use Phel\Shared\Printer\TypePrinter\NullPrinter;
use Phel\Shared\Printer\TypePrinter\NumberPrinter;
use Phel\Shared\Printer\TypePrinter\ObjectPrinter;
use Phel\Shared\Printer\TypePrinter\PersistentHashSetPrinter;
use Phel\Shared\Printer\TypePrinter\PersistentListPrinter;
use Phel\Shared\Printer\TypePrinter\PersistentMapPrinter;
use Phel\Shared\Printer\TypePrinter\PersistentQueuePrinter;
use Phel\Shared\Printer\TypePrinter\PersistentVectorPrinter;
use Phel\Shared\Printer\TypePrinter\RatioPrinter;
use Phel\Shared\Printer\TypePrinter\ResourcePrinter;
use Phel\Shared\Printer\TypePrinter\StringPrinter;
use Phel\Shared\Printer\TypePrinter\StructPrinter;
use Phel\Shared\Printer\TypePrinter\SymbolPrinter;
use Phel\Shared\Printer\TypePrinter\ToStringPrinter;
use Phel\Shared\Printer\TypePrinter\TypePrinterInterface;
use Phel\Shared\Printer\TypePrinter\UUIDPrinter;
use Phel\Shared\Printer\TypePrinter\VarPrinter;
use ReflectionClass;
use RuntimeException;

use function gettype;
use function is_object;

final readonly class Printer implements PrinterInterface
{
    public function __construct(
        private bool $readable,
        private bool $withColor = false,
    ) {}

    public static function installAsTypeStringifier(): void
    {
        TypeStringifier::install(
            static fn(TypeInterface $value): string => self::readable()->print($value),
        );
    }

    public static function readable(): self
    {
        return new self(readable: true);
    }

    public static function readableWithColor(): self
    {
        return new self(readable: true, withColor: true);
    }

    public static function nonReadable(): self
    {
        return new self(readable: false);
    }

    /**
     * Converts a form to a printable string.
     */
    public function print(mixed $form): string
    {
        return $this->createTypePrinter($form)->print($form);
    }

    /**
     * @return TypePrinterInterface<mixed>
     */
    private function createTypePrinter(mixed $form): TypePrinterInterface
    {
        if (is_object($form)) {
            return $this->createObjectTypePrinter($form);
        }

        return $this->createScalarTypePrinter($form);
    }

    /**
     * Each branch builds a printer parameterised for the type it just
     * matched, so the dispatch is sound even though generic invariance
     * cannot relate `TypePrinterInterface<Concrete>` to the `<mixed>`
     * return type (an existential type PHPStan cannot express; see the
     * documented ignore in phpstan-strict.neon).
     *
     * @return TypePrinterInterface<mixed>
     */
    private function createObjectTypePrinter(object $form): TypePrinterInterface
    {
        return match (true) {
            $form instanceof AbstractPersistentStruct => new StructPrinter($this),
            $form instanceof PersistentListInterface => new PersistentListPrinter($this),
            $form instanceof PersistentVectorInterface => new PersistentVectorPrinter($this),
            $form instanceof PersistentMapInterface => new PersistentMapPrinter($this),
            $form instanceof PersistentHashSetInterface => new PersistentHashSetPrinter($this),
            $form instanceof Cons => new ConsPrinter($this),
            $form instanceof LazySeqInterface => new LazySeqPrinter($this),
            $form instanceof PersistentQueue => new PersistentQueuePrinter($this),
            $form instanceof Keyword => new KeywordPrinter($this->withColor),
            $form instanceof Symbol => new SymbolPrinter($this->withColor),
            $form instanceof Atom => new AtomPrinter($this),
            $form instanceof PhelVar => new VarPrinter($this->withColor),
            $form instanceof Ratio => new RatioPrinter($this->withColor),
            $form instanceof BigDecimal => new BigDecimalPrinter($this->withColor),
            $form instanceof UUID => new UUIDPrinter($this->withColor),
            $form instanceof FnInterface => new FnPrinter(),
            method_exists($form, '__toString') => new ToStringPrinter(),
            new ReflectionClass($form)->isAnonymous() => new AnonymousClassPrinter(),
            default => new NonPrintableClassPrinter($this->withColor),
        };
    }

    /**
     * @return TypePrinterInterface<mixed>
     */
    private function createScalarTypePrinter(mixed $form): TypePrinterInterface
    {
        $printerName = gettype($form);

        return match (true) {
            $printerName === 'string' => new StringPrinter($this->readable, $this->withColor),
            $printerName === 'integer' || $printerName === 'double' => new NumberPrinter($this->withColor),
            $printerName === 'boolean' => new BooleanPrinter($this->withColor),
            $printerName === 'NULL' => new NullPrinter($this->withColor),
            $printerName === 'array' => new ArrayPrinter($this, $this->withColor),
            $printerName === 'resource' && !$this->readable => new ResourcePrinter(),
            !$this->readable => new ObjectPrinter(),
            default => throw new RuntimeException('Printer cannot print this type: ' . $printerName),
        };
    }
}
