<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Session;

use Phel\Nrepl\Domain\Session\SessionRegistry;
use PHPUnit\Framework\TestCase;

final class SessionRegistryTest extends TestCase
{
    public function test_it_creates_new_session_with_unique_id(): void
    {
        $registry = new SessionRegistry();
        $a = $registry->create();
        $b = $registry->create();

        self::assertNotSame($a->id, $b->id);
        self::assertNotEmpty($a->id);
    }

    public function test_it_retrieves_created_session(): void
    {
        $registry = new SessionRegistry();
        $created = $registry->create();

        self::assertSame($created, $registry->get($created->id));
    }

    public function test_it_returns_null_for_unknown_id(): void
    {
        $registry = new SessionRegistry();

        self::assertNull($registry->get('does-not-exist'));
    }

    public function test_it_closes_existing_session(): void
    {
        $registry = new SessionRegistry();
        $session = $registry->create();

        self::assertTrue($registry->close($session->id));
        self::assertNull($registry->get($session->id));
    }

    public function test_it_returns_false_when_closing_unknown_session(): void
    {
        $registry = new SessionRegistry();

        self::assertFalse($registry->close('does-not-exist'));
    }

    public function test_session_tracks_namespace_and_last_value(): void
    {
        $registry = new SessionRegistry();
        $session = $registry->create();

        self::assertSame('user', $session->namespace());
        self::assertNull($session->lastValue());

        $session->setNamespace('phel.core');
        $session->recordValue(42);

        self::assertSame('phel.core', $session->namespace());
        self::assertSame(42, $session->lastValue());
    }

    public function test_session_keeps_a_ring_of_the_last_three_values(): void
    {
        $session = new SessionRegistry()->create();

        self::assertNull($session->value(1));

        $session->recordValue('a');
        self::assertSame('a', $session->value(1));
        self::assertNull($session->value(2));

        $session->recordValue('b');
        self::assertSame('b', $session->value(1));
        self::assertSame('a', $session->value(2));

        $session->recordValue('c');
        self::assertSame(['c', 'b', 'a'], [$session->value(1), $session->value(2), $session->value(3)]);

        // A fourth value rotates the oldest ('a') out of the ring.
        $session->recordValue('d');
        self::assertSame(['d', 'c', 'b'], [$session->value(1), $session->value(2), $session->value(3)]);
        self::assertNull($session->value(4));
        self::assertSame('d', $session->lastValue());
    }
}
