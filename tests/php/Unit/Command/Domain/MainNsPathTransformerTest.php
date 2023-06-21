<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain;

use Phel\Command\Domain\MainNsPathTransformer;
use PHPUnit\Framework\TestCase;

final class MainNsPathTransformerTest extends TestCase
{
    public function test_empty_string(): void
    {
        $transformer = new MainNsPathTransformer('');

        self::assertSame('', $transformer->getOutputMainNsPath());
    }

    public function test_simple_string(): void
    {
        $transformer = new MainNsPathTransformer('test');

        self::assertSame('test', $transformer->getOutputMainNsPath());
    }

    public function test_invert_slash_to_forward_slash(): void
    {
        $transformer = new MainNsPathTransformer('test\ns');

        self::assertSame('test/ns', $transformer->getOutputMainNsPath());
    }

    public function test_dash_to_underscore(): void
    {
        $transformer = new MainNsPathTransformer('test-ns');

        self::assertSame('test_ns', $transformer->getOutputMainNsPath());
    }

    public function test_dash_and_invert_slash(): void
    {
        $transformer = new MainNsPathTransformer('test-ns\hello');

        self::assertSame('test_ns/hello', $transformer->getOutputMainNsPath());
    }
}
