<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @internal
 */
final readonly class LoadFileOp implements OpHandlerInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private EvalResultResponder $responder,
        private SessionNamespaceBinder $namespaceBinder,
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
            return [OpResponse::errorDone(
                $request,
                'Missing required "file" param for load-file op.',
                [OpStatus::LOAD_FILE_ERROR],
            )];
        }

        // A file without its own `(ns ...)` header loads into the ambient
        // namespace, so it has to be this session's, not another's.
        $this->namespaceBinder->bind($request);

        $result = $this->runFacade->structuredEval($fileContent, new CompileOptions());

        return $this->responder->respond($request, $result, 'load-file failed.', $fileName);
    }
}
