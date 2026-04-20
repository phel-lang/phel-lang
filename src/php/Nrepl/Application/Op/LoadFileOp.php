<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Shared\Facade\RunFacadeInterface;

final readonly class LoadFileOp implements OpHandlerInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private EvalResultResponder $responder,
    ) {}

    public function name(): string
    {
        return 'load-file';
    }

    public function handle(OpRequest $request): array
    {
        $fileContent = $request->stringParam('file');
        $fileName = $request->stringParam('file-name', 'NO_SOURCE_FILE');

        if ($fileContent === '') {
            return [OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Missing required "file" param for load-file op.'],
                [OpStatus::ERROR, OpStatus::LOAD_FILE_ERROR, OpStatus::DONE],
            )];
        }

        $result = $this->runFacade->structuredEval($fileContent, new CompileOptions());

        return $this->responder->respond($request, $result, 'load-file failed.', $fileName);
    }
}
