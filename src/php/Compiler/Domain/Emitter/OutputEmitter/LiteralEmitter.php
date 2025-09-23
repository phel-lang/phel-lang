<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Printer\PrinterInterface;
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
        } elseif ($x instanceof PersistentHashSetInterface) {
            $this->emitSet($x);
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
                '\Phel::keyword("' . addslashes($x->getName()) . '", "' . addslashes($x->getNamespace()) . '")',
                $x->getStartLocation(),
            );
        } else {
            $this->outputEmitter->emitStr(
                '\Phel::keyword("' . addslashes($x->getName()) . '")',
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
        $count = count($x);
        $this->outputEmitter->emitStr('\Phel::map(', $x->getStartLocation());
        if ($count > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        $i = 0;
        foreach ($x as $key => $value) {
            $this->outputEmitter->emitLiteral($key);
            $this->outputEmitter->emitStr(', ', $x->getStartLocation());
            $this->outputEmitter->emitLiteral($value);
            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }

            $this->outputEmitter->emitLine();
            ++$i;
        }

        if ($count > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }

        $this->outputEmitter->emitStr(')', $x->getStartLocation());
    }

    private function emitList(PersistentListInterface $x): void
    {
        $count = count($x);
        $this->outputEmitter->emitStr('\Phel::list([', $x->getStartLocation());

        if ($count > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        foreach ($x as $i => $value) {
            $this->outputEmitter->emitLiteral($value);
            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }

            $this->outputEmitter->emitLine();
        }

        if ($count > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }

        $this->outputEmitter->emitStr('])', $x->getStartLocation());
    }

    private function emitSet(PersistentHashSetInterface $x): void
    {
        $count = count($x);
        $this->outputEmitter->emitStr('\\Phel::set([', $x->getStartLocation());

        if ($count > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        $i = 0;
        foreach ($x as $value) {
            $this->outputEmitter->emitLiteral($value);
            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }

            $this->outputEmitter->emitLine();
            ++$i;
        }

        if ($count > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }

        $this->outputEmitter->emitStr('])', $x->getStartLocation());
    }

    private function emitVector(PersistentVector $x): void
    {
        $countVector = count($x);
        $this->outputEmitter->emitStr('\Phel::vector([', $x->getStartLocation());

        if ($countVector > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        foreach ($x as $i => $value) {
            $this->outputEmitter->emitLiteral($value);
            if ($i < $countVector - 1) {
                $this->outputEmitter->emitStr(',', $x->getStartLocation());
            }

            $this->outputEmitter->emitLine();
        }

        if ($countVector > 0) {
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
