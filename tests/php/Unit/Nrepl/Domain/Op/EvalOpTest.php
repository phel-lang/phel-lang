<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Nrepl\Application\Op\EvalOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\EvalError;
use Phel\Run\Domain\Repl\EvalResult;
use Phel\Shared\Facade\RunFacadeInterface;
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

        $op = new EvalOp($run, $printer, $registry);
        $responses = $op->handle(new OpRequest('eval', 'r1', $session->id, [
            'op' => 'eval',
            'code' => '(+ 1 2)',
        ]));

        self::assertCount(2, $responses);
        self::assertSame('3', $responses[0]->payload['value']);
        self::assertSame('user', $responses[0]->payload['ns']);
        self::assertContains('done', $responses[1]->payload['status']);
        self::assertSame(3, $session->lastValue());
    }

    public function test_it_emits_stdout_before_value(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::success(null, 'side effect'));

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('nil');

        $op = new EvalOp($run, $printer, new SessionRegistry());
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

        $op = new EvalOp($run, $printer, new SessionRegistry());
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => 'xx',
        ]));

        self::assertCount(2, $responses);
        self::assertSame('CompilerException', $responses[0]->payload['ex']);
        self::assertContains('eval-error', $responses[0]->payload['status']);
        self::assertContains('done', $responses[1]->payload['status']);
    }

    public function test_it_reports_incomplete_form(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::incomplete());

        $op = new EvalOp($run, $this->createStub(PrinterInterface::class), new SessionRegistry());
        $responses = $op->handle(new OpRequest('eval', 'r1', null, [
            'op' => 'eval',
            'code' => '(+ 1',
        ]));

        self::assertContains('incomplete', $responses[0]->payload['status']);
    }

    public function test_it_rejects_missing_code_param(): void
    {
        $op = new EvalOp(
            $this->createStub(RunFacadeInterface::class),
            $this->createStub(PrinterInterface::class),
            new SessionRegistry(),
        );
        $responses = $op->handle(new OpRequest('eval', 'r1', null, ['op' => 'eval']));

        self::assertContains('eval-error', $responses[0]->payload['status']);
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

        $op = new EvalOp($run, $printer, new SessionRegistry());
        $op->handle(new OpRequest('eval', 'r1', null, ['op' => 'eval', 'code' => '(+ 1 2)']));
    }
}
