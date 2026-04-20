<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\TagRegistry;
use PHPUnit\Framework\TestCase;

final class TagRegistryTest extends TestCase
{
    private TagRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = TagRegistry::getInstance();
        $this->registry->clear();
    }

    protected function tearDown(): void
    {
        TagRegistry::getInstance()->clear();
    }

    public function test_it_returns_the_same_instance(): void
    {
        self::assertSame(TagRegistry::getInstance(), TagRegistry::getInstance());
    }

    public function test_it_starts_empty_after_clear(): void
    {
        self::assertSame([], $this->registry->tags());
        self::assertFalse($this->registry->has('foo'));
        self::assertNull($this->registry->get('foo'));
    }

    public function test_it_registers_and_looks_up_a_handler(): void
    {
        $handler = static fn(mixed $form): string => 'got:' . $form;

        $this->registry->register('foo', $handler);

        self::assertTrue($this->registry->has('foo'));
        self::assertSame($handler, $this->registry->get('foo'));
    }

    public function test_it_last_registration_wins(): void
    {
        $first = static fn(mixed $_): int => 1;
        $second = static fn(mixed $_): int => 2;

        $this->registry->register('foo', $first);
        $this->registry->register('foo', $second);

        self::assertSame($second, $this->registry->get('foo'));
    }

    public function test_it_unregisters_a_handler(): void
    {
        $this->registry->register('foo', static fn(mixed $_): int => 1);
        $this->registry->unregister('foo');

        self::assertFalse($this->registry->has('foo'));
        self::assertNull($this->registry->get('foo'));
    }

    public function test_it_unregistering_missing_tag_is_a_noop(): void
    {
        $this->registry->unregister('does-not-exist');

        self::assertFalse($this->registry->has('does-not-exist'));
    }

    public function test_it_returns_sorted_tag_list(): void
    {
        $noop = static fn(mixed $_): mixed => null;
        $this->registry->register('zeta', $noop);
        $this->registry->register('alpha', $noop);
        $this->registry->register('mike', $noop);

        self::assertSame(['alpha', 'mike', 'zeta'], $this->registry->tags());
    }
}
