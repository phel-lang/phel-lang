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

final readonly class EvalOp implements OpHandlerInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private PrinterInterface $printer,
        private SessionRegistry $sessions,
    ) {}

    public function name(): string
    {
        return 'eval';
    }

    public function handle(OpRequest $request): array
    {
        $code = $request->stringParam('code');
        if ($code === '') {
            return [OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Missing required "code" param for eval op.'],
                ['error', 'eval-error', 'done'],
            )];
        }

        $result = $this->runFacade->structuredEval($code, new CompileOptions());
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

        if ($result->incomplete) {
            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Incomplete form: unfinished parser input.'],
                ['error', 'incomplete', 'done'],
            );

            return $responses;
        }

        $error = $result->error;
        $message = $error instanceof EvalError ? $error->message : 'Evaluation failed.';
        $exClass = $error instanceof EvalError ? $error->exceptionClass : 'Error';

        $responses[] = OpResponse::build(
            $request->id,
            $request->session,
            [
                'ex' => $exClass,
                'root-ex' => $exClass,
                'err' => sprintf('%s: %s', $exClass, $message),
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
