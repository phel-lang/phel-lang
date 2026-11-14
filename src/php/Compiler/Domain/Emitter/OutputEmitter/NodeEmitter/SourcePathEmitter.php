<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\SourcePathNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\SourceMap\SourcePathMarker;

use function assert;

/**
 * @internal
 */
final class SourcePathEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof SourcePathNode);
        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return;
        }

        $location = $node->getStartSourceLocation();
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $location);
        if (!$this->outputEmitter->getOptions()->isStatementEmitMode()
            && $location instanceof SourceLocation && $node->getValue() !== null && $node->getValue() !== ''
        ) {
            $this->outputEmitter->emitStr(SourcePathMarker::encode($location->getFile(), $node->isDirectory()), $location);
        }

        $this->outputEmitter->emitLiteral($node->getValue());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $location);
    }
}
