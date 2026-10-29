<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Bencode;

use Phel\Nrepl\Domain\Bencode\BencodeException;
use Phel\Nrepl\Domain\Bencode\BencodeStreamDecoder;
use PHPUnit\Framework\TestCase;

final class BencodeStreamDecoderTest extends TestCase
{
    public function test_repeated_malformed_chunks_exhaust_the_receive_quota(): void
    {
        $stream = new BencodeStreamDecoder();
        for ($i = 0; $i < 16; ++$i) {
            $stream->feed(str_repeat('x', 1024 * 1024));
            try {
                $stream->drain();
                self::fail('Malformed input must be rejected');
            } catch (BencodeException $exception) {
                self::assertFalse($exception->isBufferLimitExceeded());
            }
        }

        $this->expectException(BencodeException::class);
        $this->expectExceptionMessage('buffer');
        $stream->feed('x');
    }

    public function test_a_valid_message_resets_the_receive_quota(): void
    {
        $stream = new BencodeStreamDecoder();
        for ($i = 0; $i < 3; ++$i) {
            $stream->feed(str_repeat('x', 8 * 1024 * 1024));
            try {
                $stream->drain();
                self::fail('Malformed input must be rejected');
            } catch (BencodeException $exception) {
                self::assertFalse($exception->isBufferLimitExceeded());
            }

            $stream->feed('d2:op5:clonee');
            self::assertSame([['op' => 'clone']], $stream->drain());
        }
    }

    public function test_repeated_idle_drains_keep_a_partial_frame_until_more_input_arrives(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('d2:op');
        for ($i = 0; $i < 3; ++$i) {
            $stream->feed('');
            self::assertSame([], $stream->drain());
            self::assertSame('d2:op', $stream->buffer());
        }

        $stream->feed('5:clonee');
        self::assertSame([['op' => 'clone']], $stream->drain());
    }

    public function test_it_delivers_complete_messages_before_rejecting_a_bad_suffix(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('d2:op5:cloneex');
        self::assertSame([['op' => 'clone']], $stream->drain());
        self::assertSame('', $stream->buffer());
        $this->expectException(BencodeException::class);
        $stream->drain();
    }

    public function test_it_rejects_malformed_input_and_resets_the_buffer(): void
    {
        $stream = new BencodeStreamDecoder();
        $stream->feed('x');
        try {
            $stream->drain();
            self::fail('Malformed input must be rejected');
        } catch (BencodeException) {
            self::assertSame('', $stream->buffer());
        }

        $stream->feed('d2:op5:clonee');
        self::assertSame([['op' => 'clone']], $stream->drain());
    }

    public function test_it_rejects_an_oversized_pending_frame(): void
    {
        $stream = new BencodeStreamDecoder();
        $this->expectException(BencodeException::class);
        $this->expectExceptionMessage('buffer');
        $stream->feed('33554432:' . str_repeat('a', 16 * 1024 * 1024));
    }

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
