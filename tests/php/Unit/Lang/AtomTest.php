<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use InvalidArgumentException;
use Phel\Lang\Atom;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

use function is_int;
use function str_repeat;
use function strlen;

final class AtomTest extends TestCase
{
    public function test_deref(): void
    {
        $v = new Atom(null, 10);
        $this->assertSame(10, $v->deref());
    }

    public function test_set(): void
    {
        $v = new Atom(null, 10);
        $v->set(20);
        $this->assertSame(20, $v->deref());
    }

    public function test_set_returns_new_value(): void
    {
        $v = new Atom(null, 10);
        $this->assertSame(20, $v->set(20));
    }

    public function test_set_returns_value_after_watch_runs(): void
    {
        $v = new Atom(null, 1);
        $called = false;
        $v->addWatch('w', static function () use (&$called): void {
            $called = true;
        });

        $this->assertSame(2, $v->set(2));
        $this->assertTrue($called);
    }

    public function test_set_throws_when_validator_rejects(): void
    {
        $v = new Atom(null, 1);
        $v->setValidator(static fn($value): bool => $value > 0);

        $this->expectException(InvalidArgumentException::class);
        $v->set(-1);
    }

    public function test_the_rejection_message_names_a_small_offending_value(): void
    {
        $v = new Atom(null, 1);
        $v->setValidator(static fn($value): bool => $value > 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Atom validator rejected the value: -1');
        $v->set(-1);
    }

    /**
     * Printing the rejected value in full would let an arbitrarily deep, or
     * infinite, structure blow up the error path, so a collection degrades to
     * its type name instead of being rendered.
     */
    public function test_the_rejection_message_degrades_a_collection_to_its_type(): void
    {
        $v = new Atom(null, 1);
        $v->setValidator(static fn($value): bool => is_int($value));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Atom validator rejected the value: #object[' . PersistentVector::class . ']');
        $v->set(TypeFactory::getInstance()->persistentVectorFromArray([1, 2, 3]));
    }

    public function test_the_rejection_message_is_length_capped(): void
    {
        $v = new Atom(null, '');
        $v->setValidator(static fn($value): bool => strlen((string) $value) < 10);

        try {
            $v->set(str_repeat('a', 500));
            self::fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertLessThan(120, strlen($invalidArgumentException->getMessage()));
            self::assertStringEndsWith('...', $invalidArgumentException->getMessage());
        }
    }

    public function test_set_validator_rejecting_the_current_value_names_it_too(): void
    {
        $v = new Atom(null, -5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Atom validator rejected the value: -5');
        $v->setValidator(static fn($value): bool => $value > 0);
    }

    public function test_equals(): void
    {
        $v1 = new Atom(null, 10);
        $v2 = new Atom(null, 10);

        $this->assertTrue($v1->equals($v1));
        $this->assertFalse($v1->equals($v2));
        $this->assertFalse($v2->equals($v1));
    }

    public function test_hash(): void
    {
        $v1 = new Atom(null, 10);

        $this->assertSame(crc32(spl_object_hash($v1)), $v1->hash());
    }
}
