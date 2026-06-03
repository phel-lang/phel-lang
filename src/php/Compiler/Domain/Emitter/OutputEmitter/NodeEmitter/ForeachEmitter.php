<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\IterableTarget;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Seq;
use Phel\Lang\Symbol;

use function assert;

final class ForeachEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ForeachNode);

        if (!$node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapPrefix(
                $node->getEnv(),
                $node->getStartSourceLocation(),
                new ByRefLocalCollector()->collect($node),
            );
        }

        // `Seq::toIterable` is only needed to coerce `nil` to `[]` and
        // unwrap strings; for nodes the emitter has already proven are
        // iterable, the adapter call is a no-op we skip.
        $listExpr = $node->getListExpr();
        $useAdapter = !IterableTarget::isIterable($listExpr);

        if ($useAdapter) {
            $this->outputEmitter->emitStr('foreach (\\' . Seq::class . '::toIterable(', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitStr('foreach (', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitNode($listExpr);

        $this->outputEmitter->emitStr($useAdapter ? ') as ' : ' as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol() instanceof Symbol) {
            $this->outputEmitter->emitPhpVariable($node->getKeySymbol());
            $this->outputEmitter->emitStr(' => ', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitPhpVariable($node->getValueSymbol());
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBodyExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        if (!$node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('return null;', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
            $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
