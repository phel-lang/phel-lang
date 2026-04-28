<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Bencode;

use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Bencode\BencodeException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class BencodeEncoderTest extends TestCase
{
    private BencodeEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new BencodeEncoder();
    }

    public function test_it_encodes_positive_integers(): void
    {
        self::assertSame('i0e', $this->encoder->encode(0));
        self::assertSame('i42e', $this->encoder->encode(42));
        self::assertSame('i9999999999e', $this->encoder->encode(9999999999));
    }

    public function test_it_encodes_negative_integers(): void
    {
        self::assertSame('i-1e', $this->encoder->encode(-1));
        self::assertSame('i-123e', $this->encoder->encode(-123));
    }

    public function test_it_encodes_booleans_as_integers(): void
    {
        self::assertSame('i1e', $this->encoder->encode(true));
        self::assertSame('i0e', $this->encoder->encode(false));
    }

    public function test_it_encodes_ascii_strings_with_byte_length_prefix(): void
    {
        self::assertSame('0:', $this->encoder->encode(''));
        self::assertSame('5:hello', $this->encoder->encode('hello'));
        self::assertSame('11:hello world', $this->encoder->encode('hello world'));
    }

    public function test_it_encodes_binary_strings_by_byte_length(): void
    {
        $binary = "\x00\x01\xff";
        self::assertSame('3:' . $binary, $this->encoder->encode($binary));
    }

    public function test_it_encodes_empty_array_as_list(): void
    {
        self::assertSame('le', $this->encoder->encode([]));
    }

    public function test_it_encodes_lists_of_mixed_scalars(): void
    {
        self::assertSame('li1ei2ei3ee', $this->encoder->encode([1, 2, 3]));
        self::assertSame('l4:spami42ee', $this->encoder->encode(['spam', 42]));
    }

    public function test_it_encodes_dictionaries_with_sorted_keys(): void
    {
        $dict = ['op' => 'eval', 'code' => '(+ 1 2)'];
        self::assertSame('d4:code7:(+ 1 2)2:op4:evale', $this->encoder->encode($dict));
    }

    public function test_it_sorts_dictionary_keys_lexicographically(): void
    {
        $dict = ['zebra' => 1, 'apple' => 2, 'mango' => 3];
        self::assertSame(
            'd5:applei2e5:mangoi3e5:zebrai1ee',
            $this->encoder->encode($dict),
        );
    }

    public function test_it_encodes_nested_dictionaries_and_lists(): void
    {
        $msg = [
            'id' => 'abc',
            'status' => ['done'],
            'value' => [
                'kind' => 'int',
                'data' => [1, 2],
            ],
        ];
        $encoded = $this->encoder->encode($msg);
        self::assertSame(
            'd2:id3:abc6:statusl4:donee5:valued4:datali1ei2ee4:kind3:intee',
            $encoded,
        );
    }

    public function test_it_rejects_non_string_dict_keys(): void
    {
        $this->expectException(BencodeException::class);
        $this->encoder->encode([0 => 'x', 'a' => 'y']);
    }

    public function test_it_rejects_unsupported_types(): void
    {
        $this->expectException(BencodeException::class);
        $this->encoder->encode(new stdClass());
    }

    public function test_it_rejects_floats(): void
    {
        $this->expectException(BencodeException::class);
        $this->encoder->encode(1.5);
    }

    public function test_it_rejects_null_values(): void
    {
        $this->expectException(BencodeException::class);
        $this->encoder->encode(null);
    }
}
