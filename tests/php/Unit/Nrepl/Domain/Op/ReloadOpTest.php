<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Application\Op\ReloadOp;
use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\CompileOptions;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class ReloadOpTest extends TestCase
{
    public function test_it_evaluates_reload_with_a_dotted_namespace(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::once())
            ->method('structuredEval')
            ->with('(phel.repl/reload!)', self::isInstanceOf(CompileOptions::class))
            ->willReturn(EvalResult::success(null));

        $this->reloadOp($run)->handle(new OpRequest('reload', 'r1', null, ['op' => 'reload']));
    }

    public function test_it_evaluates_reload_all_with_a_dotted_namespace(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::once())
            ->method('structuredEval')
            ->with('(phel.repl/reload-all!)', self::isInstanceOf(CompileOptions::class))
            ->willReturn(EvalResult::success(null));

        $this->reloadOp($run)->handle(new OpRequest('reload', 'r1', null, [
            'op' => 'reload',
            'all' => 'true',
        ]));
    }

    private function reloadOp(RunFacadeInterface $run): ReloadOp
    {
        $binder = new SessionNamespaceBinder(
            $this->createStub(CompilerFacadeInterface::class),
            new SessionRegistry(),
        );

        return new ReloadOp(
            $run,
            new EvalResultResponder($this->createStub(PrinterInterface::class), $binder),
        );
    }
}
