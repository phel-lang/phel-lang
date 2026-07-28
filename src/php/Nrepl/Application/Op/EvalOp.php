<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\RunFacadeInterface;

use function trim;

/**
 * @internal
 */
final readonly class EvalOp implements OpHandlerInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private EvalResultResponder $responder,
    ) {}

    public function name(): string
    {
        return 'eval';
    }

    public function handle(OpRequest $request): array
    {
        $code = $request->optionalStringParam('code');
        if ($code === null) {
            return [OpResponse::errorDone(
                $request,
                'Missing required "code" param for eval op.',
                [OpStatus::NO_CODE],
            )];
        }

        // Nothing to evaluate: editors send an empty init eval on connect to
        // prime their namespace state from the response (CIDER sets its
        // initial prompt from it), so the reply must still report the `ns`.
        if (trim($code) === '') {
            return $this->responder->respondEmptyCode($request);
        }

        // structuredEval compiles and evaluates the code in the compiler's
        // current namespace, which every session shares. CompileOptions is
        // left empty: there is currently no nREPL-specific tuning (no
        // optimization flags) to pass through.
        $result = $this->runFacade->structuredEval($code, new CompileOptions());

        return $this->responder->respond($request, $result, 'Evaluation failed.');
    }
}
