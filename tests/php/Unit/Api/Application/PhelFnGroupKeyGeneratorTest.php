<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhelFnGroupKeyGenerator;
use PHPUnit\Framework\TestCase;

final class PhelFnGroupKeyGeneratorTest extends TestCase
{
    public function test_it_returns_plain_name_unchanged(): void
    {
        $generator = new PhelFnGroupKeyGenerator();

        self::assertSame('map', $generator->generateGroupKey('phel\\core', 'map'));
    }

    public function test_it_lowercases_the_name(): void
    {
        $generator = new PhelFnGroupKeyGenerator();

        self::assertSame('foo', $generator->generateGroupKey('phel\\core', 'Foo'));
    }

    public function test_it_replaces_slash_with_dash(): void
    {
        $generator = new PhelFnGroupKeyGenerator();

        self::assertSame('foo-bar', $generator->generateGroupKey('phel\\core', 'foo/bar'));
    }

    public function test_it_strips_non_alphanumeric_characters(): void
    {
        $generator = new PhelFnGroupKeyGenerator();

        self::assertSame('', $generator->generateGroupKey('phel\\core', '+'));
    }

    public function test_it_trims_trailing_dash(): void
    {
        $generator = new PhelFnGroupKeyGenerator();

        self::assertSame('name', $generator->generateGroupKey('phel\\core', 'name-'));
    }

    public function test_it_ignores_the_namespace_argument(): void
    {
        $generator = new PhelFnGroupKeyGenerator();

        self::assertSame(
            $generator->generateGroupKey('', 'map'),
            $generator->generateGroupKey('completely-different', 'map'),
        );
    }
}
