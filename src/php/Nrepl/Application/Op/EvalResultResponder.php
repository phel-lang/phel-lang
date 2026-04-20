<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\EvalError;
use Phel\Run\Domain\Repl\EvalResult;

use function sprintf;

/**
 * Translates an `EvalResult` into a list of nREPL response frames.
 * Shared between `EvalOp` and `LoadFileOp`, which only differ in:
 *   - the source of the code being evaluated;
 *   - the wording of the final error message.
 */
final readonly class EvalResultResponder
{
    public function __construct(
        private PrinterInterface $printer,
        private SessionRegistry $sessions,
    ) {}

    /**
     * @return list<OpResponse>
     */
    public function respond(
        OpRequest $request,
        EvalResult $result,
        string $errorFallbackMessage,
        ?string $fileName = null,
    ): array {
        $responses = [];

        if ($result->output !== '') {
            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                ['out' => $result->output],
            );
        }

        if ($result->success) {
            $session = $this->sessionFor($request);
            $session?->recordValue($result->value);

            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                [
                    'ns' => $session instanceof Session ? $session->namespace() : 'user',
                    'value' => $this->printer->print($result->value),
                ],
            );

            $responses[] = $this->doneFrame($request);

            return $responses;
        }

        if ($result->incomplete) {
            $responses[] = OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Incomplete form: unfinished parser input.'],
                [OpStatus::ERROR, OpStatus::INCOMPLETE, OpStatus::DONE],
            );

            return $responses;
        }

        $responses[] = $this->errorFrame($request, $result->error, $errorFallbackMessage, $fileName);
        $responses[] = $this->doneFrame($request);

        return $responses;
    }

    private function sessionFor(OpRequest $request): ?Session
    {
        return $request->session !== null
            ? $this->sessions->get($request->session)
            : null;
    }

    private function errorFrame(
        OpRequest $request,
        ?EvalError $error,
        string $fallbackMessage,
        ?string $fileName,
    ): OpResponse {
        $message = $error instanceof EvalError ? $error->message : $fallbackMessage;
        $exClass = $error instanceof EvalError ? $error->exceptionClass : 'Error';

        $body = [
            'ex' => $exClass,
            'err' => $fileName === null
                ? sprintf('%s: %s', $exClass, $message)
                : sprintf('%s (%s): %s', $exClass, $fileName, $message),
        ];

        if ($fileName === null) {
            // Preserve the existing `root-ex` field that EvalOp emitted.
            $body['root-ex'] = $exClass;
        }

        return OpResponse::build(
            $request->id,
            $request->session,
            $body,
            [OpStatus::EVAL_ERROR],
        );
    }

    private function doneFrame(OpRequest $request): OpResponse
    {
        return OpResponse::build(
            $request->id,
            $request->session,
            [],
            [OpStatus::DONE],
        );
    }
}
