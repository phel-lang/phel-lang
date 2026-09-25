<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Destructure;
use Phel\Lang\Keyword;
use PHPUnit\Framework\TestCase;

/**
 * `Destructure::nthNext` is the `& r` tail a vector pattern compiles to
 * (#3356), so it must answer what `next` applied that many times answers.
 * `tests/phel/core/destructuring-indexed.phel` compares the two with the
 * core loaded.
 */
final class DestructureTest extends TestCase
{
    public function test_zero_steps_is_the_vector_itself(): void
    {
        $v = Phel::vector([1, 2]);

        self::assertSame($v, Destructure::nthNext($v, 0));
    }

    public function test_each_step_is_one_cdr(): void
    {
        $v = Phel::vector([1, 2, 3, 4]);

        $expected = $v->cdr()?->cdr();

        self::assertEquals($expected, Destructure::nthNext($v, 2));
        self::assertSame([3, 4], $this->toArray(Destructure::nthNext($v, 2)));
    }

    public function test_exhausted_is_nil_not_empty(): void
    {
        $v = Phel::vector([1, 2]);

        self::assertNull(Destructure::nthNext($v, 2));
        self::assertNull(Destructure::nthNext($v, 5));
        self::assertNull(Destructure::nthNext(Phel::vector([]), 1));
    }

    public function test_keeps_the_vector_metadata(): void
    {
        $meta = Phel::map(Keyword::create('m'), 1);
        $v = Phel::vector([1, 2, 3])->withMeta($meta);

        $rest = Destructure::nthNext($v, 1);

        self::assertInstanceOf(PersistentVectorInterface::class, $rest);
        self::assertSame($meta, $rest->getMeta());
    }

    public function test_a_subvector_steps_from_its_own_start(): void
    {
        $sub = Phel::vector([0, 1, 2, 3, 4, 5])->slice(1, 4);

        self::assertSame([3, 4], $this->toArray(Destructure::nthNext($sub, 2)));
    }

    /**
     * @return array<int, mixed>
     */
    private function toArray(mixed $v): array
    {
        self::assertInstanceOf(PersistentVectorInterface::class, $v);

        return $v->toArray();
    }
}
