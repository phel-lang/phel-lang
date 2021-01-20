<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\AbstractStruct;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Set;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Printer\TypePrinter\ArrayPrinter;
use Phel\Printer\TypePrinter\BooleanPrinter;
use Phel\Printer\TypePrinter\KeywordPrinter;
use Phel\Printer\TypePrinter\NullPrinter;
use Phel\Printer\TypePrinter\NumberPrinter;
use Phel\Printer\TypePrinter\ObjectPrinter;
use Phel\Printer\TypePrinter\PhelArrayPrinter;
use Phel\Printer\TypePrinter\ResourcePrinter;
use Phel\Printer\TypePrinter\SetPrinter;
use Phel\Printer\TypePrinter\StringPrinter;
use Phel\Printer\TypePrinter\StructPrinter;
use Phel\Printer\TypePrinter\SymbolPrinter;
use Phel\Printer\TypePrinter\TablePrinter;
use Phel\Printer\TypePrinter\TuplePrinter;
use Phel\Printer\TypePrinter\TypePrinterInterface;
use RuntimeException;

final class Printer implements PrinterInterface
{
    private bool $readable;

    public static function readable(): self
    {
        return new self(true);
    }

    public static function nonReadable(): self
    {
        return new self(false);
    }

    private function __construct(bool $readable)
    {
        $this->readable = $readable;
    }

    /**
     * Converts a form to a printable string.
     *
     * @param mixed $form The form to print
     */
    public function print($form): string
    {
        $printer = $this->createTypePrinter($form);

        return $printer->print($form);
    }

    /**
     * @param mixed $form The form to print
     */
    private function createTypePrinter($form): TypePrinterInterface
    {
        if (is_object($form)) {
            return $this->createObjectTypePrinter($form);
        }

        return $this->creatScalarTypePrinter($form);
    }

    /**
     * @param mixed $form The form to print
     */
    private function createObjectTypePrinter($form): TypePrinterInterface
    {
        if ($form instanceof Tuple) {
            return new TuplePrinter($this);
        }
        if ($form instanceof Keyword) {
            return new KeywordPrinter();
        }
        if ($form instanceof Symbol) {
            return new SymbolPrinter();
        }
        if ($form instanceof Set) {
            return new SetPrinter($this);
        }
        if ($form instanceof PhelArray) {
            return new PhelArrayPrinter($this);
        }
        if ($form instanceof Table) {
            return new TablePrinter($this);
        }
        if ($form instanceof AbstractStruct) {
            return new StructPrinter($this);
        }

        throw new RuntimeException('Printer can not print this type: ' . get_class($form));
    }

    /**
     * @param mixed $form The form to print
     */
    private function creatScalarTypePrinter($form): TypePrinterInterface
    {
        $printerName = gettype($form);

        if ('string' === $printerName) {
            return new StringPrinter($this->readable);
        }
        if ('integer' === $printerName || 'double' === $printerName) {
            return new NumberPrinter();
        }
        if ('boolean' === $printerName) {
            return new BooleanPrinter();
        }
        if ('NULL' === $printerName) {
            return new NullPrinter();
        }
        if ('array' === $printerName && !$this->readable) {
            return new ArrayPrinter();
        }
        if ('resource' === $printerName && !$this->readable) {
            return new ResourcePrinter();
        }
        if (!$this->readable) {
            return new ObjectPrinter();
        }

        throw new RuntimeException('Printer can not print this type: ' . $printerName);
    }
}
