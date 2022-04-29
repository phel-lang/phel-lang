<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

use function assert;

final class PhpNewEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpNewNode);

        $this->emitPhpNewBegin($node);
        $this->emitPhpNewArgs($node);
        $this->emitPhpNewEnd($node);
    }

    private function emitPhpNewBegin(PhpNewNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $classExpr = $node->getClassExpr();

        if ($classExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('(new ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($classExpr);
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($classExpr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

            $this->outputEmitter->emitStr('return new $' . $targetSym->getName() . '(', $node->getStartSourceLocation());
        }
    }

    private function emitPhpNewArgs(PhpNewNode $node): void
    {
        $this->outputEmitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());
    }

    private function emitPhpNewEnd(PhpNewNode $node): void
    {
        $classExpr = $node->getClassExpr();

        if ($classExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('))', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitStr(');', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
