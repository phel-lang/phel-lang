<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Application\Op\LoadFileOp;
use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Eval\EvalError;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class LoadFileOpTest extends TestCase
{
    public function test_it_evaluates_file_content(): void
    {
        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('structuredEval')->willReturn(EvalResult::success(42));

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('42');

        $op = $this->loadFileOp($run, $printer);
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
        $op = $this->loadFileOp($this->createStub(RunFacadeInterface::class));
        $responses = $op->handle(new OpRequest('load-file', 'r1', null, ['op' => 'load-file']));

        self::assertContains('load-file-error', $responses[0]->payload['status']);
    }

    public function test_op_name_is_load_file(): void
    {
        $op = $this->loadFileOp($this->createStub(RunFacadeInterface::class));
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

        $op = $this->loadFileOp($run);
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

    /**
     * The op and its responder share one namespace binder, as they do in
     * production: the op binds the session's namespace before evaluating and
     * the responder reads the result back out of the same place.
     *
     * The responder is exercised on its own in `EvalResultResponderTest`; here
     * it only has to translate results, so the binder's compiler facade stays
     * a stub with no global environment (namespaces fall back to `user`).
     */
    private function loadFileOp(RunFacadeInterface $run, ?PrinterInterface $printer = null): LoadFileOp
    {
        $binder = new SessionNamespaceBinder(
            $this->createStub(CompilerFacadeInterface::class),
            new SessionRegistry(),
        );

        return new LoadFileOp(
            $run,
            new EvalResultResponder($printer ?? $this->createStub(PrinterInterface::class), $binder),
            $binder,
        );
    }
}
