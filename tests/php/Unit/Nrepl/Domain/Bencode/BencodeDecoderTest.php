<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Bencode;

use Phel\Nrepl\Domain\Bencode\BencodeDecoder;
use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Bencode\BencodeException;
use PHPUnit\Framework\TestCase;

final class BencodeDecoderTest extends TestCase
{
    private BencodeDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new BencodeDecoder();
    }

    public function test_it_decodes_zero(): void
    {
        self::assertSame(0, $this->decoder->decode('i0e'));
    }

    public function test_it_decodes_positive_integers(): void
    {
        self::assertSame(42, $this->decoder->decode('i42e'));
        self::assertSame(9999999, $this->decoder->decode('i9999999e'));
    }

    public function test_it_decodes_negative_integers(): void
    {
        self::assertSame(-1, $this->decoder->decode('i-1e'));
        self::assertSame(-123, $this->decoder->decode('i-123e'));
    }

    public function test_it_rejects_integers_with_leading_zero(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('i01e');
    }

    public function test_it_rejects_negative_zero(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('i-0e');
    }

    public function test_it_rejects_empty_integer(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('ie');
    }

    public function test_it_rejects_non_numeric_integer(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('ixe');
    }

    public function test_it_decodes_empty_string(): void
    {
        self::assertSame('', $this->decoder->decode('0:'));
    }

    public function test_it_decodes_ascii_strings(): void
    {
        self::assertSame('hello', $this->decoder->decode('5:hello'));
        self::assertSame('hello world', $this->decoder->decode('11:hello world'));
    }

    public function test_it_decodes_binary_strings(): void
    {
        $expected = "\x00\x01\xff";
        self::assertSame($expected, $this->decoder->decode('3:' . $expected));
    }

    public function test_it_decodes_empty_list(): void
    {
        self::assertSame([], $this->decoder->decode('le'));
    }

    public function test_it_decodes_flat_lists(): void
    {
        self::assertSame([1, 2, 3], $this->decoder->decode('li1ei2ei3ee'));
        self::assertSame(['spam', 42], $this->decoder->decode('l4:spami42ee'));
    }

    public function test_it_decodes_empty_dict(): void
    {
        self::assertSame([], $this->decoder->decode('de'));
    }

    public function test_it_decodes_dictionaries(): void
    {
        self::assertSame(
            ['code' => '(+ 1 2)', 'op' => 'eval'],
            $this->decoder->decode('d4:code7:(+ 1 2)2:op4:evale'),
        );
    }

    public function test_it_decodes_nested_structures(): void
    {
        $encoded = 'd2:id3:abc6:statusl4:doneee';
        self::assertSame(['id' => 'abc', 'status' => ['done']], $this->decoder->decode($encoded));
    }

    public function test_it_round_trips_deeply_nested_values(): void
    {
        $msg = [
            'id' => '42',
            'ops' => ['eval' => [], 'clone' => []],
            'list' => [1, 2, [3, 4]],
        ];

        $encoder = new BencodeEncoder();
        $encoded = $encoder->encode($msg);
        /** @var array<string, mixed> $decoded */
        $decoded = $this->decoder->decode($encoded);

        self::assertSame('42', $decoded['id']);
        self::assertSame([1, 2, [3, 4]], $decoded['list']);
        self::assertArrayHasKey('eval', $decoded['ops']);
    }

    public function test_it_rejects_trailing_bytes(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('i1eX');
    }

    public function test_it_rejects_unterminated_list(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('li1ei2e');
    }

    public function test_it_rejects_unterminated_dict(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('d3:key5:valu');
    }

    public function test_it_rejects_string_with_bad_length(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('10:short');
    }

    public function test_it_rejects_empty_input(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('');
    }

    public function test_it_rejects_unknown_token(): void
    {
        $this->expectException(BencodeException::class);
        $this->decoder->decode('x');
    }

    public function test_decode_with_length_returns_consumed_bytes(): void
    {
        [$value, $consumed] = $this->decoder->decodeWithLength('i42ei1e');
        self::assertSame(42, $value);
        self::assertSame(4, $consumed);
    }
}
