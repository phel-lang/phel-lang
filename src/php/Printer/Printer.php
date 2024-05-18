<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Printer\TypePrinter\AnonymousClassPrinter;
use Phel\Printer\TypePrinter\ArrayPrinter;
use Phel\Printer\TypePrinter\BooleanPrinter;
use Phel\Printer\TypePrinter\FnPrinter;
use Phel\Printer\TypePrinter\KeywordPrinter;
use Phel\Printer\TypePrinter\NonPrintableClassPrinter;
use Phel\Printer\TypePrinter\NullPrinter;
use Phel\Printer\TypePrinter\NumberPrinter;
use Phel\Printer\TypePrinter\ObjectPrinter;
use Phel\Printer\TypePrinter\PersistentHashSetPrinter;
use Phel\Printer\TypePrinter\PersistentListPrinter;
use Phel\Printer\TypePrinter\PersistentMapPrinter;
use Phel\Printer\TypePrinter\PersistentVectorPrinter;
use Phel\Printer\TypePrinter\ResourcePrinter;
use Phel\Printer\TypePrinter\StringPrinter;
use Phel\Printer\TypePrinter\StructPrinter;
use Phel\Printer\TypePrinter\SymbolPrinter;
use Phel\Printer\TypePrinter\ToStringPrinter;
use Phel\Printer\TypePrinter\TypePrinterInterface;
use ReflectionClass;
use RuntimeException;

use function gettype;
use function is_object;

final readonly class Printer implements PrinterInterface
{
    public function __construct(
        private bool $readable,
        private bool $withColor = false,
    ) {
    }

    public static function readable(): self
    {
        return new self(readable: true);
    }

    public static function nonReadable(): self
    {
        return new self(readable: false);
    }

    public static function nonReadableWithColor(): self
    {
        return new self(readable: false, withColor: true);
    }

    /**
     * Converts a form to a printable string.
     */
    public function print(mixed $form): string
    {
        return $this->createTypePrinter($form)->print($form);
    }

    private function createTypePrinter(mixed $form): TypePrinterInterface
    {
        if (is_object($form)) {
            return $this->createObjectTypePrinter($form);
        }

        return $this->creatScalarTypePrinter($form);
    }

    private function createObjectTypePrinter(mixed $form): TypePrinterInterface
    {
        if ($form instanceof AbstractPersistentStruct) {
            return new StructPrinter($this);
        }

        if ($form instanceof PersistentListInterface) {
            return new PersistentListPrinter($this);
        }

        if ($form instanceof PersistentVectorInterface) {
            return new PersistentVectorPrinter($this);
        }

        if ($form instanceof PersistentMapInterface) {
            return new PersistentMapPrinter($this);
        }

        if ($form instanceof PersistentHashSetInterface) {
            return new PersistentHashSetPrinter($this);
        }

        if ($form instanceof Keyword) {
            return new KeywordPrinter($this->withColor);
        }

        if ($form instanceof Symbol) {
            return new SymbolPrinter($this->withColor);
        }

        if (method_exists($form, '__toString')) {
            return new ToStringPrinter();
        }

        if ($form instanceof FnInterface) {
            return new FnPrinter();
        }

        if ((new ReflectionClass($form))->isAnonymous()) {
            return new AnonymousClassPrinter();
        }

        return new NonPrintableClassPrinter($this->withColor);
    }

    private function creatScalarTypePrinter(mixed $form): TypePrinterInterface
    {
        $printerName = gettype($form);

        if ($printerName === 'string') {
            return new StringPrinter($this->readable, $this->withColor);
        }

        if ($printerName === 'integer' || $printerName === 'double') {
            return new NumberPrinter($this->withColor);
        }

        if ($printerName === 'boolean') {
            return new BooleanPrinter($this->withColor);
        }

        if ($printerName === 'NULL') {
            return new NullPrinter($this->withColor);
        }

        if ($printerName === 'array' && !$this->readable) {
            return new ArrayPrinter($this, $this->withColor);
        }

        if ($printerName === 'resource' && !$this->readable) {
            return new ResourcePrinter();
        }

        if (!$this->readable) {
            return new ObjectPrinter();
        }

        throw new RuntimeException('Printer cannot print this type: ' . $printerName);
    }
}
