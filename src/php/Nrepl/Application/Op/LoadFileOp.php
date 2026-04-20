<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\EvalError;
use Phel\Shared\Facade\RunFacadeInterface;

use function sprintf;

final readonly class LoadFileOp implements OpHandlerInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private PrinterInterface $printer,
        private SessionRegistry $sessions,
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
                ['error', 'load-file-error', 'done'],
            )];
        }

        $options = new CompileOptions();
        $result = $this->runFacade->structuredEval($fileContent, $options);

        $responses = [];

        if ($result->output !== '') {
            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                ['out' => $result->output],
            );
        }

        if ($result->success) {
            $sessionObject = $request->session !== null ? $this->sessions->get($request->session) : null;
            $sessionObject?->recordValue($result->value);

            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                [
                    'ns' => $sessionObject?->namespace() ?? 'user',
                    'value' => $this->printer->print($result->value),
                ],
            );

            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                [],
                ['done'],
            );

            return $responses;
        }

        $error = $result->error;
        $message = $error instanceof EvalError ? $error->message : 'load-file failed.';
        $exClass = $error instanceof EvalError ? $error->exceptionClass : 'Error';

        $responses[] = OpResponse::build(
            $request->id,
            $request->session,
            [
                'ex' => $exClass,
                'err' => sprintf('%s (%s): %s', $exClass, $fileName, $message),
            ],
            ['eval-error'],
        );

        $responses[] = OpResponse::build(
            $request->id,
            $request->session,
            [],
            ['done'],
        );

        return $responses;
    }
}
