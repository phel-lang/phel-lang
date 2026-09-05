<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;

/**
 * @internal
 */
interface FileEmitterInterface
{
    public function startFile(string $source): void;

    public function emitNode(AbstractNode $node): void;

    /**
     * Emits into the file as {@see self::emitNode} does, and returns that one
     * form's emission as its own result when it is byte-identical to what a
     * statement emission would produce, or `null` when it is not.
     *
     * A non-null result lets the caller evaluate what was already emitted
     * instead of emitting the same form a second time.
     */
    public function emitNodeCapturing(AbstractNode $node, bool $enableSourceMaps): ?EmitterResult;

    public function endFile(bool $enableSourceMaps): EmitterResult;
}
