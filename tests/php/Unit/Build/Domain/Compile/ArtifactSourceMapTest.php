<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\ArtifactSourceMap;
use Phel\Shared\SourceMap\SourceMapConsumer;
use Phel\Shared\SourceMap\VLQ;
use PHPUnit\Framework\TestCase;

final class ArtifactSourceMapTest extends TestCase
{
    public function test_generated_columns_follow_replacements_without_changing_source_locations(): void
    {
        $vlq = new VLQ();
        $mapping = implode(',', [
            $vlq->encodeIntegers([0, 0, 0, 0]),
            $vlq->encodeIntegers([15, 0, 0, 5]),
            $vlq->encodeIntegers([35, 0, 2, 1]),
        ]);

        $rewritten = ArtifactSourceMap::rewrite($mapping, [
            2 => [['column' => 10, 'length' => 10, 'replacementLength' => 30]],
        ]);
        $segments = explode(',', $rewritten);

        self::assertSame([0, 0, 0, 0], $vlq->decode($segments[0]));
        self::assertSame([10, 0, 0, 5], $vlq->decode($segments[1]));
        self::assertSame([60, 0, 2, 1], $vlq->decode($segments[2]));
        self::assertSame(new SourceMapConsumer($mapping)->getMappedLines(), new SourceMapConsumer($rewritten)->getMappedLines());
    }
}
