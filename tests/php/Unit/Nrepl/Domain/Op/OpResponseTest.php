<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use PHPUnit\Framework\TestCase;

final class OpResponseTest extends TestCase
{
    public function test_build_skips_empty_id_and_session(): void
    {
        $response = OpResponse::build(null, null, ['value' => 1]);

        self::assertSame(['value' => 1], $response->payload);
    }

    public function test_build_skips_empty_strings(): void
    {
        $response = OpResponse::build('', '', ['value' => 1]);

        self::assertSame(['value' => 1], $response->payload);
    }

    public function test_build_includes_status_only_when_non_empty(): void
    {
        $withStatus = OpResponse::build('id-1', 'sess', [], ['done']);
        $withoutStatus = OpResponse::build('id-1', 'sess', []);

        self::assertSame(['done'], $withStatus->payload['status']);
        self::assertArrayNotHasKey('status', $withoutStatus->payload);
    }

    public function test_for_request_forwards_id_and_session(): void
    {
        $request = new OpRequest('eval', 'req-42', 'sess-7', []);
        $response = OpResponse::forRequest($request, ['k' => 'v']);

        self::assertSame('req-42', $response->payload['id']);
        self::assertSame('sess-7', $response->payload['session']);
        self::assertSame('v', $response->payload['k']);
    }

    public function test_done_produces_terminal_frame(): void
    {
        $request = new OpRequest('eval', 'req-1', 'sess', []);
        $response = OpResponse::done($request);

        self::assertSame([OpStatus::DONE], $response->payload['status']);
        self::assertSame('req-1', $response->payload['id']);
        self::assertSame('sess', $response->payload['session']);
    }

    public function test_error_done_prepends_error_and_appends_done(): void
    {
        $request = new OpRequest('eval', 'req-1', null, []);
        $response = OpResponse::errorDone($request, 'boom', [OpStatus::EVAL_ERROR]);

        self::assertSame('boom', $response->payload['message']);
        self::assertSame(
            [OpStatus::ERROR, OpStatus::EVAL_ERROR, OpStatus::DONE],
            $response->payload['status'],
        );
    }

    public function test_error_done_accepts_empty_extra_status(): void
    {
        $request = new OpRequest('eval', null, null, []);
        $response = OpResponse::errorDone($request, 'nope');

        self::assertSame([OpStatus::ERROR, OpStatus::DONE], $response->payload['status']);
    }

    public function test_with_extra_merges_additional_payload(): void
    {
        $response = OpResponse::build('x', 'y', ['a' => 1])->withExtra(['b' => 2]);

        self::assertSame(1, $response->payload['a']);
        self::assertSame(2, $response->payload['b']);
    }
}
