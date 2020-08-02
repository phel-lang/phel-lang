<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Emitter;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Printer;
use RuntimeException;

final class LiteralEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * @param AbstractType|scalar|null $x The value
     */
    public function emitLiteral($x): void
    {
        if (is_float($x)) {
            $this->emitFloat($x);
        } elseif (is_int($x)) {
            $this->emitInt($x);
        } elseif (is_string($x)) {
            $this->emitStr($x);
        } elseif ($x === null) {
            $this->emitNull();
        } elseif (is_bool($x)) {
            $this->emitBool($x);
        } elseif ($x instanceof Keyword) {
            $this->emitKeyword($x);
        } elseif ($x instanceof Symbol) {
            $this->emitSymbol($x);
        } elseif ($x instanceof PhelArray) {
            $this->emitPhelArray($x);
        } elseif ($x instanceof Table) {
            $this->emitTable($x);
        } elseif ($x instanceof Tuple) {
            $this->emitTuple($x);
        } else {
            throw new RuntimeException('literal not supported: ' . gettype($x));
        }
    }

    private function emitFloat(float $x): void
    {
        $this->emitter->emitStr($this->printFloat($x));
    }

    private function printFloat(float $x): string
    {
        if ((int)$x == $x) {
            // (string) 10.0 will return 10 and not 10.0
            // so we just add a .0 at the end
            return ((string)$x) . '.0';
        }

        return ((string)$x);
    }

    private function emitInt(int $x): void
    {
        $this->emitter->emitStr((string)$x);
    }

    private function emitStr(string $x): void
    {
        $this->emitter->emitStr(Printer::readable()->print($x));
    }

    private function emitNull(): void
    {
        $this->emitter->emitStr('null');
    }

    private function emitBool(bool $x): void
    {
        $this->emitter->emitStr($x === true ? 'true' : 'false');
    }

    private function emitKeyword(Keyword $x): void
    {
        $this->emitter->emitStr('new \Phel\Lang\Keyword("' . addslashes($x->getName()) . '")', $x->getStartLocation());
    }

    private function emitSymbol(Symbol $x): void
    {
        $this->emitter->emitStr(
            '(\Phel\Lang\Symbol::create("' . addslashes($x->getFullName()) . '"))',
            $x->getStartLocation()
        );
    }

    private function emitPhelArray(PhelArray $x): void
    {
        $this->emitter->emitStr('\Phel\Lang\PhelArray::create(', $x->getStartLocation());
        if (count($x) > 0) {
            $this->emitter->increaseIndentLevel();
            $this->emitter->emitLine();
        }

        foreach ($x as $i => $value) {
            $this->emitter->emitLiteral($value);

            if ($i < count($x) - 1) {
                $this->emitter->emitStr(',', $x->getStartLocation());
            }

            $this->emitter->emitLine();
        }

        if (count($x) > 0) {
            $this->emitter->decreaseIndentLevel();
        }

        $this->emitter->emitStr(')', $x->getStartLocation());
    }

    private function emitTable(Table $x): void
    {
        $this->emitter->emitStr('\Phel\Lang\Table::fromKVs(', $x->getStartLocation());

        if (count($x) > 0) {
            $this->emitter->increaseIndentLevel();
            $this->emitter->emitLine();
        }

        $i = 0;
        foreach ($x as $key => $value) {
            $this->emitter->emitLiteral($key);
            $this->emitter->emitStr(', ', $x->getStartLocation());
            $this->emitter->emitLiteral($value);

            if ($i < count($x) - 1) {
                $this->emitter->emitStr(',', $x->getStartLocation());
            }
            $this->emitter->emitLine();

            $i++;
        }

        if (count($x) > 0) {
            $this->emitter->decreaseIndentLevel();
        }
        $this->emitter->emitStr(')', $x->getStartLocation());
    }

    private function emitTuple(Tuple $x): void
    {
        if ($x->isUsingBracket()) {
            $this->emitter->emitStr('\Phel\Lang\Tuple::createBracket(', $x->getStartLocation());
        } else {
            $this->emitter->emitStr('\Phel\Lang\Tuple::create(', $x->getStartLocation());
        }

        if (count($x) > 0) {
            $this->emitter->increaseIndentLevel();
            $this->emitter->emitLine();
        }

        foreach ($x as $i => $value) {
            $this->emitter->emitLiteral($value);

            if ($i < count($x) - 1) {
                $this->emitter->emitStr(',', $x->getStartLocation());
            }

            $this->emitter->emitLine();
        }

        if (count($x) > 0) {
            $this->emitter->decreaseIndentLevel();
        }

        $this->emitter->emitStr(')', $x->getStartLocation());
    }
}
