<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use DateTimeImmutable;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInteger;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use Phel\Lang\Rational;
use Phel\Lang\Symbol;
use Phel\Lang\Uuid;
use Phel\Printer\PrinterInterface;
use RuntimeException;

use function addslashes;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_string;
use function preg_match;

final readonly class LiteralEmitter
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private PrinterInterface $printer,
    ) {}

    public function emitLiteral(mixed $x): void
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
        } elseif ($x instanceof DateTimeImmutable) {
            $this->emitDateTime($x);
        } elseif ($x instanceof Rational) {
            $this->emitRational($x);
        } elseif ($x instanceof BigInteger) {
            $this->emitBigInteger($x);
        } elseif ($x instanceof BigDecimal) {
            $this->emitBigDecimal($x);
        } elseif ($x instanceof Uuid) {
            $this->emitUuid($x);
        } elseif (is_array($x)) {
            $this->emitArray($x);
        } else {
            $typeName = $x::class;

            throw new RuntimeException('literal not supported: ' . $typeName);
        }
    }

    private function emitDateTime(DateTimeImmutable $x): void
    {
        // Emit an expression that reconstructs the exact instant and timezone.
        // RFC 3339 with microseconds preserves both.
        $iso = $x->format('Y-m-d\\TH:i:s.uP');
        $this->outputEmitter->emitStr('(new \\DateTimeImmutable("' . addslashes($iso) . '"))');
    }

    private function emitBigInteger(BigInteger $x): void
    {
        $this->outputEmitter->emitStr(
            '\Phel\Lang\BigInteger::fromString("' . $x->__toString() . '")',
        );
    }

    private function emitUuid(Uuid $x): void
    {
        $this->outputEmitter->emitStr(
            '\Phel\Lang\Uuid::fromString("' . $x->__toString() . '")',
            $x->getStartLocation(),
        );
    }

    private function emitBigDecimal(BigDecimal $x): void
    {
        $this->outputEmitter->emitStr(
            '\Phel\Lang\BigDecimal::fromString("' . $x->toPlainString() . '")',
            $x->getStartLocation(),
        );
    }

    private function emitRational(Rational $x): void
    {
        $this->outputEmitter->emitStr(
            '\Phel\Lang\Rational::create('
            . '\Phel\Lang\BigInteger::fromString("' . $x->numerator()->__toString() . '"), '
            . '\Phel\Lang\BigInteger::fromString("' . $x->denominator()->__toString() . '")'
            . ')',
            $x->getStartLocation(),
        );
    }

    private function emitFloat(float $x): void
    {
        $this->outputEmitter->emitStr($this->formatFloatLiteral($x));
    }

    private function formatFloatLiteral(float $x): string
    {
        // NAN and INF have no PHP literal form, and (string) coercion of NAN
        // emits an E_WARNING on PHP 8.5+. Emit constant expressions instead.
        if (is_nan($x)) {
            return 'NAN';
        }

        if (is_infinite($x)) {
            return $x > 0 ? 'INF' : '-INF';
        }

        $str = (string) $x;
        // (string) 10.0 renders as "10"; append .0 so the literal stays
        // a float in PHP. Skip when the string already carries a decimal
        // point or scientific notation (e.g. -9.22E+18) — appending .0
        // would yield invalid PHP like "-9.22E+18.0".
        if (preg_match('/^-?\d+$/', $str) === 1) {
            $str .= '.0';
        }

        return $str;
    }

    private function emitInt(int $x): void
    {
        // PHP's own parser cannot represent PHP_INT_MIN as a literal: `-9223372036854775808`
        // is parsed as `-(9223372036854775808)`, and the unsigned magnitude overflows int
        // to a float, yielding `-9.2e18` instead of the expected int. Emit an expression
        // that stays int-valued at evaluation time.
        if ($x === PHP_INT_MIN) {
            $this->outputEmitter->emitStr('(-9223372036854775807 - 1)');

            return;
        }

        $this->outputEmitter->emitStr((string) $x);
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
        if ($x->getNamespace() !== null) {
            $this->outputEmitter->emitStr(
                '\Phel::keyword("' . $this->escapeForDoubleQuotedString($x->getName()) . '", "' . $this->escapeForDoubleQuotedString($x->getNamespace()) . '")',
                $x->getStartLocation(),
            );
        } else {
            $this->outputEmitter->emitStr(
                '\Phel::keyword("' . $this->escapeForDoubleQuotedString($x->getName()) . '")',
                $x->getStartLocation(),
            );
        }
    }

    private function emitSymbol(Symbol $x): void
    {
        $this->outputEmitter->emitStr(
            '(\Phel\Lang\Symbol::create("' . $this->escapeForDoubleQuotedString($x->getFullName()) . '"))',
            $x->getStartLocation(),
        );
    }

    /**
     * Escape a string so it can be embedded inside a PHP double-quoted
     * literal without losing characters. `addslashes` is wrong here
     * because it escapes the apostrophe with a backslash that PHP keeps
     * verbatim inside `"..."`, polluting symbol/keyword names.
     */
    private function escapeForDoubleQuotedString(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            '$' => '\\$',
        ]);
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
