<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\BigInt;
use Phel\Lang\Collections\ValueIdentity;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use const NAN;

final class ValueIdentityTest extends TestCase
{
    public static function provideSameValues(): iterable
    {
        $object = new stdClass();
        $vector = TypeFactory::getInstance()->persistentVectorFromArray([1, 2]);

        yield 'int' => [1, 1];
        yield 'string' => ['a', 'a'];
        yield 'null' => [null, null];
        yield 'bool' => [true, true];
        yield 'float' => [1.5, 1.5];
        yield 'positive zero' => [0.0, 0.0];
        yield 'negative zero' => [-0.0, -0.0];
        yield 'keyword' => [Keyword::create('k'), Keyword::create('k')];
        yield 'same object' => [$object, $object];
        yield 'same vector' => [$vector, $vector];
    }

    public static function provideDifferentValues(): iterable
    {
        $factory = TypeFactory::getInstance();

        yield 'int' => [1, 2];
        yield 'int and float' => [1, 1.0];
        yield 'int and string' => [1, '1'];
        yield 'negative over positive zero' => [0.0, -0.0];
        yield 'positive over negative zero' => [-0.0, 0.0];
        yield 'nan' => [NAN, NAN];
        yield 'int and bigint' => [1, BigInt::fromInt(1)];
        yield 'equal but distinct objects' => [new stdClass(), new stdClass()];
        yield 'equal but distinct vectors' => [
            $factory->persistentVectorFromArray([1, 2]),
            $factory->persistentVectorFromArray([1, 2]),
        ];
        yield 'equal php arrays' => [[1, 2], [1, 2]];
        yield 'the same php array' => [$array = [1, 2], $array];
        yield 'php array over a scalar' => [1, [1]];
        yield 'scalar over a php array' => [[1], 1];
    }

    #[DataProvider('provideSameValues')]
    public function test_identical_values_are_the_same(mixed $stored, mixed $new): void
    {
        self::assertTrue(ValueIdentity::isSame($stored, $new));
    }

    #[DataProvider('provideDifferentValues')]
    public function test_distinguishable_values_are_not_the_same(mixed $stored, mixed $new): void
    {
        self::assertFalse(ValueIdentity::isSame($stored, $new));
    }

    public function test_distinct_recursive_arrays_are_not_the_same_and_do_not_throw(): void
    {
        $stored = [1];
        $stored[] = &$stored;
        $new = [1];
        $new[] = &$new;

        self::assertFalse(ValueIdentity::isSame($stored, $new));
    }

    public function test_arrays_holding_distinct_references_to_equal_values_are_not_the_same(): void
    {
        $x = 1;
        $y = 1;

        self::assertFalse(ValueIdentity::isSame([&$x], [&$y]));
    }
}
