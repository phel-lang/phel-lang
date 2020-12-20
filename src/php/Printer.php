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
        if ($form instanceof Tuple) {
            return (new TuplePrinter($this))->print($form);
        }
        if ($form instanceof Keyword) {
            return (new KeywordPrinter())->print($form);
        }
        if ($form instanceof Symbol) {
            return (new SymbolPrinter())->print($form);
        }
        if ($form instanceof Set) {
            return (new SetPrinter($this))->print($form);
        }
        if ($form instanceof PhelArray) {
            return (new PhelArrayPrinter($this))->print($form);
        }
        if ($form instanceof Struct) {
            return (new StructPrinter($this))->print($form);
        }
        if ($form instanceof Table) {
            return (new TablePrinter($this))->print($form);
        }
        if (is_string($form)) {
            return (new StringPrinter($this->readable))->print($form);
        }
        if (is_int($form) || is_float($form)) {
            return (new NumericalPrinter())->print($form);
        }
        if (is_bool($form)) {
            return (new BooleanPrinter())->print($form);
        }
        if ($form === null) {
            return (new NullPrinter())->print();
        }
        if (is_array($form) && !$this->readable) {
            return (new ArrayPrinter())->print();
        }
        if (is_resource($form) && !$this->readable) {
            return (new ResourcePrinter())->print($form);
        }
        if (is_object($form) && !$this->readable) {
            return (new ObjectPrinter())->print($form);
        }

        $type = gettype($form);
        if ($type === 'object') {
            $type = get_class($form);
        }
        throw new \RuntimeException('Printer can not print this type: ' . $type);
    }
}
