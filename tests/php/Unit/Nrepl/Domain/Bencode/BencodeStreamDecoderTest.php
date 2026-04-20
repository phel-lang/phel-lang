<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Bencode;

use Phel\Nrepl\Domain\Bencode\BencodeStreamDecoder;
use PHPUnit\Framework\TestCase;

final class BencodeStreamDecoderTest extends TestCase
{
    public function test_it_returns_nothing_until_a_frame_is_complete(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('d2:op');
        self::assertSame([], $stream->drain());
    }

    public function test_it_drains_a_complete_frame(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('d2:op4:evale');
        self::assertSame([['op' => 'eval']], $stream->drain());
        self::assertSame('', $stream->buffer());
    }

    public function test_it_splits_multiple_framed_messages(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('d2:op5:cloneed2:op5:closee');

        $drained = $stream->drain();

        self::assertCount(2, $drained);
        self::assertSame(['op' => 'clone'], $drained[0]);
        self::assertSame(['op' => 'close'], $drained[1]);
    }

    public function test_it_keeps_trailing_partial_data_buffered(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('d2:op5:cloneed2:op');

        $drained = $stream->drain();

        self::assertCount(1, $drained);
        self::assertSame('d2:op', $stream->buffer());

        $stream->feed('4:evale');
        $drained2 = $stream->drain();
        self::assertCount(1, $drained2);
        self::assertSame(['op' => 'eval'], $drained2[0]);
        self::assertSame('', $stream->buffer());
    }
}
