<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain;

use Phel\Command\Domain\NamespacePathTransformer;
use PHPUnit\Framework\TestCase;

final class NamespacePathTransformerTest extends TestCase
{
    public function test_empty_string(): void
    {
        $transformer = new NamespacePathTransformer('');

        self::assertSame('', $transformer->getOutputMainNamespacePath());
    }

    public function test_simple_string(): void
    {
        $transformer = new NamespacePathTransformer('test');

        self::assertSame('test', $transformer->getOutputMainNamespacePath());
    }

    public function test_invert_slash_to_forward_slash(): void
    {
        $transformer = new NamespacePathTransformer('test\ns');

        self::assertSame('test/ns', $transformer->getOutputMainNamespacePath());
    }

    public function test_dash_to_underscore(): void
    {
        $transformer = new NamespacePathTransformer('test-ns');

        self::assertSame('test_ns', $transformer->getOutputMainNamespacePath());
    }

    public function test_dash_and_invert_slash(): void
    {
        $transformer = new NamespacePathTransformer('test-ns\hello');

        self::assertSame('test_ns/hello', $transformer->getOutputMainNamespacePath());
    }
}
