<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

/**
 * The emit mode, plus a record of whether asking about it changed anything.
 *
 * A form is emitted twice: once into the file (the artifact) and once as a
 * statement (what the evaluator runs). For most forms the two are the same
 * bytes, and the second emission is pure waste. "Most" is not something to
 * guess at from node types, so it is observed instead: every mode predicate
 * reports whether its answer under the active mode differs from its answer
 * under {@see EmitMode::Statement}. If a form's emission never got a different
 * answer, its file emission is byte-identical to the statement one and can be
 * evaluated directly.
 *
 * This self-maintains. A new `isFileEmitMode()` branch in some emitter is
 * accounted for the moment it asks, without anyone remembering to add its node
 * type to a list.
 *
 * @internal
 */
final class OutputEmitterOptions
{
    private bool $divergedFromStatementMode = false;

    public function __construct(
        private readonly EmitMode $emitMode = EmitMode::Statement,
    ) {}

    public function isFileEmitMode(): bool
    {
        return $this->record($this->emitMode === EmitMode::File, false);
    }

    public function isStatementEmitMode(): bool
    {
        return $this->record($this->emitMode === EmitMode::Statement, true);
    }

    public function isCacheEmitMode(): bool
    {
        return $this->record($this->emitMode === EmitMode::Cache, false);
    }

    public function resetDivergenceRecord(): void
    {
        $this->divergedFromStatementMode = false;
    }

    /**
     * True when the emission since the last reset asked a mode question whose
     * answer differs from the one statement mode would have given, so its
     * output cannot be assumed to match a statement emission.
     */
    public function hasDivergedFromStatementMode(): bool
    {
        return $this->divergedFromStatementMode;
    }

    /**
     * @param bool $actual         the answer under the active mode
     * @param bool $underStatement the answer the same question has in statement mode
     */
    private function record(bool $actual, bool $underStatement): bool
    {
        if ($actual !== $underStatement) {
            $this->divergedFromStatementMode = true;
        }

        return $actual;
    }
}
