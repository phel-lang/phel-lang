<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Domain\Op\OpRequest;
use PHPUnit\Framework\TestCase;

final class OpRequestTest extends TestCase
{
    public function test_from_message_reads_routing_metadata(): void
    {
        $request = OpRequest::fromMessage([
            'op' => 'eval',
            'id' => 'req-1',
            'session' => 'sess-1',
            'code' => '(+ 1 2)',
        ]);

        self::assertSame('eval', $request->op);
        self::assertSame('req-1', $request->id);
        self::assertSame('sess-1', $request->session);
        self::assertSame('(+ 1 2)', $request->stringParam('code'));
    }

    public function test_from_message_defaults_non_string_routing_metadata(): void
    {
        $request = OpRequest::fromMessage(['op' => 42, 'id' => 7, 'session' => []]);

        self::assertSame('', $request->op);
        self::assertNull($request->id);
        self::assertNull($request->session);
    }

    public function test_string_param_falls_back_to_the_default(): void
    {
        $request = new OpRequest('load-file', null, null, ['file-name' => 3]);

        self::assertSame('NO_SOURCE_FILE', $request->stringParam('file-name', 'NO_SOURCE_FILE'));
        self::assertSame('', $request->stringParam('missing'));
    }

    public function test_optional_string_param_separates_absent_from_empty(): void
    {
        $request = new OpRequest('eval', null, null, ['code' => '']);

        self::assertSame('', $request->optionalStringParam('code'));
        self::assertNull($request->optionalStringParam('file'));
    }

    public function test_optional_string_param_rejects_non_strings(): void
    {
        $request = new OpRequest('eval', null, null, ['code' => 42]);

        self::assertNull($request->optionalStringParam('code'));
    }
}
