<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Emitter\OutputEmitter\SourceMap;

use Phel\Transpiler\Domain\Emitter\OutputEmitter\SourceMap\VLQ;
use PHPUnit\Framework\TestCase;

final class VLQTest extends TestCase
{
    public function test_encode1(): void
    {
        self::assertSame('AAAA', $this->encode([0, 0, 0, 0]));
    }

    public function test_encode2(): void
    {
        self::assertSame('AAgBC', $this->encode([0, 0, 16, 1]));
    }

    public function test_encode3(): void
    {
        self::assertSame('D', $this->encode([-1]));
    }

    public function test_encode4(): void
    {
        self::assertSame('B', $this->encode([-2_147_483_648]));
    }

    public function test_encode5(): void
    {
        self::assertSame('+/////D', $this->encode([2_147_483_647]));
    }

    public function test_decode1(): void
    {
        self::assertSame([0, 0, 0, 0], $this->decode('AAAA'));
    }

    public function test_decode2(): void
    {
        self::assertSame([0, 0, 16, 1], $this->decode('AAgBC'));
    }

    public function test_decode3(): void
    {
        self::assertSame([-1], $this->decode('D'));
    }

    public function test_decode4(): void
    {
        self::assertSame([-2_147_483_648], $this->decode('B'));
    }

    public function test_decode5(): void
    {
        self::assertSame([2_147_483_647], $this->decode('+/////D'));
    }

    private function encode(array $xs): string
    {
        return (new VLQ())->encodeIntegers($xs);
    }

    private function decode(string $s): array
    {
        return (new VLQ())->decode($s);
    }
}
