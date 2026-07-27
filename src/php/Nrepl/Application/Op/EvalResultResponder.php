<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Eval\EvalError;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Munge;
use Phel\Shared\Printer\PrinterInterface;

use function sprintf;

/**
 * Translates an `EvalResult` into a list of nREPL response frames.
 * Shared between `EvalOp` and `LoadFileOp`, which only differ in:
 *   - the source of the code being evaluated;
 *   - the wording of the final error message.
 *
 * @internal
 */
final readonly class EvalResultResponder
{
    public function __construct(
        private PrinterInterface $printer,
        private SessionRegistry $sessions,
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param OpRequest  $request              the nREPL request context (carries id/session routing)
     * @param EvalResult $result               the evaluated result produced by RunFacade
     * @param string     $errorFallbackMessage error text used when the failure carries no EvalError object
     * @param ?string    $fileName             optional source file name, woven into the error framing for load-file ops
     *
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
            $responses[] = OpResponse::forRequest($request, ['out' => $result->output]);
        }

        $session = $this->sessionFor($request);
        $ns = $this->resolveNamespace($session);

        if ($result->success) {
            $session?->recordValue($result->value);

            $responses[] = OpResponse::forRequest($request, $this->successPayload($session, $ns, $result->value));
            $responses[] = OpResponse::done($request);

            return $responses;
        }

        if ($result->incomplete) {
            $responses[] = OpResponse::errorDone(
                $request,
                'Incomplete form: unfinished parser input.',
                [OpStatus::INCOMPLETE],
            );

            return $responses;
        }

        $responses[] = $this->errorFrame($request, $result->error, $errorFallbackMessage, $fileName, $ns);
        $responses[] = OpResponse::done($request);

        return $responses;
    }

    /**
     * Reply to an eval request carrying empty code (nothing to evaluate):
     * a single done frame that still reports the current namespace. Clients
     * prime their namespace state from the first eval response on connect —
     * CIDER emits its initial prompt from it — so the `ns` must be present
     * even though there is no value. Mirrors the reference nREPL, where
     * empty code reads as EOF and only a done frame comes back.
     *
     * @return list<OpResponse>
     */
    public function respondEmptyCode(OpRequest $request): array
    {
        $ns = $this->resolveNamespace($this->sessionFor($request));

        return [OpResponse::forRequest($request, ['ns' => $ns], [OpStatus::DONE])];
    }

    private function sessionFor(OpRequest $request): ?Session
    {
        return $request->session !== null
            ? $this->sessions->get($request->session)
            : null;
    }

    /**
     * The namespace an eval ran in: the compiler's current namespace, mirrored
     * into the session so the `ns` field of eval responses tracks `ns`/`in-ns`
     * forms as they evaluate — editor prompts (CIDER, Calva, ...) are driven
     * by that field. This is the same source of truth the terminal REPL prompt
     * reads. A failed or incomplete eval restores the environment snapshot, so
     * after one this simply yields the pre-eval namespace.
     */
    private function resolveNamespace(?Session $session): string
    {
        if ($this->compilerFacade->isGlobalEnvironmentInitialized()) {
            $ns = Munge::displayNs($this->compilerFacade->getGlobalEnvironment()->getNs());
            if ($ns !== '') {
                $session?->setNamespace($ns);

                return $ns;
            }
        }

        return $session instanceof Session ? $session->namespace() : 'user';
    }

    /**
     * @return array<string, string>
     */
    private function successPayload(?Session $session, string $ns, mixed $value): array
    {
        $payload = [
            'ns' => $ns,
            'value' => $this->printer->print($value),
        ];

        // *1/*2/*3 exist only within a session; a session-less eval has no history.
        if ($session instanceof Session) {
            $payload['*1'] = $this->printer->print($session->value(1));
            $payload['*2'] = $this->printer->print($session->value(2));
            $payload['*3'] = $this->printer->print($session->value(3));
        }

        return $payload;
    }

    private function errorFrame(
        OpRequest $request,
        ?EvalError $error,
        string $fallbackMessage,
        ?string $fileName,
        string $ns,
    ): OpResponse {
        $message = $error instanceof EvalError ? $error->message : $fallbackMessage;
        $exClass = $error instanceof EvalError ? $error->exceptionClass : 'Error';

        $body = [
            // Clients track the namespace from every eval response frame;
            // without it a failed eval (e.g. CIDER's Clojure-only init code)
            // would leave the client's prompt namespace unset.
            'ns' => $ns,
            'ex' => $exClass,
            'err' => $fileName === null
                ? sprintf('%s: %s', $exClass, $message)
                : sprintf('%s (%s): %s', $exClass, $fileName, $message),
        ];

        if ($fileName === null) {
            // Preserve the existing `root-ex` field that EvalOp emitted.
            $body['root-ex'] = $exClass;
        }

        return OpResponse::forRequest($request, $body, [OpStatus::EVAL_ERROR]);
    }
}
