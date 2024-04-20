<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Printer\PrinterInterface;
use Phel\Transpiler\Domain\Emitter\OutputEmitterInterface;
use RuntimeException;

use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class LiteralEmitter
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private PrinterInterface $printer,
    ) {
    }

    public function emitLiteral(TypeInterface|array|string|float|bool|int|null $x): void
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
        } elseif (is_array($x)) {
            $this->emitArray($x);
        } else {
            $typeName = $x::class;

            throw new RuntimeException('literal not supported: ' . $typeName);
        }
    }

    private function emitFloat(float $x): void
    {
        /** @psalm-suppress InvalidCast */
        $float = ((int)$x == $x)
            // (string) 10.0 will return 10 and not 10.0
            // so, we just add a .0 at the end
            ? ($x) . '.0'
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
        $this->outputEmitter->emitStr($x ? 'true' : 'false');
    }

    private function emitKeyword(Keyword $x): void
    {
        if ($x->getNamespace() !== null && $x->getNamespace() !== '') {
            $this->outputEmitter->emitStr(
                '\Phel\Lang\Keyword::createForNamespace("' . addslashes($x->getNamespace()) . '", "' . addslashes($x->getName()) . '")',
                $x->getStartLocation(),
            );
        } else {
            $this->outputEmitter->emitStr(
                '\Phel\Lang\Keyword::create("' . addslashes($x->getName()) . '")',
                $x->getStartLocation(),
            );
        }
    }

    private function emitSymbol(Symbol $x): void
    {
        $this->outputEmitter->emitStr(
            '(\Phel\Lang\Symbol::create("' . addslashes($x->getFullName()) . '"))',
            $x->getStartLocation(),
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
            ++$i;
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

    private function emitArray(array $x): void
    {
        $this->outputEmitter->emitStr('[');
        $count = count($x);
        $index = 0;
        foreach ($x as $key => $value) {
            $this->outputEmitter->emitLiteral($key);
            $this->outputEmitter->emitStr(' => ');
            $this->outputEmitter->emitLiteral($value);
            if ($index < $count - 1) {
                $this->outputEmitter->emitStr(', ');
            }

            ++$index;
        }

        $this->outputEmitter->emitStr(']');
    }
}
