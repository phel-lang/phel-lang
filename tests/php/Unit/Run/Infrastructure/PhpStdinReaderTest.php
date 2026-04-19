<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure;

use Phel\Run\Infrastructure\PhpStdinReader;
use PHPUnit\Framework\TestCase;

final class PhpStdinReaderTest extends TestCase
{
    public function test_reads_contents_from_injected_stream(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        fwrite($stream, "(+ 1 2)\n(println :ok)");
        rewind($stream);

        $reader = new PhpStdinReader($stream);

        self::assertSame("(+ 1 2)\n(println :ok)", $reader->read());
    }

    public function test_returns_empty_string_for_empty_stream(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $reader = new PhpStdinReader($stream);

        self::assertSame('', $reader->read());
    }
}
