<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function is_string;
use function str_starts_with;

final class PhpNewEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpNewNode);

        $classExpr = $node->getClassExpr();
        $staticClassName = $this->staticClassName($classExpr);

        if ($staticClassName !== null) {
            $this->emitStaticClassNew($node, $staticClassName);
            return;
        }

        $this->emitDynamicNew($node, $classExpr);
    }

    /**
     * Returns the absolute PHP class name when the class expression is known at
     * compile time (a resolved class-name node or a literal string), or null
     * when the class is only known at runtime (a variable, call, ...).
     */
    private function staticClassName(AbstractNode $classExpr): ?string
    {
        if ($classExpr instanceof PhpClassNameNode) {
            return $classExpr->getAbsolutePhpName();
        }

        if ($classExpr instanceof LiteralNode && is_string($classExpr->getValue())) {
            return $this->absoluteClassName($classExpr->getValue());
        }

        return null;
    }

    /**
     * Forces an absolute (global-namespace) class name so direct `new <name>`
     * emission matches the runtime semantics of `new $string`, which always
     * resolves from the global namespace and never prepends the current one.
     */
    private function absoluteClassName(string $className): string
    {
        if (str_starts_with($className, '\\')) {
            return $className;
        }

        return '\\' . $className;
    }

    /**
     * Emits `(new <Class>(...))` directly: no `target_` temp, no guard, no IIFE.
     */
    private function emitStaticClassNew(PhpNewNode $node, string $className): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitStr('(new ', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($className, $node->getClassExpr()->getStartSourceLocation());
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('))', $node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * Emits a runtime-class construction with a `target_` temp and an
     * `is_string()/is_object()` guard. The construction is wrapped in an IIFE
     * only in expression context; in statement/return context the temp, guard
     * and `new` are emitted as plain statements.
     */
    private function emitDynamicNew(PhpNewNode $node, AbstractNode $classExpr): void
    {
        $isExpr = $node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION);

        if ($isExpr) {
            $this->outputEmitter->emitFnWrapPrefix(
                $node->getEnv(),
                $node->getStartSourceLocation(),
                new ByRefLocalCollector()->collect($node),
            );
        }

        $targetSym = Symbol::gen('target_');
        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($classExpr);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $targetVar = '$' . $targetSym->getName();
        $this->outputEmitter->emitStr(
            'if (!is_string(' . $targetVar . ') && !is_object(' . $targetVar . ')) {',
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr(
            'throw new \InvalidArgumentException(sprintf("php/new expects a class name or object, %s given (%s)", get_debug_type(' . $targetVar . '), var_export(' . $targetVar . ', true)));',
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());

        if ($isExpr) {
            $this->outputEmitter->emitStr('return new ' . $targetVar . '(', $node->getStartSourceLocation());
            $this->outputEmitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(');', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
            $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
            return;
        }

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new ' . $targetVar . '(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
