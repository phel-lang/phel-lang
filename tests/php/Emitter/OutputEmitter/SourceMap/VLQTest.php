<?php

declare(strict_types=1);

namespace PhelTest\Emitter\OutputEmitter\SoxurceMap;

use Phel\Emitter\OutputEmitter\SourceMap\VLQ;
use PHPUnit\Framework\TestCase;

final class VLQTest extends TestCase
{
    public function testEncode1(): void
    {
        $this->assertEquals('AAAA', $this->encode([0, 0, 0, 0]));
    }

    public function testEncode2(): void
    {
        $this->assertEquals('AAgBC', $this->encode([0, 0, 16, 1]));
    }

    public function testEncode3(): void
    {
        $this->assertEquals('D', $this->encode([-1]));
    }

    public function testEncode4(): void
    {
        $this->assertEquals('B', $this->encode([-2147483648]));
    }

    public function testEncode5(): void
    {
        $this->assertEquals('+/////D', $this->encode([2147483647]));
    }

    public function testDecode1(): void
    {
        $this->assertEquals([0, 0, 0, 0], $this->decode('AAAA'));
    }

    public function testDecode2(): void
    {
        $this->assertEquals([0, 0, 16, 1], $this->decode('AAgBC'));
    }

    public function testDecode3(): void
    {
        $this->assertEquals([-1], $this->decode('D'));
    }

    public function testDecode4(): void
    {
        $this->assertEquals([-2147483648], $this->decode('B'));
    }

    public function testDecode5(): void
    {
        $this->assertEquals([2147483647], $this->decode('+/////D'));
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
