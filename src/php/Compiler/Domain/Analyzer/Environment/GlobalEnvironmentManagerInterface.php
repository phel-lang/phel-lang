<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

/**
 * Manages the single `GlobalEnvironmentInterface` instance shared by the
 * compiler pipeline within a process. Owns the slot, the lifecycle, and
 * the lazy-construction policy.
 *
 * Application and Infrastructure both depend on this contract; the legacy
 * static accessor in `Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton`
 * is now a thin forwarder onto the same registry, preserved only for the
 * compiled-PHP ABI (the emitter writes literal references to it into
 * generated code).
 */
interface GlobalEnvironmentManagerInterface
{
    /**
     * Ensures the global environment is initialized. Returns the existing
     * instance when one is present; otherwise builds a new one (which also
     * clears the Phel runtime registry).
     */
    public function initialize(): GlobalEnvironmentInterface;

    /**
     * Drops the global environment. Next access lazily creates a fresh one.
     */
    public function reset(): void;

    public function isInitialized(): bool;

    /**
     * Returns the singleton, lazy-constructing a fresh `GlobalEnvironment`
     * when none exists — without clearing the Phel registry. This keeps
     * compiled artefacts usable when required outside a full compiler
     * bootstrap.
     */
    public function getInstance(): GlobalEnvironmentInterface;

    /**
     * Builds a new global environment, clearing the Phel runtime registry
     * and the require cache as a side effect. Returns the new instance.
     */
    public function initializeNew(): GlobalEnvironmentInterface;

    /**
     * Replaces the singleton with a previously captured environment. Used
     * to restore state after a transient operation that needed a clean
     * environment (e.g. documentation/completion loading).
     */
    public function setInstance(GlobalEnvironmentInterface $env): void;
}
