<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Iterator;
use Phel\Lang\Collections\LazySeq\LazySeq;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeStringifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

final class TypeStringifierTest extends TestCase
{
    public function test_to_string_prints_a_type_readably(): void
    {
        self::assertSame(':foo', TypeStringifier::toString(Keyword::create('foo')));
    }

    /**
     * @return Iterator<int<0, max>, array{mixed, string}>
     */
    public static function providerBoundedValues(): Iterator
    {
        yield [null, 'nil'];
        yield [true, 'true'];
        yield [42, '42'];
        yield ['hi', '"hi"'];
        yield [Keyword::create('foo'), ':foo'];
        yield [Symbol::create('bar'), 'bar'];
    }

    #[DataProvider('providerBoundedValues')]
    public function test_describe_prints_inherently_small_values(mixed $value, string $expected): void
    {
        self::assertSame($expected, TypeStringifier::describe($value));
    }

    public function test_describe_truncates_a_long_string(): void
    {
        $described = TypeStringifier::describe(str_repeat('a', 500));

        self::assertLessThan(70, strlen($described));
        self::assertStringEndsWith('...', $described);
    }

    public function test_describe_degrades_a_collection_to_its_type(): void
    {
        $vector = TypeFactory::getInstance()->persistentVectorFromArray([1, 2, 3]);

        self::assertSame('#object[' . $vector::class . ']', TypeStringifier::describe($vector));
    }

    /**
     * The whole point of the bound: an error message must never force an
     * infinite seq while rendering the value that caused the error.
     */
    public function test_describe_never_realizes_an_infinite_lazy_seq(): void
    {
        $typeFactory = TypeFactory::getInstance();
        $realizations = 0;
        $endless = static function () use (&$endless, &$realizations, $typeFactory): LazySeq {
            ++$realizations;
            return new LazySeq(
                $typeFactory->getHasher(),
                $typeFactory->getEqualizer(),
                static fn(): LazySeq => $endless(),
            );
        };

        self::assertSame('#object[' . LazySeq::class . ']', TypeStringifier::describe($endless()));
        self::assertSame(1, $realizations, 'describe() must not drive the seq');
    }
}
