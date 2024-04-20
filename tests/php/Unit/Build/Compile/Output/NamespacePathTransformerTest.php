<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Compile\Output;

use Phel\Build\Domain\Builder\Output\NamespacePathTransformer;
use PHPUnit\Framework\TestCase;

final class NamespacePathTransformerTest extends TestCase
{
    private NamespacePathTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new NamespacePathTransformer();
    }

    public function test_empty_string(): void
    {
        self::assertSame(
            '',
            $this->transformer->transform(''),
        );
    }

    public function test_simple_string(): void
    {
        self::assertSame(
            'test',
            $this->transformer->transform('test'),
        );
    }

    public function test_invert_slash_to_forward_slash(): void
    {
        self::assertSame(
            'test/ns',
            $this->transformer->transform('test\ns'),
        );
    }

    public function test_dash_to_underscore(): void
    {
        self::assertSame(
            'test_ns',
            $this->transformer->transform('test-ns'),
        );
    }

    public function test_dash_and_invert_slash(): void
    {
        self::assertSame(
            'test_ns/hello',
            $this->transformer->transform('test-ns\hello'),
        );
    }
}
