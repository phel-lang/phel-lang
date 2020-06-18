<?php

namespace Phel\SourceMap;

use PHPUnit\Framework\TestCase;

class VLQTest extends TestCase
{
    public function testEncode1()
    {
        $this->assertEquals('AAAA', $this->encode([0,0,0,0]));
    }

    public function testEncode2()
    {
        $this->assertEquals('AAgBC', $this->encode([0, 0, 16, 1]));
    }

    public function testEncode3()
    {
        $this->assertEquals('D', $this->encode([-1]));
    }

    public function testEncode4()
    {
        $this->assertEquals('B', $this->encode([-2147483648]));
    }

    public function testEncode5()
    {
        $this->assertEquals('+/////D', $this->encode([2147483647]));
    }

    public function testDecode1()
    {
        $this->assertEquals([0,0,0,0], $this->decode('AAAA'));
    }

    public function testDecode2()
    {
        $this->assertEquals([0, 0, 16, 1], $this->decode('AAgBC'));
    }

    public function testDecode3()
    {
        $this->assertEquals([-1], $this->decode('D'));
    }

    public function testDecode4()
    {
        $this->assertEquals([-2147483648], $this->decode('B'));
    }

    public function testDecode5()
    {
        $this->assertEquals([2147483647], $this->decode('+/////D'));
    }

    public function encode(array $xs)
    {
        $service = new VLQ();
        return $service->encodeIntegers($xs);
    }

    public function decode(string $s)
    {
        $service = new VLQ();
        return $service->decode($s);
    }
}
