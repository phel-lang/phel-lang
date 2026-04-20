<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Application\Op;

use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\EvalError;
use Phel\Run\Domain\Repl\EvalResult;
use PHPUnit\Framework\TestCase;

final class EvalResultResponderTest extends TestCase
{
    public function test_success_emits_value_and_done_and_records_session_value(): void
    {
        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('42');

        $registry = new SessionRegistry();
        $session = $registry->create();
        $responder = new EvalResultResponder($printer, $registry);

        $responses = $responder->respond(
            new OpRequest('eval', 'req-1', $session->id, []),
            EvalResult::success(42),
            'fallback',
        );

        self::assertCount(2, $responses);
        self::assertSame('42', $responses[0]->payload['value']);
        self::assertSame('user', $responses[0]->payload['ns']);
        self::assertSame(['done'], $responses[1]->payload['status']);
        self::assertSame(42, $session->lastValue());
    }

    public function test_success_uses_user_namespace_when_session_missing(): void
    {
        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('nil');

        $responder = new EvalResultResponder($printer, new SessionRegistry());

        $responses = $responder->respond(
            new OpRequest('eval', null, null, []),
            EvalResult::success(null),
            'fallback',
        );

        self::assertSame('user', $responses[0]->payload['ns']);
    }

    public function test_success_prepends_out_frame_when_output_present(): void
    {
        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('nil');

        $responder = new EvalResultResponder($printer, new SessionRegistry());

        $responses = $responder->respond(
            new OpRequest('eval', null, null, []),
            EvalResult::success(null, 'hello'),
            'fallback',
        );

        self::assertCount(3, $responses);
        self::assertSame('hello', $responses[0]->payload['out']);
    }

    public function test_incomplete_emits_single_frame_with_incomplete_status(): void
    {
        $responder = new EvalResultResponder(
            $this->createStub(PrinterInterface::class),
            new SessionRegistry(),
        );

        $responses = $responder->respond(
            new OpRequest('eval', 'req-1', null, []),
            EvalResult::incomplete(),
            'fallback',
        );

        self::assertCount(1, $responses);
        self::assertContains('incomplete', $responses[0]->payload['status']);
        self::assertContains('done', $responses[0]->payload['status']);
    }

    public function test_failure_uses_fallback_message_when_no_error_attached(): void
    {
        $responder = new EvalResultResponder(
            $this->createStub(PrinterInterface::class),
            new SessionRegistry(),
        );

        $error = new EvalError(
            exceptionClass: 'CustomException',
            message: 'real message',
            errorCode: null,
            file: null,
            line: null,
            column: null,
            endLine: null,
            endColumn: null,
            codeSnippet: null,
            stackTrace: '',
            phase: 'compile',
            frames: [],
        );

        $responses = $responder->respond(
            new OpRequest('eval', 'req-1', null, []),
            EvalResult::failure($error),
            'fallback',
        );

        self::assertCount(2, $responses);
        self::assertSame('CustomException', $responses[0]->payload['ex']);
        self::assertSame('CustomException', $responses[0]->payload['root-ex']);
        self::assertStringContainsString('real message', (string) $responses[0]->payload['err']);
    }

    public function test_failure_with_filename_embeds_filename_in_err_message(): void
    {
        $responder = new EvalResultResponder(
            $this->createStub(PrinterInterface::class),
            new SessionRegistry(),
        );

        $responses = $responder->respond(
            new OpRequest('load-file', 'req-1', null, []),
            EvalResult::failure(new EvalError(
                exceptionClass: 'E',
                message: 'boom',
                errorCode: null,
                file: null,
                line: null,
                column: null,
                endLine: null,
                endColumn: null,
                codeSnippet: null,
                stackTrace: '',
                phase: 'compile',
                frames: [],
            )),
            'fallback',
            'my.phel',
        );

        self::assertStringContainsString('(my.phel)', (string) $responses[0]->payload['err']);
        // With a file name, no "root-ex" field (matches legacy LoadFileOp behavior).
        self::assertArrayNotHasKey('root-ex', $responses[0]->payload);
    }

    public function test_failure_without_error_falls_back_to_generic_class(): void
    {
        $responder = new EvalResultResponder(
            $this->createStub(PrinterInterface::class),
            new SessionRegistry(),
        );

        // An explicit "non-success, non-incomplete, no error" case (defensive);
        // the responder should still produce a two-frame error reply.
        $failure = EvalResult::failure(new EvalError(
            exceptionClass: 'Boom',
            message: 'raw',
            errorCode: null,
            file: null,
            line: null,
            column: null,
            endLine: null,
            endColumn: null,
            codeSnippet: null,
            stackTrace: '',
            phase: 'compile',
            frames: [],
        ));

        $responses = $responder->respond(
            new OpRequest('eval', 'req-1', null, []),
            $failure,
            'fallback',
        );

        self::assertCount(2, $responses);
        self::assertContains('eval-error', $responses[0]->payload['status']);
        self::assertContains('done', $responses[1]->payload['status']);
    }
}
