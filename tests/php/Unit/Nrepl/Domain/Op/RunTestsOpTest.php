<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Application\Op\RunTestsOp;
use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\CompileOptions;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class RunTestsOpTest extends TestCase
{
    public function test_it_evaluates_a_namespace_with_a_dotted_internal_namespace(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::once())
            ->method('structuredEval')
            ->with("(phel.repl/run-tests 'app.test)", self::isInstanceOf(CompileOptions::class))
            ->willReturn(EvalResult::success(null));

        $this->runTestsOp($run)->handle(new OpRequest('run-tests', 'r1', null, [
            'op' => 'run-tests',
            'ns' => 'app.test',
        ]));
    }

    public function test_it_evaluates_one_test_with_a_dotted_internal_namespace(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::once())
            ->method('structuredEval')
            ->with("(phel.repl/run-test 'app.test/works)", self::isInstanceOf(CompileOptions::class))
            ->willReturn(EvalResult::success(null));

        $this->runTestsOp($run)->handle(new OpRequest('run-tests', 'r1', null, [
            'op' => 'run-tests',
            'ns' => 'app.test',
            'var' => 'works',
        ]));
    }

    private function runTestsOp(RunFacadeInterface $run): RunTestsOp
    {
        $binder = new SessionNamespaceBinder(
            $this->createStub(CompilerFacadeInterface::class),
            new SessionRegistry(),
        );

        return new RunTestsOp(
            $run,
            new EvalResultResponder($this->createStub(PrinterInterface::class), $binder),
        );
    }
}
