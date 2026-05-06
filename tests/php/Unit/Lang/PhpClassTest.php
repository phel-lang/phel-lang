<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use ArrayObject;
use Countable;
use InvalidArgumentException;
use Phel\Lang\PhpClass;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class PhpClassTest extends TestCase
{
    public function test_from_name_strips_leading_backslash(): void
    {
        $a = PhpClass::fromName('\\' . Symbol::class);
        $b = PhpClass::fromName(Symbol::class);

        self::assertSame(Symbol::class, $a->name());
        self::assertTrue($a->equals($b));
    }

    public function test_from_name_accepts_interface(): void
    {
        $c = PhpClass::fromName(Countable::class);

        self::assertSame(Countable::class, $c->name());
    }

    public function test_from_name_rejects_unknown_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhpClass::fromName('Phel\\Lang\\NoSuchClass');
    }

    public function test_from_name_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhpClass::fromName('');
    }

    public function test_of_value_uses_runtime_class(): void
    {
        $sym = Symbol::create('foo');

        $c = PhpClass::ofValue($sym);

        self::assertSame(Symbol::class, $c->name());
    }

    public function test_of_value_rejects_non_object(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhpClass::ofValue('not-an-object');
    }

    public function test_is_instance_true_for_matching_class(): void
    {
        $c = PhpClass::fromName(Symbol::class);

        self::assertTrue($c->isInstance(Symbol::create('x')));
    }

    public function test_is_instance_true_for_subclass_or_interface(): void
    {
        $countable = PhpClass::fromName(Countable::class);

        self::assertTrue($countable->isInstance(new ArrayObject([1, 2])));
    }

    public function test_is_instance_false_for_other_value(): void
    {
        $c = PhpClass::fromName(Symbol::class);

        self::assertFalse($c->isInstance('a string'));
        self::assertFalse($c->isInstance(42));
        self::assertFalse($c->isInstance(null));
    }

    public function test_equals_only_other_php_class_with_same_fqn(): void
    {
        $a = PhpClass::fromName(Symbol::class);
        $b = PhpClass::fromName(Symbol::class);
        $c = PhpClass::fromName(Countable::class);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals(Symbol::class));
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_to_string_returns_fqn(): void
    {
        self::assertSame(Symbol::class, (string) PhpClass::fromName(Symbol::class));
    }
}
