<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Session;

use Phel;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Munge;

/**
 * Both directions of the one piece of evaluation state nREPL keeps per session:
 * the current namespace.
 *
 * Evaluation runs in a single process-wide `GlobalEnvironment` that every
 * session shares, so without this two editors on one server walk over each
 * other: client A evaluates `(ns foo)`, and client B's next eval compiles in
 * `foo` and reports `ns foo` back, moving its prompt with nobody having asked
 * (#2906). Reference nREPL binds `*ns*` per session, and `*1`/`*2`/`*3` are
 * already per-session here, which made the namespace the odd one out.
 *
 * Definitions stay shared either way: the registry is global, so a `def` in one
 * session is visible from the other. Only the *current* namespace is isolated.
 *
 * @internal
 */
final readonly class SessionNamespaceBinder
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private SessionRegistry $sessions,
    ) {}

    public function sessionFor(OpRequest $request): ?Session
    {
        return $request->session !== null
            ? $this->sessions->get($request->session)
            : null;
    }

    /**
     * Point the shared environment at the session's namespace, so the code
     * about to be evaluated compiles where that session left off rather than
     * wherever another session last went.
     *
     * A session-less request is left alone: it has no namespace of its own, so
     * the only sensible ambient namespace is the one already in place.
     *
     * The namespace can only be one this process created — a session starts at
     * `user` and moves only by evaluating an `ns`/`in-ns` form — so this never
     * points the environment at something that does not exist.
     *
     * "The current namespace" is two pieces of state, and both have to move
     * together or `*ns*` disagrees with where the code actually compiled:
     * the analyzer's `GlobalEnvironment::ns` and the runtime `phel.core/*ns*`
     * var. `NamespaceLoader::restoreStartupNamespace()` writes the same pair.
     */
    public function bind(OpRequest $request): void
    {
        $session = $this->sessionFor($request);
        if (!$session instanceof Session) {
            return;
        }

        if (!$this->compilerFacade->isGlobalEnvironmentInitialized()) {
            return;
        }

        $namespace = $session->namespace();
        $this->compilerFacade->getGlobalEnvironment()->setNs($namespace);
        Phel::setVar(CompilerConstants::PHEL_CORE_NAMESPACE, '*ns*', $namespace);
    }

    /**
     * Mirror the post-eval namespace back into the session and return it: the
     * `ns` field of eval responses then tracks `ns`/`in-ns` forms as they
     * evaluate, which is what drives editor prompts (CIDER, Calva, ...). This
     * is the same source of truth the terminal REPL prompt reads.
     *
     * A failed or incomplete eval restores the environment snapshot, so after
     * one this yields the pre-eval namespace and the session does not move.
     * Falls back to what the session already knows while the global
     * environment is still uninitialized.
     */
    public function sync(?Session $session): string
    {
        if ($this->compilerFacade->isGlobalEnvironmentInitialized()) {
            $ns = Munge::displayNs($this->compilerFacade->getGlobalEnvironment()->getNs());
            if ($ns !== '') {
                $session?->setNamespace($ns);

                return $ns;
            }
        }

        return $session instanceof Session ? $session->namespace() : Session::DEFAULT_NAMESPACE;
    }
}
