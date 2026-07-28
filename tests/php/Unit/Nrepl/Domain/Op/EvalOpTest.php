<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\EvalOp;
use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\CompileOptions;
use Phel\Shared\Eval\EvalError;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class EvalOpTest extends TestCase
{
    public function test_it_returns_value_and_done_on_success(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::success(3));

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('3');

        $registry = new SessionRegistry();
        $session = $registry->create();

        $op = $this->evalOp($run, $printer, $registry);
        $responses = $op->handle(new OpRequest('eval', 'r1', $session->id, [
            'op' => 'eval',
            'code' => '(+ 1 2)',
        ]));

        self::assertCount(2, $responses);
        self::assertSame('3', $responses[0]->payload['value']);
        self::assertSame('user', $responses[0]->payload['ns']);
        self::assertSame('3', $responses[0]->payload['*1']);
        self::assertContains('done', $responses[1]->payload['status']);
        self::assertSame(3, $session->lastValue());
    }

    public function test_it_emits_stdout_before_value(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::success(null, 'side effect'));

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('nil');

        $registry = new SessionRegistry();
        $op = $this->evalOp($run, $printer, $registry);
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => '(println "x")',
        ]));

        self::assertCount(3, $responses);
        self::assertSame('side effect', $responses[0]->payload['out']);
        self::assertSame('nil', $responses[1]->payload['value']);
        self::assertContains('done', $responses[2]->payload['status']);
    }

    public function test_it_reports_error_on_failure(): void
    {
        $error = new EvalError(
            exceptionClass: 'CompilerException',
            message: 'unbound symbol',
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

        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::failure($error));

        $printer = $this->createStub(PrinterInterface::class);

        $registry = new SessionRegistry();
        $op = $this->evalOp($run, $printer, $registry);
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => 'xx',
        ]));

        self::assertCount(2, $responses);
        self::assertSame('CompilerException', $responses[0]->payload['ex']);
        self::assertSame('CompilerException', $responses[0]->payload['root-ex']);
        self::assertStringContainsString('unbound symbol', (string) $responses[0]->payload['err']);
        self::assertContains('eval-error', $responses[0]->payload['status']);
        self::assertContains('done', $responses[1]->payload['status']);
    }

    public function test_it_falls_back_to_generic_error_when_no_eval_error_is_attached(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        // Failure without a concrete EvalError: the responder must still produce a frame.
        $run->method('structuredEval')->willReturn(EvalResult::incomplete());

        $registry = new SessionRegistry();
        $op = $this->evalOp($run, registry: $registry);
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => '(+ 1',
        ]));

        self::assertCount(1, $responses);
        self::assertContains('incomplete', $responses[0]->payload['status']);
    }

    public function test_it_reports_incomplete_form(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::incomplete());

        $op = $this->evalOp($run);
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => '(+ 1',
        ]));

        self::assertContains('incomplete', $responses[0]->payload['status']);
    }

    public function test_it_rejects_missing_code_param(): void
    {
        $op = $this->evalOp($this->createStub(RunFacadeInterface::class));
        $responses = $op->handle(new OpRequest('eval', 'r1', null, ['op' => 'eval']));

        self::assertContains('no-code', $responses[0]->payload['status']);
        self::assertContains('done', $responses[0]->payload['status']);
    }

    public function test_it_rejects_a_non_string_code_param(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::never())->method('structuredEval');

        $op = $this->evalOp($run);
        $responses = $op->handle(new OpRequest('eval', 'r1', null, ['op' => 'eval', 'code' => 42]));

        self::assertContains('no-code', $responses[0]->payload['status']);
    }

    public function test_it_treats_empty_code_as_a_no_op_reporting_the_current_namespace(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::never())->method('structuredEval');

        $registry = new SessionRegistry();
        $session = $registry->create();

        $op = $this->evalOp($run, registry: $registry);
        $responses = $op->handle(new OpRequest('eval', 'r1', $session->id, [
            'op' => 'eval',
            'code' => '',
        ]));

        self::assertCount(1, $responses);
        self::assertSame('user', $responses[0]->payload['ns']);
        self::assertContains('done', $responses[0]->payload['status']);
        self::assertNotContains('error', $responses[0]->payload['status']);
    }

    public function test_it_treats_whitespace_only_code_as_a_no_op(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::never())->method('structuredEval');

        $op = $this->evalOp($run);
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => "  \n ",
        ]));

        self::assertCount(1, $responses);
        self::assertContains('done', $responses[0]->payload['status']);
    }

    public function test_it_passes_compile_options(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::once())
            ->method('structuredEval')
            ->with('(+ 1 2)', self::isInstanceOf(CompileOptions::class))
            ->willReturn(EvalResult::success(3));

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('3');

        $op = $this->evalOp($run, $printer);
        $op->handle(new OpRequest('eval', 'r1', null, ['op' => 'eval', 'code' => '(+ 1 2)']));
    }

    /**
     * The op and its responder share one namespace binder, as they do in
     * production: the op binds the session's namespace before evaluating and
     * the responder reads the result back out of the same place.
     *
     * The responder is exercised on its own in `EvalResultResponderTest`; here
     * it only has to translate results, so the binder's compiler facade stays
     * a stub with no global environment (namespaces fall back to `user`).
     */
    private function evalOp(
        RunFacadeInterface $run,
        ?PrinterInterface $printer = null,
        ?SessionRegistry $registry = null,
    ): EvalOp {
        $binder = new SessionNamespaceBinder(
            $this->createStub(CompilerFacadeInterface::class),
            $registry ?? new SessionRegistry(),
        );

        return new EvalOp(
            $run,
            new EvalResultResponder($printer ?? $this->createStub(PrinterInterface::class), $binder),
            $binder,
        );
    }
}
