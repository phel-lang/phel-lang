<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Set;
use Phel\Lang\AbstractStruct;
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
use Phel\Printer\TypePrinter\TypePrinterInterface;
use Phel\Printer\TypePrinter\ResourcePrinter;
use Phel\Printer\TypePrinter\SetPrinter;
use Phel\Printer\TypePrinter\StringPrinter;
use Phel\Printer\TypePrinter\StructPrinter;
use Phel\Printer\TypePrinter\SymbolPrinter;
use Phel\Printer\TypePrinter\TablePrinter;
use Phel\Printer\TypePrinter\TuplePrinter;

final class Printer
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
        $printerName = $this->getTypePrinterName($form);
        $printer = $this->createTypePrinterByName($printerName);

        return $printer->print($form);
    }

    /**
     * @param mixed $form
     */
    private function getTypePrinterName($form): string
    {
        return 'object' === gettype($form)
            ? get_class($form)
            : gettype($form);
    }

    private function createTypePrinterByName(string $printerName): TypePrinterInterface
    {
        if (Tuple::class === $printerName) {
            return new TuplePrinter($this);
        }
        if (Keyword::class === $printerName) {
            return new KeywordPrinter();
        }
        if (Symbol::class === $printerName) {
            return new SymbolPrinter();
        }
        if (Set::class === $printerName) {
            return new SetPrinter($this);
        }
        if (PhelArray::class === $printerName) {
            return new PhelArrayPrinter($this);
        }
        if (AbstractStruct::class === $printerName) {
            return new StructPrinter($this);
        }
        if (Table::class === $printerName) {
            return new TablePrinter($this);
        }
        if ('string' === $printerName) {
            return new StringPrinter($this->readable);
        }
        if ('integer' === $printerName || 'float' === $printerName) {
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

        throw new \RuntimeException('Printer can not print this type: ' . $printerName);
    }
}
