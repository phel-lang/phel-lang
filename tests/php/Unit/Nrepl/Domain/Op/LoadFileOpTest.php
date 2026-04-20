<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Application\Op\LoadFileOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\EvalError;
use Phel\Run\Domain\Repl\EvalResult;
use Phel\Shared\Facade\RunFacadeInterface;
use PHPUnit\Framework\TestCase;

final class LoadFileOpTest extends TestCase
{
    public function test_it_evaluates_file_content(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::success(42));

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('42');

        $op = new LoadFileOp($run, new EvalResultResponder($printer, new SessionRegistry()));
        $responses = $op->handle(new OpRequest('load-file', 'r1', null, [
            'op' => 'load-file',
            'file' => '(def x 42) x',
            'file-name' => 'x.phel',
        ]));

        self::assertCount(2, $responses);
        self::assertSame('42', $responses[0]->payload['value']);
        self::assertContains('done', $responses[1]->payload['status']);
    }

    public function test_it_rejects_missing_file_param(): void
    {
        $op = new LoadFileOp(
            $this->createStub(RunFacadeInterface::class),
            new EvalResultResponder($this->createStub(PrinterInterface::class), new SessionRegistry()),
        );
        $responses = $op->handle(new OpRequest('load-file', 'r1', null, ['op' => 'load-file']));

        self::assertContains('load-file-error', $responses[0]->payload['status']);
    }

    public function test_op_name_is_load_file(): void
    {
        $op = new LoadFileOp(
            $this->createStub(RunFacadeInterface::class),
            new EvalResultResponder($this->createStub(PrinterInterface::class), new SessionRegistry()),
        );
        self::assertSame('load-file', $op->name());
    }

    public function test_it_includes_file_name_in_error_message(): void
    {
        $error = new EvalError(
            exceptionClass: 'CompilerException',
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
        );

        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::failure($error));

        $op = new LoadFileOp(
            $run,
            new EvalResultResponder($this->createStub(PrinterInterface::class), new SessionRegistry()),
        );
        $responses = $op->handle(new OpRequest('load-file', 'r1', null, [
            'op' => 'load-file',
            'file' => '(broken form)',
            'file-name' => 'missing.phel',
        ]));

        self::assertSame('CompilerException', $responses[0]->payload['ex']);
        self::assertStringContainsString('(missing.phel)', (string) $responses[0]->payload['err']);
        self::assertContains('eval-error', $responses[0]->payload['status']);
        self::assertContains('done', $responses[1]->payload['status']);
    }
}
