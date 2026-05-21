<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\BodyConstantScanner;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\BuildConstants;
use Phel\Shared\CompilerConstants;

use function count;
use function implode;
use function is_string;

final readonly class MethodEmitter
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private ClosureEmitterHelper $closureHelper,
        private BodyConstantScanner $constantScanner = new BodyConstantScanner(),
    ) {}

    public function emit(string $methodName, FnNode $node): void
    {
        $this->emitMethodBegin($methodName, $node);
        $this->emitMethodParameterList($node);
        $this->outputEmitter->emitLine(')' . $this->returnTypeSuffix($node) . ' {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->emitMethodParametersExtraction($node);
        $this->emitSelfNameBinding($node);
        $this->emitMethodVariadicParameters($node);
        $this->emitBodyWithConstantScope($node);
        $this->emitMethodEnd($node);
    }

    /**
     * Returns `: <type>` when the fn carries an explicit return type tag,
     * otherwise an empty string. Used to splice the return-type declaration
     * into a PHP signature between the closing paren and the opening brace.
     */
    public function returnTypeSuffix(FnNode $node): string
    {
        $type = $node->getReturnType();
        return $type === null ? '' : ': ' . $type;
    }

    /**
     * Emits just the parameter list (without surrounding parens or braces).
     * Used by FnAsClassEmitter for both class and closure emission paths.
     */
    public function emitParameters(FnNode $node): void
    {
        $paramsCount = count($node->getParams());

        foreach ($node->getParams() as $i => $symbol) {
            $isVariadicTail = $i === $paramsCount - 1 && $node->isVariadic();
            $meta = $symbol->getMeta();
            $tag = $this->extractTypeTag($meta);
            if ($tag !== null) {
                $this->outputEmitter->emitStr($tag . ' ', $node->getStartSourceLocation());
            }

            if ($isVariadicTail) {
                $this->outputEmitter->emitPhpVariable($symbol, $loc = null, $asReference = false, $isVariadic = true);
            } else {
                $isReference = $this->isByRef($meta);
                $this->outputEmitter->emitPhpVariable($symbol, $loc = null, $isReference);
            }

            if ($i < $paramsCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
    }

    /**
     * Emits the function body: variadic wrapping, recur loop, and the body node.
     * Used by FnAsClassEmitter for the closure emission path.
     */
    public function emitBody(FnNode $node): void
    {
        if ($node->isMultiArityChild()) {
            $this->emitSelfNameBinding($node);
        }

        $this->emitMethodVariadicParameters($node);
        $this->emitBodyWithConstantScope($node);
    }

    /**
     * Wraps {@see emitMethodBody()} with a {@see ConstantScope} so the
     * emitters for {@see VectorNode}, {@see MapNode}, and {@see SetNode}
     * can hoist pure literals to per-fn `static` variables, and the
     * {@see CallEmitter} can hoist global-fn call-site lookups to per-fn
     * `static $__phel_call_N` slots when build mode is active.
     */
    private function emitBodyWithConstantScope(FnNode $node): void
    {
        $scope = new ConstantScope();
        $this->constantScanner->scan($node->getBody(), $scope, $this->shouldCacheCallSites());
        $this->outputEmitter->pushConstantScope($scope);

        try {
            $this->emitConstantStatics($scope, $node);
            $this->emitMethodBody($node);
        } finally {
            $this->outputEmitter->popConstantScope();
        }
    }

    /**
     * Read `*build-mode*` straight from the registry to avoid a hard
     * compile-time dependency from the emitter onto `BuildFacade`.
     * Caching call-site resolutions is only safe when redefinitions are
     * not expected, which is exactly the contract of build mode.
     */
    private function shouldCacheCallSites(): bool
    {
        return Registry::getInstance()->getDefinition(
            CompilerConstants::PHEL_CORE_NAMESPACE,
            BuildConstants::BUILD_MODE,
        ) === true;
    }

    private function emitConstantStatics(ConstantScope $scope, FnNode $node): void
    {
        $parts = [];

        for ($i = 0, $n = $scope->count(); $i < $n; ++$i) {
            $parts[] = '$__phel_const_' . $i;
        }

        for ($i = 0, $n = $scope->callSlotCount(); $i < $n; ++$i) {
            $parts[] = '$__phel_call_' . $i;
        }

        if ($parts === []) {
            return;
        }

        $this->outputEmitter->emitLine(
            'static ' . implode(', ', $parts) . ';',
            $node->getStartSourceLocation(),
        );
    }

    private function extractTypeTag(mixed $meta): ?string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            $tag = $tag->getName();
        }

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    /**
     * `^:by-ref` (and the historical `^:reference` alias used inside
     * `phel.core`) compiles the param to PHP `&$param` so mutations via
     * `php/aset`, `php/array_push`, etc. propagate back to the caller's
     * binding. Surfaces the `(php/array)` mutation pattern at the Phel
     * level without forcing buffer-return-rebind dances.
     */
    private function isByRef(mixed $meta): bool
    {
        if (!$meta instanceof PersistentMapInterface) {
            return false;
        }

        if ($meta->find(Keyword::create('by-ref')) === true) {
            return true;
        }

        return $meta->find(Keyword::create('reference')) === true;
    }

    /**
     * For named fns compiled as invokable classes, bind the fn's own name to
     * `$this` at the top of the method body so self-recursive references
     * resolve to the class instance (which is callable via __invoke).
     */
    private function emitSelfNameBinding(FnNode $node): void
    {
        $name = $node->getName();
        if (!$name instanceof Symbol) {
            return;
        }

        $varName = $this->outputEmitter->mungeEncode($name->getName());

        $this->outputEmitter->emitLine(
            '$' . $varName . ' = $this;',
            $node->getStartSourceLocation(),
        );
    }

    private function emitMethodBegin(string $methodName, FnNode $node): void
    {
        $this->outputEmitter->emitStr('public function ' . $this->outputEmitter->mungeEncode($methodName) . '(', $node->getStartSourceLocation());
    }

    private function emitMethodParameterList(FnNode $node): void
    {
        $this->emitParameters($node);
    }

    private function emitMethodParametersExtraction(FnNode $node): void
    {
        foreach ($node->getUses() as $use) {
            $varName = $this->munge($this->closureHelper->normalizeUse($use, $node->getEnv()));

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = $this->' . $varName . ';',
                $node->getStartSourceLocation(),
            );
        }
    }

    private function emitMethodVariadicParameters(FnNode $node): void
    {
        if ($node->isVariadic()) {
            $p = $node->getParams()[count($node->getParams()) - 1];
            $varName = $this->munge($p);

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = \Phel::vector($' . $varName . ');',
                $node->getStartSourceLocation(),
            );
        }
    }

    private function munge(Symbol $symbol): string
    {
        return $this->outputEmitter->mungeEncode($symbol->getName());
    }

    private function emitMethodBody(FnNode $node): void
    {
        if ($node->getRecurs()) {
            $this->outputEmitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
        }

        $this->outputEmitter->emitNode($node->getBody());

        if ($node->getRecurs()) {
            $this->outputEmitter->emitLine('break;', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        }
    }

    private function emitMethodEnd(FnNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
