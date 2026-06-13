<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * Reloads project namespaces whose source files changed since the last load
 * (or all of them when the `all` param is truthy), delegating to the
 * `phel\repl/reload!` / `phel\repl/reload-all!` helpers. Editors bind this to
 * a "reload changed namespaces" shortcut.
 */
final readonly class ReloadOp implements OpHandlerInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private EvalResultResponder $responder,
    ) {}

    public function name(): string
    {
        return 'reload';
    }

    public function handle(OpRequest $request): array
    {
        $all = $request->stringParam('all');
        $reloadFn = ($all === '1' || $all === 'true') ? 'reload-all!' : 'reload!';

        $result = $this->runFacade->structuredEval(
            '(phel\repl/' . $reloadFn . ')',
            new CompileOptions(),
        );

        return $this->responder->respond($request, $result, 'reload failed.');
    }
}
