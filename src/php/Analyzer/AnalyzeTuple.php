<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Exception;
use Phel\Analyzer;
use Phel\Ast\ApplyNode;
use Phel\Ast\BindingNode;
use Phel\Ast\CallNode;
use Phel\Ast\CatchNode;
use Phel\Ast\DefNode;
use Phel\Ast\DefStructNode;
use Phel\Ast\DoNode;
use Phel\Ast\FnNode;
use Phel\Ast\ForeachNode;
use Phel\Ast\GlobalVarNode;
use Phel\Ast\IfNode;
use Phel\Ast\LetNode;
use Phel\Ast\MethodCallNode;
use Phel\Ast\Node;
use Phel\Ast\NsNode;
use Phel\Ast\PhelArrayNode;
use Phel\Ast\PhpArrayGetNode;
use Phel\Ast\PhpArrayPushNode;
use Phel\Ast\PhpArraySetNode;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Ast\PhpNewNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Ast\QuoteNode;
use Phel\Ast\RecurNode;
use Phel\Ast\ThrowNode;
use Phel\Ast\TryNode;
use Phel\Destructure;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class AnalyzeTuple
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Tuple $x, NodeEnvironment $env): Node
    {
        if (!$x[0] instanceof Symbol) {
            return $this->analyzeInvoke($x, $env);
        }

        switch ($x[0]->getName()) {
            case 'def':
                return $this->analyzeDef($x, $env);
            case 'ns':
                return $this->analyzeNs($x, $env);
            case 'fn':
                return $this->analyzeFn($x, $env);
            case 'quote':
                return $this->analyzeQuote($x, $env);
            case 'do':
                return $this->analyzeDo($x, $env);
            case 'if':
                return $this->analyzeIf($x, $env);
            case 'apply':
                return $this->analyzeApply($x, $env);
            case 'let':
                return $this->analyzeLet($x, $env);
            case 'php/new':
                return $this->analyzePhpNew($x, $env);
            case 'php/->':
                return $this->analyzePhpObjectCall($x, $env, false);
            case 'php/::':
                return $this->analyzePhpObjectCall($x, $env, true);
            case 'php/aget':
                return $this->analyzePhpAGet($x, $env);
            case 'php/aset':
                return $this->analyzePhpASet($x, $env);
            case 'php/apush':
                return $this->analyzePhpAPush($x, $env);
            case 'php/aunset':
                return $this->analyzePhpAUnset($x, $env);
            case 'recur':
                return $this->analyzeRecur($x, $env);
            case 'try':
                return $this->analyzeTry($x, $env);
            case 'throw':
                return $this->analyzeThrow($x, $env);
            case 'loop':
                return $this->analyzeLoop($x, $env);
            case 'foreach':
                return $this->analyzeForeach($x, $env);
            case 'defstruct*':
                return $this->analyzeDefStruct($x, $env);
            default:
                return $this->analyzeInvoke($x, $env);
        }
    }

    private function analyzeInvoke(Tuple $x, NodeEnvironment $nodeEnvironment): Node
    {
        $tupleCount = count($x);
        $f = $this->analyzer->analyze($x[0], $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        if ($f instanceof GlobalVarNode && $f->isMacro()) {
            $this->analyzer->getGlobalEnvironment()->setAllowPrivateAccess(true);
            $result = $this->analyzer->analyze($this->macroExpand($x, $nodeEnvironment), $nodeEnvironment);
            $this->analyzer->getGlobalEnvironment()->setAllowPrivateAccess(false);

            return $result;
        }

        $arguments = [];
        for ($i = 1; $i < $tupleCount; $i++) {
            $arguments[] = $this->analyzer->analyze($x[$i], $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new CallNode(
            $nodeEnvironment,
            $f,
            $arguments,
            $x->getStartLocation()
        );
    }

    /** @return Phel|scalar|null */
    private function macroExpand(Tuple $x, NodeEnvironment $env)
    {
        $tupleCount = count($x);
        /**
         * @psalm-suppress PossiblyNullArgument
         */
        $node = $this->analyzer->getGlobalEnvironment()->resolve($x[0], $env);
        if ($node && $node instanceof GlobalVarNode) {
            $fn = $GLOBALS['__phel'][$node->getNamespace()][$node->getName()->getName()];

            $arguments = [];
            for ($i = 1; $i < $tupleCount; $i++) {
                $arguments[] = $x[$i];
            }

            try {
                $result = $fn(...$arguments);
                $this->enrichLocation($result, $x);
                return $result;
            } catch (Exception $e) {
                throw new AnalyzerException(
                    'Error in expanding macro "' . $node->getNamespace() . '\\' . $node->getName()->getName() . '": ' . $e->getMessage(),
                    $x->getStartLocation(),
                    $x->getEndLocation(),
                    $e
                );
            }
        }

        if (is_null($node)) {
            throw new AnalyzerException(
                'Can not resolive macro',
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        throw new AnalyzerException(
            'This is not macro expandable: ' . get_class($node),
            $x->getStartLocation(),
            $x->getEndLocation()
        );
    }

    /**
     * @param mixed $x
     * @param Phel $parent
     */
    private function enrichLocation($x, Phel $parent): void
    {
        if ($x instanceof Tuple) {
            foreach ($x as $item) {
                $this->enrichLocation($item, $parent);
            }

            if (!$x->getStartLocation()) {
                $x->setStartLocation($parent->getStartLocation());
            }
            if (!$x->getEndLocation()) {
                $x->setEndLocation($parent->getEndLocation());
            }
        } elseif ($x instanceof Phel) {
            if (!$x->getStartLocation()) {
                $x->setStartLocation($parent->getStartLocation());
            }
            if (!$x->getEndLocation()) {
                $x->setEndLocation($parent->getEndLocation());
            }
        }
    }

    private function analyzeDef(Tuple $x, NodeEnvironment $nodeEnvironment): DefNode
    {
        $countX = count($x);
        if ($countX < 3 || $countX > 4) {
            throw new AnalyzerException(
                "Two or three arguments are required for 'def. Got " . count($x),
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Symbol)) {
            throw new AnalyzerException(
                "First arugment of 'def must be a Symbol.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $namespace = $this->analyzer->getGlobalEnvironment()->getNs();
        /** @var Symbol $name */
        $name = $x[1];

        $initEnv = $nodeEnvironment
            ->withBoundTo($namespace . '\\' . $name)
            ->withContext(NodeEnvironment::CTX_EXPR)
            ->withDisallowRecurFrame();

        if ($countX === 4) {
            $meta = $x[2];
            $init = $x[3];
        } else {
            $meta = new Table();
            $init = $x[2];
        }

        if (is_string($meta)) {
            $kv = new Keyword('doc');
            $kv->setStartLocation($x->getStartLocation());
            $kv->setEndLocation($x->getEndLocation());

            $meta = Table::fromKVs($kv, $meta);
            $meta->setStartLocation($x->getStartLocation());
            $meta->setEndLocation($x->getEndLocation());
        } elseif ($meta instanceof Keyword) {
            $meta = Table::fromKVs($meta, true);
            $meta->setStartLocation($meta->getStartLocation());
            $meta->setEndLocation($meta->getEndLocation());
        } elseif (!$meta instanceof Table) {
            throw new AnalyzerException(
                "Metadata must be a Symbol, String, Keyword or Table",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $this->analyzer->getGlobalEnvironment()->addDefinition($namespace, $name, $meta);

        return new DefNode(
            $nodeEnvironment,
            $namespace,
            $name,
            $meta,
            $this->analyzer->analyze($init, $initEnv),
            $x->getStartLocation()
        );
    }

    private function analyzeNs(Tuple $x, NodeEnvironment $env): NsNode
    {
        $tupleCount = count($x);
        if (!($x[1] instanceof Symbol)) {
            throw new AnalyzerException(
                "First argument of 'ns must be a Symbol",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $ns = $x[1]->getName();
        if (!(preg_match("/^[a-zA-Z\x7f-\xff][a-zA-Z0-9\-\x7f-\xff\\\\]*[a-zA-Z0-9\-\x7f-\xff]*$/", $ns))) {
            throw new AnalyzerException(
                "The namespace is not valid. A valid namespace name starts with a letter,
                followed by any number of letters, numbers, or dashes. Elements are splitted by a backslash.",
                $x[1]->getStartLocation(),
                $x[1]->getEndLocation()
            );
        }

        $parts = explode("\\", $ns);
        foreach ($parts as $part) {
            if ($this->isPHPKeyword($part)) {
                throw new AnalyzerException(
                    "The namespace is not valid. The part '$part' can not be used because it is a reserved keyword.",
                    $x[1]->getStartLocation(),
                    $x[1]->getEndLocation()
                );
            }
        }

        $this->analyzer->getGlobalEnvironment()->setNs($x[1]->getName());

        $requireNs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $import = $x[$i];

            if (!($import instanceof Tuple)) {
                throw new AnalyzerException(
                    "Import in 'ns must be Tuples.",
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }

            /** @var Tuple $import */
            if ($this->isKeywordWithName($import[0], 'use')) {
                if (!($import[1] instanceof Symbol)) {
                    throw new AnalyzerException(
                        "First arugment in :use must be a symbol.",
                        $import->getStartLocation(),
                        $import->getEndLocation()
                    );
                }

                if (count($import) === 4 && $this->isKeywordWithName($import[2], 'as')) {
                    $alias = $import[3];
                    if (!($alias instanceof Symbol)) {
                        throw new AnalyzerException(
                            "Alias must be a Symbol",
                            $import->getStartLocation(),
                            $import->getEndLocation()
                        );
                    }
                } else {
                    $parts = explode('\\', $import[1]->getName());
                    $alias = new Symbol($parts[count($parts) - 1]);
                }

                $this->analyzer->getGlobalEnvironment()->addUseAlias($alias, $import[1]);
            } elseif ($this->isKeywordWithName($import[0], 'require')) {
                if (!($import[1] instanceof Symbol)) {
                    throw new AnalyzerException(
                        "First arugment in :require must be a symbol.",
                        $import->getStartLocation(),
                        $import->getEndLocation()
                    );
                }

                $requireNs[] = $import[1];

                if (count($import) === 4 && $this->isKeywordWithName($import[2], 'as')) {
                    $alias = $import[3];
                    if (!($alias instanceof Symbol)) {
                        throw new AnalyzerException(
                            "Alias must be a Symbol",
                            $import->getStartLocation(),
                            $import->getEndLocation()
                        );
                    }
                } else {
                    $parts = explode('\\', $import[1]->getName());
                    $alias = new Symbol($parts[count($parts) - 1]);
                }

                $this->analyzer->getGlobalEnvironment()->addRequireAlias($alias, $import[1]);
            }
        }

        return new NsNode($x[1]->getName(), $requireNs, $x->getStartLocation());
    }

    private function isPHPKeyword(string $w): bool
    {
        return in_array($w, PhpKeywords::KEYWORDS, true);
    }

    /** @param mixed $x */
    private function isKeywordWithName($x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }

    private function analyzeFn(Tuple $x, NodeEnvironment $env): FnNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 2 || $tupleCount > 3) {
            throw new AnalyzerException(
                "'fn requires one or two arguments",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                "Second argument of 'fn must be a Tuple",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $params = [];
        $lets = [];
        $isVariadic = false;
        $hasVariadicForm = false;
        $state = 'start';
        $xs = $x[1];
        foreach ($xs as $param) {
            switch ($state) {
                case 'start':
                    if ($param instanceof Symbol) {
                        if ($this->isSymWithName($param, '&')) {
                            $isVariadic = true;
                            $state = 'rest';
                        } elseif ($param->getName() === '_') {
                            $params[] = Symbol::gen()->copyLocationFrom($param);
                        } else {
                            $params[] = $param;
                        }
                    } else {
                        $tempSym = Symbol::gen()->copyLocationFrom($param);
                        $params[] = $tempSym;
                        $lets[] = $param;
                        $lets[] = $tempSym;
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    $hasVariadicForm = true;
                    if ($this->isSymWithName($param, '_')) {
                        $params[] = Symbol::gen()->copyLocationFrom($param);
                    } elseif ($param instanceof Symbol) {
                        $params[] = $param;
                    } else {
                        $tempSym = Symbol::gen()->copyLocationFrom($x);
                        $params[] = $tempSym;
                        $lets[] = $param;
                        $lets[] = $tempSym;
                    }
                    break;
                case 'done':
                    throw new AnalyzerException(
                        'Unsupported parameter form, only one symbol can follow the & parameter',
                        $x->getStartLocation(),
                        $x->getEndLocation()
                    );
            }
        }

        // Add a dummy variadic symbol
        if ($isVariadic && !$hasVariadicForm) {
            $params[] = Symbol::gen();
        }

        foreach ($params as $param) {
            if (!(preg_match("/^[a-zA-Z_\x80-\xff].*$/", $param->getName()))) {
                throw new AnalyzerException(
                    "Variable names must start with a letter or underscore: {$param->getName()}",
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }
        }

        $recurFrame = new RecurFrame($params);

        $body = $x[2];
        if (count($lets) > 0) {
            $body = Tuple::create(
                (new Symbol('let'))->copyLocationFrom($body),
                (new Tuple($lets, true))->copyLocationFrom($body),
                $body
            )->copyLocationFrom($body);
        }

        $bodyEnv = $env
            ->withMergedLocals($params)
            ->withContext(NodeEnvironment::CTX_RET)
            ->withAddedRecurFrame($recurFrame);

        $body = $this->analyzer->analyze($body, $bodyEnv);

        $uses = array_diff($env->getLocals(), $params);

        return new FnNode(
            $env,
            $params,
            $body,
            $uses,
            $isVariadic,
            $recurFrame->isActive(),
            $x->getStartLocation()
        );
    }

    /** @param mixed $x */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }

    private function analyzeQuote(Tuple $x, NodeEnvironment $env): QuoteNode
    {
        if (count($x) !== 2) {
            throw new AnalyzerException(
                "Exactly one arguments is required for 'quote",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        return new QuoteNode(
            $env,
            $x[1],
            $x->getStartLocation()
        );
    }

    private function analyzeDo(Tuple $x, NodeEnvironment $env): DoNode
    {
        $tupleCount = count($x);
        $stmts = [];
        for ($i = 1; $i < $tupleCount - 1; $i++) {
            $stmts[] = $this->analyzer->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame());
        }

        if ($tupleCount > 2) {
            $retEnv = $env->getContext() === NodeEnvironment::CTX_STMT
                ? $env->withContext(NodeEnvironment::CTX_STMT)
                : $env->withContext(NodeEnvironment::CTX_RET);
            $ret = $this->analyzer->analyze($x[$tupleCount - 1], $retEnv);
        } elseif ($tupleCount === 2) {
            $ret = $this->analyzer->analyze($x[$tupleCount - 1], $env);
        } else {
            $ret = $this->analyzer->analyze(null, $env);
        }

        return new DoNode(
            $env,
            $stmts,
            $ret,
            $x->getStartLocation()
        );
    }

    private function analyzeIf(Tuple $x, NodeEnvironment $env): IfNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 3 || $tupleCount > 4) {
            throw new AnalyzerException(
                "'if requires two or three arguments",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $testExpr = $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        $thenExpr = $this->analyzer->analyze($x[2], $env);
        if ($tupleCount === 3) {
            $elseExpr = $this->analyzer->analyze(null, $env);
        } else {
            $elseExpr = $this->analyzer->analyze($x[3], $env);
        }

        return new IfNode(
            $env,
            $testExpr,
            $thenExpr,
            $elseExpr,
            $x->getStartLocation()
        );
    }

    private function analyzeApply(Tuple $x, NodeEnvironment $env): ApplyNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 3) {
            throw new AnalyzerException(
                "At least three arguments are required for 'apply",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $fn = $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        $args = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $args[] = $this->analyzer->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new ApplyNode(
            $env,
            $fn,
            $args,
            $x->getStartLocation()
        );
    }

    private function analyzeLet(Tuple $x, NodeEnvironment $env): LetNode
    {
        if (count($x) < 2) {
            throw new AnalyzerException(
                "At least two arguments are required for 'let",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                "Binding parameter must be a tuple",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!(count($x[1]) % 2 === 0)) {
            throw new AnalyzerException(
                "Bindings must be a even number of parameters",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $destructor = new Destructure();
        $bindings = $destructor->run($x[1]);
        $bindingTupleData = [];
        foreach ($bindings as $binding) {
            $bindingTupleData[] = $binding[0];
            $bindingTupleData[] = $binding[1];
        }

        $newTuple = $x->update(1, new Tuple($bindingTupleData, true));

        return $this->analyzeLetOrLoop($newTuple, $env, false);
    }

    private function analyzeLetOrLoop(Tuple $x, NodeEnvironment $env, bool $isLoop = false): LetNode
    {
        $tupleCount = count($x);
        $exprs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $exprs[] = $x[$i];
        }

        /**
         * @psalm-suppress PossiblyNullArgument
         */
        $bindings = $this->analyzeBindings($x[1], $env->withDisallowRecurFrame());

        $locals = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
        }

        $recurFrame = new RecurFrame($locals);

        $bodyEnv = $env
            ->withMergedLocals($locals)
            ->withContext(
                $env->getContext() === NodeEnvironment::CTX_EXPR
                    ? NodeEnvironment::CTX_RET
                    : $env->getContext()
            );

        if ($isLoop) {
            $bodyEnv = $bodyEnv->withAddedRecurFrame($recurFrame);
        }

        foreach ($bindings as $binding) {
            $bodyEnv = $bodyEnv->withShadowedLocal($binding->getSymbol(), $binding->getShadow());
        }

        $bodyExpr = $this->analyzer->analyze(Tuple::create(new Symbol('do'), ...$exprs), $bodyEnv);

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $isLoop && $recurFrame->isActive(),
            $x->getStartLocation()
        );
    }

    /**
     * @return BindingNode[]
     */
    private function analyzeBindings(Tuple $x, NodeEnvironment $env)
    {
        $tupleCount = count($x);
        $initEnv = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < $tupleCount; $i+=2) {
            $sym = $x[$i];
            if (!($sym instanceof Symbol)) {
                throw new AnalyzerException(
                    'Binding name must be a symbol, got: ' . \gettype($sym),
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }

            $shadowSym = Symbol::gen($sym->getName() . '_')
                ->copyLocationFrom($sym);
            $init = $x[$i+1];

            $nextBoundTo = $initEnv->getBoundTo() . '.' . $sym->getName();
            $expr = $this->analyzer->analyze($init, $initEnv->withBoundTo($nextBoundTo));

            $nodes[] = new BindingNode(
                $env,
                $sym,
                $shadowSym,
                $expr,
                $sym->getStartLocation()
            );

            $initEnv = $initEnv->withMergedLocals([$sym])->withShadowedLocal($sym, $shadowSym);
        }

        return $nodes;
    }

    private function analyzePhpNew(Tuple $x, NodeEnvironment $env): PhpNewNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 2) {
            throw new AnalyzerException(
                "At least one arguments is required for 'php/new",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $classExpr = $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        $args = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $args[] = $this->analyzer->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new PhpNewNode(
            $env,
            $classExpr,
            $args,
            $x->getStartLocation()
        );
    }

    private function analyzePhpObjectCall(Tuple $x, NodeEnvironment $env, bool $isStatic): PhpObjectCallNode
    {
        $fnName = $isStatic ? 'php/::' : 'php/->';
        if (count($x) !== 3) {
            throw new AnalyzerException(
                "Exactly two arguments are expected for '$fnName",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[2] instanceof Tuple || $x[2] instanceof Symbol)) {
            throw new AnalyzerException(
                "Second argument of '$fnName must be a Tuple or a Symbol",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $targetExpr = $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        if ($x[2] instanceof Tuple) {
            // Method call
            $methodCall = true;

            /** @var Tuple $tuple */
            $tuple = $x[2];
            $tCount = count($tuple);

            if (count($x) < 1) {
                throw new AnalyzerException(
                    "Function name is missing",
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }

            $args = [];
            for ($i = 1; $i < $tCount; $i++) {
                $args[] = $this->analyzer->analyze($tuple[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
            }

            /**
             * @psalm-suppress PossiblyNullArgument
             */
            $callExpr = new MethodCallNode(
                $env,
                $tuple[0],
                $args,
                $tuple->getStartLocation()
            );
        } else {
            // Property call
            $methodCall = false;

            $callExpr = new PropertyOrConstantAccessNode(
                $env,
                $x[2],
                $x[2]->getStartLocation()
            );
        }

        return new PhpObjectCallNode(
            $env,
            $targetExpr,
            $callExpr,
            $isStatic,
            $methodCall,
            $x->getStartLocation()
        );
    }

    private function analyzePhpAGet(Tuple $x, NodeEnvironment $env): PhpArrayGetNode
    {
        return new PhpArrayGetNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }

    private function analyzePhpASet(Tuple $x, NodeEnvironment $env): PhpArraySetNode
    {
        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[3], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }

    private function analyzePhpAPush(Tuple $x, NodeEnvironment $env): PhpArrayPushNode
    {
        return new PhpArrayPushNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }

    private function analyzePhpAUnset(Tuple $x, NodeEnvironment $env): PhpArrayUnsetNode
    {
        if ($env->getContext() !== NodeEnvironment::CTX_STMT) {
            throw new AnalyzerException(
                "'php/unset can only be called as Statement and not as Expression",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        return new PhpArrayUnsetNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyzer->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $x->getStartLocation()
        );
    }

    private function analyzeRecur(Tuple $x, NodeEnvironment $env): RecurNode
    {
        $tupleCount = count($x);
        $frame = $env->getCurrentRecurFrame();

        if (!($x[0] instanceof Symbol && $x[0] == "recur")) {
            throw new AnalyzerException(
                "This is not a 'recur.",
                $x->getStartLocation(),
                $x->getEndLocation(),
            );
        }

        if (!$frame) {
            throw new AnalyzerException(
                "Can't call 'recur here",
                $x[0]->getStartLocation(),
                $x[0]->getEndLocation()
            );
        }

        if ($tupleCount - 1 !== count($frame->getParams())) {
            throw new AnalyzerException(
                "Wrong number of arugments for 'recur. Expected: "
                . count($frame->getParams()) . ' args, got: ' . ($tupleCount - 1),
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }


        $frame->setIsActive(true);

        $exprs = [];
        for ($i = 1; $i < $tupleCount; $i++) {
            $exprs[] = $this->analyzer->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new RecurNode(
            $env,
            $frame,
            $exprs,
            $x->getStartLocation()
        );
    }

    private function analyzeTry(Tuple $x, NodeEnvironment $env): TryNode
    {
        $tupleCount = count($x);
        $state = 'start';
        $body = [];
        $catches = [];
        /** @var Tuple|null $finally */
        $finally = null;
        for ($i = 1; $i < $tupleCount; $i++) {
            /** @var mixed $form */
            $form = $x[$i];

            switch ($state) {
                case 'start':
                    if ($this->isSymWithName($form[0], 'catch')) {
                        $state = 'catches';
                        $catches[] = $form;
                    } elseif ($this->isSymWithName($form[0], 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        $body[] = $form;
                    }
                    break;

                case 'catches':
                    if ($this->isSymWithName($form[0], 'catch')) {
                        $catches[] = $form;
                    } elseif ($this->isSymWithName($form[0], 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        throw new AnalyzerException("Invalid 'try form", $x->getStartLocation(), $x->getEndLocation());
                    }
                    break;

                case 'done':
                    throw new AnalyzerException("Unexpected form after 'finally", $x->getStartLocation(), $x->getEndLocation());

                default:
                    throw new AnalyzerException("Unexpected parser state in 'try", $x->getStartLocation(), $x->getEndLocation());
            }
        }

        if ($finally) {
            $finally = $finally->update(0, new Symbol('do'));
            $finally = $this->analyzer->analyze($finally, $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame());
        }

        $catchCtx = $env->getContext() === NodeEnvironment::CTX_EXPR ? NodeEnvironment::CTX_RET : $env->getContext();
        $catchNodes = [];
        /** @var Tuple $catch */
        foreach ($catches as $catch) {
            [$_, $type, $name] = $catch;

            if (!($type instanceof Symbol)) {
                throw new AnalyzerException(
                    "First argument of 'catch must be a Symbol",
                    $catch->getStartLocation(),
                    $catch->getEndLocation()
                );
            }

            if (!($name instanceof Symbol)) {
                throw new AnalyzerException(
                    "Second argument of 'catch must be a Symbol",
                    $catch->getStartLocation(),
                    $catch->getEndLocation()
                );
            }

            $exprs = [new Symbol('do')];
            $catchCount = count($catch);
            for ($i = 3; $i < $catchCount; $i++) {
                $exprs[] = $catch[$i];
            }

            $catchBody = $this->analyzer->analyze(
                new Tuple($exprs),
                $env->withContext($catchCtx)
                    ->withMergedLocals([$name])
                    ->withDisallowRecurFrame()
            );

            $catchNodes[] = new CatchNode(
                $env,
                $type,
                $name,
                $catchBody,
                $catch->getStartLocation()
            );
        }

        $body = $this->analyzer->analyze(
            new Tuple(array_merge([new Symbol('do')], $body)),
            $env->withContext(count($catchNodes) > 0 || $finally ? $catchCtx : $env->getContext())
                ->withDisallowRecurFrame()
        );

        return new TryNode(
            $env,
            $body,
            $catchNodes,
            $finally,
            $x->getStartLocation()
        );
    }

    private function analyzeThrow(Tuple $x, NodeEnvironment $env): ThrowNode
    {
        if (count($x) !== 2) {
            throw new AnalyzerException(
                "Exact one argument is required for 'throw",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        return new ThrowNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()),
            $x->getStartLocation()
        );
    }

    private function analyzeLoop(Tuple $x, NodeEnvironment $env): LetNode
    {
        $tupleCount = count($x);
        if (!($x[0] instanceof Symbol && $x[0] == "loop")) {
            throw new AnalyzerException(
                "This is not a 'loop.",
                $x->getStartLocation(),
                $x->getEndLocation(),
            );
        }

        if ($tupleCount < 2) {
            throw new AnalyzerException(
                "At least two arguments are required for 'loop.",
                $x->getStartLocation(),
                $x->getEndLocation(),
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                "Binding parameter must be a tuple.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!(count($x[1]) % 2 === 0)) {
            throw new AnalyzerException(
                "Bindings must be a even number of parameters",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $loopBindings = $x[1];
        $loopBindingsCount = count($loopBindings);

        $preInits = [];
        $lets = [];
        for ($i = 0; $i < $loopBindingsCount; $i+=2) {
            $b = $loopBindings[$i];
            $init = $loopBindings[$i+1];

            Destructure::assertSupportedBinding($b);

            if ($b instanceof Symbol) {
                $preInits[] = $b;
                $preInits[] = $init;
            } else {
                $tempSym = Symbol::gen();
                $tempSym->setStartLocation($b->getStartLocation());
                $tempSym->setEndLocation($b->getEndLocation());

                $preInits[] = $tempSym;
                $preInits[] = $init;
                $lets[] = $b;
                $lets[] = $tempSym;
            }
        }

        if (count($lets) > 0) {
            $bodyExpr = [];
            for ($i = 2; $i < $tupleCount; $i++) {
                $bodyExpr[] = $x[$i];
            }
            $letSym = new Symbol('let');
            $letSym->setStartLocation($x[0]->getStartLocation());
            $letSym->setEndLocation($x[0]->getEndLocation());

            $letExpr = Tuple::create(
                $letSym,
                new Tuple($lets, true),
                ...$bodyExpr
            );
            $letExpr->setStartLocation($x->getStartLocation());
            $letExpr->setEndLocation($x->getEndLocation());

            $newExpr = Tuple::create(
                $x[0],
                new Tuple($preInits, true),
                $letExpr
            );
            $newExpr->setStartLocation($x->getStartLocation());
            $newExpr->setEndLocation($x->getEndLocation());

            return $this->analyzeLetOrLoop($newExpr, $env, true);
        }

        return $this->analyzeLetOrLoop($x, $env, true);
    }

    private function analyzeForeach(Tuple $x, NodeEnvironment $env): ForeachNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 2) {
            throw new AnalyzerException(
                "At least two arguments are required for 'foreach",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                "First argument of 'foreach must be a tuple.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (count($x[1]) !== 2 && count($x[1]) !== 3) {
            throw new AnalyzerException(
                "Tuple of 'foreach must have exactly two or three elements.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $lets = [];
        if (count($x[1]) === 2) {
            $keySymbol = null;

            $valueSymbol = $x[1][0];
            if (!($valueSymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $valueSymbol;
                $lets[] = $tmpSym;
                $valueSymbol = $tmpSym;
            }
            $bodyEnv = $env->withMergedLocals([$valueSymbol]);
            $listExpr = $this->analyzer->analyze($x[1][1], $env->withContext(NodeEnvironment::CTX_EXPR));
        } else {
            $keySymbol = $x[1][0];
            if (!($keySymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $keySymbol;
                $lets[] = $tmpSym;
                $keySymbol = $tmpSym;
            }

            $valueSymbol = $x[1][1];
            if (!($valueSymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $valueSymbol;
                $lets[] = $tmpSym;
                $valueSymbol = $tmpSym;
            }

            $bodyEnv = $env->withMergedLocals([$valueSymbol, $keySymbol]);
            $listExpr = $this->analyzer->analyze($x[1][2], $env->withContext(NodeEnvironment::CTX_EXPR));
        }

        $bodys = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $bodys[] = $x[$i];
        }

        if (count($lets)) {
            $body = Tuple::create(new Symbol('let'), new Tuple($lets, true), ...$bodys);
        } else {
            $body = Tuple::create(new Symbol('do'), ...$bodys);
        }

        $bodyExpr = $this->analyzer->analyze($body, $bodyEnv->withContext(NodeEnvironment::CTX_STMT));

        return new ForeachNode(
            $env,
            $bodyExpr,
            $listExpr,
            $valueSymbol,
            $keySymbol,
            $x->getStartLocation()
        );
    }

    private function analyzeDefStruct(Tuple $x, NodeEnvironment $env): DefStructNode
    {
        if (count($x) !== 3) {
            throw new AnalyzerException(
                "Exactly two arguments are required for 'defstruct. Got " . count($x),
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Symbol)) {
            throw new AnalyzerException(
                "First arugment of 'defstruct must be a Symbol.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[2] instanceof Tuple)) {
            throw new AnalyzerException(
                "Second arugment of 'defstruct must be a Tuple.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $params = [];
        foreach ($x[2] as $element) {
            if (!($element instanceof Symbol)) {
                throw new AnalyzerException(
                    "Defstruct field elements must by Symbols.",
                    $element->getStartLocation(),
                    $element->getEndLocation()
                );
            }

            $params[] = $element;
        }

        $namespace = $this->analyzer->getGlobalEnvironment()->getNs();

        return new DefStructNode(
            $env,
            $namespace,
            $x[1],
            $params,
            $x->getStartLocation()
        );
    }

    private function analyzePhelArray(PhelArray $x, NodeEnvironment $env): PhelArrayNode
    {
        $args = [];
        foreach ($x as $arg) {
            $args[] = $this->analyzer->analyze($arg, $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new PhelArrayNode($env, $args, $x->getStartLocation());
    }
}
