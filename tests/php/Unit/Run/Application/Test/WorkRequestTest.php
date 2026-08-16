<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\FrameKey;
use Phel\Run\Application\Test\WorkRequest;
use PHPUnit\Framework\TestCase;

final class WorkRequestTest extends TestCase
{
    public function test_decodes_a_well_formed_frame(): void
    {
        $request = WorkRequest::fromFrame([
            'index' => 11,
            'ns' => 'phel.json.test',
            'file' => '/abs/path/json.phel',
            'options' => '{:filter nil}',
        ]);

        self::assertSame(11, $request->index);
        self::assertSame('phel.json.test', $request->ns);
        self::assertSame('/abs/path/json.phel', $request->file);
        self::assertSame('{:filter nil}', $request->options);
    }

    public function test_supplies_safe_defaults_for_missing_fields(): void
    {
        $request = WorkRequest::fromFrame([]);

        self::assertSame(-1, $request->index);
        self::assertSame('', $request->ns);
        self::assertSame('', $request->file);
        self::assertSame('{}', $request->options);
        self::assertSame([], $request->loadOrder);
    }

    public function test_decodes_the_load_order_and_drops_malformed_entries(): void
    {
        $request = WorkRequest::fromFrame([
            'load-order' => [
                ['ns' => 'phel.core', 'file' => '/core.phel'],
                'not-an-entry',
                ['ns' => '', 'file' => '/empty-ns.phel'],
                ['ns' => 'app.t', 'file' => '/t.phel'],
            ],
        ]);

        self::assertSame(
            [
                ['ns' => 'phel.core', 'file' => '/core.phel'],
                ['ns' => 'app.t', 'file' => '/t.phel'],
            ],
            $request->loadOrder,
        );
    }

    public function test_base_response_echoes_index_and_ns_with_result_type(): void
    {
        $request = new WorkRequest(3, 'phel.x', '/x.phel', '{}');

        self::assertSame(
            [
                FrameKey::TYPE => FrameKey::TYPE_RESULT,
                FrameKey::INDEX => 3,
                FrameKey::NS => 'phel.x',
            ],
            $request->baseResponse(),
        );
    }
}
