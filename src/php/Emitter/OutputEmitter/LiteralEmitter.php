<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter;

use Phel\Emitter\OutputEmitter;
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
    private OutputEmitter $outputEmitter;

    public function __construct(OutputEmitter $emitter)
    {
        $this->outputEmitter = $emitter;
    }

    /**
     * @param AbstractType|string|float|int|bool|null $x The value
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
        $float = ((int)$x == $x)
            // (string) 10.0 will return 10 and not 10.0
            // so we just add a .0 at the end
            ? ((string)$x) . '.0'
            : ((string)$x);

        $this->outputEmitter->emitStr($float);
    }

    private function emitInt(int $x): void
    {
        $this->outputEmitter->emitStr((string)$x);
    }

    private function emitStr(string $x): void
    {
        $this->outputEmitter->emitStr(Printer::readable()->print($x));
    }

    private function emitNull(): void
    {
        $this->outputEmitter->emitStr('null');
    }

    private function emitBool(bool $x): void
    {
        $this->outputEmitter->emitStr($x === true ? 'true' : 'false');
    }

    private function emitKeyword(Keyword $x): void
    {
        $this->outputEmitter->emitStr(
            'new \Phel\Lang\Keyword("' . addslashes($x->getName()) . '")',
            $x->getStartLocation()
        );
    }

    private function emitSymbol(Symbol $x): void
    {
        $this->outputEmitter->emitStr(
            '(\Phel\Lang\Symbol::create("' . addslashes($x->getFullName()) . '"))',
            $x->getStartLocation()
        );
    }

    private function emitPhelArray(PhelArray $x): void
    {
        $this->outputEmitter->emitStr('\Phel\Lang\PhelArray::create(', $x->getStartLocation());
        if (count($x) > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        foreach ($x as $i => $value) {
            $this->outputEmitter->emitLiteral($value);
            if ($i < count($x) - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }
            $this->outputEmitter->emitLine();
        }

        if (count($x) > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }
        $this->outputEmitter->emitStr(')', $x->getStartLocation());
    }

    private function emitTable(Table $x): void
    {
        $this->outputEmitter->emitStr('\Phel\Lang\Table::fromKVs(', $x->getStartLocation());
        if (count($x) > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        $i = 0;
        foreach ($x as $key => $value) {
            $this->outputEmitter->emitLiteral($key);
            $this->outputEmitter->emitStr(', ', $x->getStartLocation());
            $this->outputEmitter->emitLiteral($value);
            if ($i < count($x) - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }
            $this->outputEmitter->emitLine();
            $i++;
        }

        if (count($x) > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }
        $this->outputEmitter->emitStr(')', $x->getStartLocation());
    }

    private function emitTuple(Tuple $x): void
    {
        if ($x->isUsingBracket()) {
            $this->outputEmitter->emitStr('\Phel\Lang\Tuple::createBracket(', $x->getStartLocation());
        } else {
            $this->outputEmitter->emitStr('\Phel\Lang\Tuple::create(', $x->getStartLocation());
        }

        if (count($x) > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        foreach ($x as $i => $value) {
            $this->outputEmitter->emitLiteral($value);
            if ($i < count($x) - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }
            $this->outputEmitter->emitLine();
        }

        if (count($x) > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }
        $this->outputEmitter->emitStr(')', $x->getStartLocation());
    }
}
