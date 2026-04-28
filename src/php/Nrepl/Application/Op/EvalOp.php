<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Shared\Facade\RunFacadeInterface;

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
        $code = $request->stringParam('code');
        if ($code === '') {
            return [OpResponse::errorDone(
                $request,
                'Missing required "code" param for eval op.',
                [OpStatus::EVAL_ERROR],
            )];
        }

        $result = $this->runFacade->structuredEval($code, new CompileOptions());

        return $this->responder->respond($request, $result, 'Evaluation failed.');
    }
}
