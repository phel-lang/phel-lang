<?php

declare(strict_types=1);

namespace Phel;

use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Set;
use Phel\Lang\Struct;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Printer\ElementPrinter\ArrayPrinter;
use Phel\Printer\ElementPrinter\BooleanPrinter;
use Phel\Printer\ElementPrinter\KeywordPrinter;
use Phel\Printer\ElementPrinter\NullPrinter;
use Phel\Printer\ElementPrinter\NumericalPrinter;
use Phel\Printer\ElementPrinter\ObjectPrinter;
use Phel\Printer\ElementPrinter\PhelArrayPrinter;
use Phel\Printer\ElementPrinter\ElementPrinterInterface;
use Phel\Printer\ElementPrinter\ResourcePrinter;
use Phel\Printer\ElementPrinter\SetPrinter;
use Phel\Printer\ElementPrinter\StringPrinter;
use Phel\Printer\ElementPrinter\StructPrinter;
use Phel\Printer\ElementPrinter\SymbolPrinter;
use Phel\Printer\ElementPrinter\TablePrinter;
use Phel\Printer\ElementPrinter\TuplePrinter;

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
        $printerName = $this->getElementPrinterName($form);
        $printer = $this->createElementPrinterByName($printerName);

        return $printer->print($form);
    }

    /**
     * @param mixed $form
     */
    private function getElementPrinterName($form): string
    {
        return 'object' === gettype($form)
            ? get_class($form)
            : gettype($form);
    }

    private function createElementPrinterByName(string $printerName): ElementPrinterInterface
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
        if (Struct::class === $printerName) {
            return new StructPrinter($this);
        }
        if (Table::class === $printerName) {
            return new TablePrinter($this);
        }
        if ('string' === $printerName) {
            return new StringPrinter($this->readable);
        }
        if ('integer' === $printerName || 'float' === $printerName) {
            return new NumericalPrinter();
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
