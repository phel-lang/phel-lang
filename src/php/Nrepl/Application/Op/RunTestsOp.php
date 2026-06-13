<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\RunFacadeInterface;

use function preg_match;

/**
 * Runs tests from inside the nREPL session, delegating to `phel\repl/run-tests`
 * (a whole namespace) or `phel\repl/run-test` (a single test when the `var`
 * param is set). Editors bind this to "run namespace tests" / "run test under
 * cursor" shortcuts.
 */
final readonly class RunTestsOp implements OpHandlerInterface
{
    /**
     * Conservative allow-list for the namespace/test identifiers woven into
     * the evaluated form. Editors send plain symbols; rejecting anything else
     * keeps the op from evaluating arbitrary code.
     */
    private const string SYMBOL_PATTERN = '/^[A-Za-z0-9._\/*?!<>=+-]+$/';

    public function __construct(
        private RunFacadeInterface $runFacade,
        private EvalResultResponder $responder,
    ) {}

    public function name(): string
    {
        return 'run-tests';
    }

    public function handle(OpRequest $request): array
    {
        $ns = $request->stringParam('ns');
        if ($ns === '') {
            return [OpResponse::errorDone(
                $request,
                'Missing required "ns" param for run-tests op.',
                [OpStatus::EVAL_ERROR],
            )];
        }

        $var = $request->stringParam('var');
        $identifier = $var === '' ? $ns : $ns . '/' . $var;

        if (preg_match(self::SYMBOL_PATTERN, $identifier) !== 1) {
            return [OpResponse::errorDone(
                $request,
                'Invalid namespace or test name for run-tests op.',
                [OpStatus::EVAL_ERROR],
            )];
        }

        $code = $var === ''
            ? "(phel\\repl/run-tests '" . $ns . ')'
            : "(phel\\repl/run-test '" . $identifier . ')';

        $result = $this->runFacade->structuredEval($code, new CompileOptions());

        return $this->responder->respond($request, $result, 'run-tests failed.');
    }
}
