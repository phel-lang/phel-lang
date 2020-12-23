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
use Phel\Printer\ArrayPrinter;
use Phel\Printer\BooleanPrinter;
use Phel\Printer\KeywordPrinter;
use Phel\Printer\NullPrinter;
use Phel\Printer\NumericalPrinter;
use Phel\Printer\ObjectPrinter;
use Phel\Printer\PhelArrayPrinter;
use Phel\Printer\PrinterInterface;
use Phel\Printer\ResourcePrinter;
use Phel\Printer\SetPrinter;
use Phel\Printer\StringPrinter;
use Phel\Printer\StructPrinter;
use Phel\Printer\SymbolPrinter;
use Phel\Printer\TablePrinter;
use Phel\Printer\TuplePrinter;

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
        $printerName = $this->getPrinterName($form);
        $printer = $this->createPrinterByName($printerName);

        return $printer->print($form);
    }

    /**
     * @param mixed $form
     */
    private function getPrinterName($form): string
    {
        return 'object' === gettype($form)
            ? get_class($form)
            : gettype($form);
    }

    private function createPrinterByName(string $printerName): PrinterInterface
    {
        if ($printerName === Tuple::class) {
            return new TuplePrinter($this);
        }
        if ($printerName === Keyword::class) {
            return new KeywordPrinter();
        }
        if ($printerName === Symbol::class) {
            return new SymbolPrinter();
        }
        if ($printerName === Set::class) {
            return new SetPrinter($this);
        }
        if ($printerName === PhelArray::class) {
            return new PhelArrayPrinter($this);
        }
        if ($printerName === Struct::class) {
            return new StructPrinter($this);
        }
        if ($printerName === Table::class) {
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
