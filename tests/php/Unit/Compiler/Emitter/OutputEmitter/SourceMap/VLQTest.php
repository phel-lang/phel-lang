<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\SourceMap;

use Phel\Compiler\Emitter\OutputEmitter\SourceMap\VLQ;
use PHPUnit\Framework\TestCase;

final class VLQTest extends TestCase
{
    public function test_encode1(): void
    {
        self::assertEquals('AAAA', $this->encode([0, 0, 0, 0]));
    }

    public function test_encode2(): void
    {
        self::assertEquals('AAgBC', $this->encode([0, 0, 16, 1]));
    }

    public function test_encode3(): void
    {
        self::assertEquals('D', $this->encode([-1]));
    }

    public function test_encode4(): void
    {
        self::assertEquals('B', $this->encode([-2147483648]));
    }

    public function test_encode5(): void
    {
        self::assertEquals('+/////D', $this->encode([2147483647]));
    }

    public function test_decode1(): void
    {
        self::assertEquals([0, 0, 0, 0], $this->decode('AAAA'));
    }

    public function test_decode2(): void
    {
        self::assertEquals([0, 0, 16, 1], $this->decode('AAgBC'));
    }

    public function test_decode3(): void
    {
        self::assertEquals([-1], $this->decode('D'));
    }

    public function test_decode4(): void
    {
        self::assertEquals([-2147483648], $this->decode('B'));
    }

    public function test_decode5(): void
    {
        self::assertEquals([2147483647], $this->decode('+/////D'));
    }

    private function encode(array $xs): string
    {
        $service = new VLQ();

        return $service->encodeIntegers($xs);
    }

    private function decode(string $s): array
    {
        $service = new VLQ();

        return $service->decode($s);
    }
}
