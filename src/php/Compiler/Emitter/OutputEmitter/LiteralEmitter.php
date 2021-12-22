<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Printer\PrinterInterface;
use RuntimeException;

final class LiteralEmitter
{
    private OutputEmitterInterface $outputEmitter;
    private PrinterInterface $printer;

    public function __construct(
        OutputEmitterInterface $outputEmitter,
        PrinterInterface $printer
    ) {
        $this->outputEmitter = $outputEmitter;
        $this->printer = $printer;
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $x The value
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
        } elseif ($x instanceof PersistentMapInterface) {
            $this->emitMap($x);
        } elseif ($x instanceof PersistentVector) {
            $this->emitVector($x);
        } elseif ($x instanceof PersistentListInterface) {
            $this->emitList($x);
        } else {
            $typeName = gettype($x);
            if ($typeName === 'object') {
                $typeName = get_class($x);
            }
            throw new RuntimeException('literal not supported: ' . $typeName);
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
        $this->outputEmitter->emitStr($this->printer->print($x));
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
        if ($x->getNamespace()) {
            $this->outputEmitter->emitStr(
                '\Phel\Lang\Keyword::createForNamespace("' . addslashes($x->getNamespace()) . '", "' . addslashes($x->getName()) . '")',
                $x->getStartLocation()
            );
        } else {
            $this->outputEmitter->emitStr(
                '\Phel\Lang\Keyword::create("' . addslashes($x->getName()) . '")',
                $x->getStartLocation()
            );
        }
    }

    private function emitSymbol(Symbol $x): void
    {
        $this->outputEmitter->emitStr(
            '(\Phel\Lang\Symbol::create("' . addslashes($x->getFullName()) . '"))',
            $x->getStartLocation()
        );
    }

    private function emitMap(PersistentMapInterface $x): void
    {
        $this->outputEmitter->emitStr('\Phel\Lang\TypeFactory::getInstance()->persistentMapFromKVs(', $x->getStartLocation());
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

    private function emitList(PersistentListInterface $x): void
    {
        $this->outputEmitter->emitStr('\Phel\Lang\TypeFactory::getInstance()->persistentListFromArray([', $x->getStartLocation());

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
        $this->outputEmitter->emitStr('])', $x->getStartLocation());
    }

    private function emitVector(PersistentVector $x): void
    {
        $this->outputEmitter->emitStr('\Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray([', $x->getStartLocation());

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
        $this->outputEmitter->emitStr('])', $x->getStartLocation());
    }
}
