<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\SourceMap;

use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use PHPUnit\Framework\TestCase;

final class SourceMapGeneratorTest extends TestCase
{
    public function test_encode_empty(): void
    {
        self::assertSame('', new SourceMapGenerator()->encode([]));
    }

    public function test_encode_single_mapping(): void
    {
        $mappings = [
            ['generated' => ['line' => 0, 'column' => 0], 'original' => ['line' => 0, 'column' => 0]],
        ];

        self::assertSame('AAAA', new SourceMapGenerator()->encode($mappings));
    }

    public function test_encode_two_mappings_same_line_emits_comma(): void
    {
        $mappings = [
            ['generated' => ['line' => 0, 'column' => 0], 'original' => ['line' => 0, 'column' => 0]],
            ['generated' => ['line' => 0, 'column' => 5], 'original' => ['line' => 0, 'column' => 5]],
        ];

        self::assertSame('AAAA,KAAK', new SourceMapGenerator()->encode($mappings));
    }

    public function test_encode_new_generated_line_emits_semicolon(): void
    {
        $mappings = [
            ['generated' => ['line' => 0, 'column' => 0], 'original' => ['line' => 0, 'column' => 0]],
            ['generated' => ['line' => 0, 'column' => 5], 'original' => ['line' => 0, 'column' => 5]],
            ['generated' => ['line' => 1, 'column' => 2], 'original' => ['line' => 1, 'column' => 3]],
        ];

        self::assertSame('AAAA,KAAK;EACF', new SourceMapGenerator()->encode($mappings));
    }

    public function test_encode_skips_duplicate_generated_position(): void
    {
        $mappings = [
            ['generated' => ['line' => 0, 'column' => 0], 'original' => ['line' => 0, 'column' => 0]],
            ['generated' => ['line' => 0, 'column' => 0], 'original' => ['line' => 0, 'column' => 0]],
        ];

        self::assertSame('AAAA', new SourceMapGenerator()->encode($mappings));
    }
}
