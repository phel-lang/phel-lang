<?php

namespace Phel;

use Phel\Ast\ApplyNode;
use Phel\Ast\BindingNode;
use Phel\Ast\CallNode;
use Phel\Ast\CatchNode;
use Phel\Ast\DefNode;
use Phel\Ast\DoNode;
use Phel\Ast\NsNode;
use Phel\Ast\FnNode;
use Phel\Ast\ForeachNode;
use Phel\Ast\GlobalVarNode;
use Phel\Ast\IfNode;
use Phel\Ast\LetNode;
use Phel\Ast\LiteralNode;
use Phel\Ast\LocalVarNode;
use Phel\Ast\MethodCallNode;
use Phel\Ast\Node;
use Phel\Ast\PhelArrayNode;
use Phel\Ast\PhpArrayGetNode;
use Phel\Ast\PhpArrayPushNode;
use Phel\Ast\PhpArraySetNode;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Ast\PhpNewNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PhpVarNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Ast\QuoteNode;
use Phel\Ast\RecurNode;
use Phel\Ast\ThrowNode;
use Phel\Ast\TryNode;
use Phel\Ast\TupleNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Throwable;

class Analyzer {

    /**
     * @var GlobalEnvironment
     */
    protected $globalEnvironment;

    public function __construct($globalEnvironment = null)
    {
        if (is_null($globalEnvironment)) {
            $globalEnvironment = new GlobalEnvironment();
        }

        $this->globalEnvironment = $globalEnvironment;
    }

    private function isLiteral($x) {
        return is_string($x) 
          || is_float($x)
          || is_int($x)
          || is_bool($x)
          || $x === null
          || $x instanceof Keyword
          || $x instanceof PhelArray
          || $x instanceof Table;
    }

    private function isValidPhelType($x) {
        return $x instanceof Phel || $this->isLiteral($x);
    }

    public function analyze($x, NodeEnvironment $nodeEnvironment = null): Node {
        if (is_null($nodeEnvironment)) {
            $nodeEnvironment = NodeEnvironment::empty();
        }

        if ($this->isLiteral($x)) {
            return $this->analyzeLiteral($x, $nodeEnvironment);
        } else if ($x instanceof Symbol) {
            return $this->analyzeVar($x, $nodeEnvironment);
        } else if ($x instanceof Tuple && $x->isUsingBracket()) {
            return $this->analyzeBracketTuple($x, $nodeEnvironment);
        } else if ($x instanceof Tuple) {
            if ($x[0] instanceof Symbol) {
                switch ($x[0]->getName()) {
                    case 'def':
                        return $this->analyzeDef($x, $nodeEnvironment);
                    case 'ns':
                        return $this->analyzeNs($x, $nodeEnvironment);
                    case 'fn':
                        return $this->analyzeFn($x, $nodeEnvironment);
                    case 'quote':
                        return $this->analyzeQuote($x, $nodeEnvironment);
                    case 'do':
                        return $this->analyzeDo($x, $nodeEnvironment);
                    case 'if':
                        return $this->analyzeIf($x, $nodeEnvironment);
                    case 'apply':
                        return $this->analyzeApply($x, $nodeEnvironment);
                    case 'let':
                        return $this->analyzeLet($x, $nodeEnvironment);
                    case 'php/new':
                        return $this->analyzePhpNew($x, $nodeEnvironment);
                    case 'php/->':
                        return $this->analyzePhpObjectCall($x, $nodeEnvironment, false);
                    case 'php/::':
                        return $this->analyzePhpObjectCall($x, $nodeEnvironment, true);
                    case 'php/aget':
                        return $this->analyzePhpAGet($x, $nodeEnvironment);
                    case 'php/aset':
                        return $this->analyzePhpASet($x, $nodeEnvironment);
                    case 'php/apush':
                        return $this->analyzePhpAPush($x, $nodeEnvironment);
                    case 'php/aunset':
                        return $this->analyzePhpAUnset($x, $nodeEnvironment);
                    case 'recur':
                        return $this->analyzeRecur($x, $nodeEnvironment);
                    case 'try':
                        return $this->analyzeTry($x, $nodeEnvironment);
                    case 'throw':
                        return $this->analyzeThrow($x, $nodeEnvironment);
                    case 'loop':
                        return $this->analyzeLoop($x, $nodeEnvironment);
                    case 'foreach':
                        return $this->analyzeForeach($x, $nodeEnvironment);
                    default:
                        return $this->analyzeInvoke($x, $nodeEnvironment);
                }
            } else {
                return $this->analyzeInvoke($x, $nodeEnvironment);
            }
        } else {
            throw new AnalyzerException('Unhandled type: ' . var_export($x, true));
        }
    }

    protected function analyzeForeach(Tuple $x, NodeEnvironment $env) {
        // (foreach [k? v xs] @body)
        assert(count($x) > 2, "At least two arguments are required");
        assert($x[1] instanceof Tuple, "First argument must be a tuple");
        assert(count($x[1]) == 2 || count($x[1]) == 3, "Tuple of foreach must have two or three elements");

        $lets = [];
        if (count($x[1]) == 2) {
            $keySymbol = null;

            $valueSymbol = $x[1][0];
            if (!($valueSymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $valueSymbol;
                $lets[] = $tmpSym;
                $valueSymbol = $tmpSym;
            }
            $bodyEnv = $env->withMergedLocals([$valueSymbol]);
            $listExpr = $this->analyze($x[1][1], $env->withContext(NodeEnvironment::CTX_EXPR));
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
            $listExpr = $this->analyze($x[1][2], $env->withContext(NodeEnvironment::CTX_EXPR));
        }

        $bodys = [];
        for ($i = 2; $i < count($x); $i++) {
            $bodys[] = $x[$i];
        }

        if (count($lets)) {
            $body = Tuple::create(new Symbol('let'), new Tuple($lets, true), ...$bodys);
        } else {
            $body = Tuple::create(new Symbol('do'), ...$bodys);
        }

        $bodyExpr = $this->analyze($body, $bodyEnv->withContext(NodeEnvironment::CTX_STMT));

        return new ForeachNode(
            $env,
            $bodyExpr,
            $listExpr,
            $valueSymbol,
            $keySymbol
        );
    }

    protected function analyzePhpAUnset(Tuple $x, NodeEnvironment $env) {
        return new PhpArrayUnsetNode(
            $env,
            $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR))
        );
    }

    protected function analyzePhpAGet(Tuple $x, NodeEnvironment $env) {
        return new PhpArrayGetNode(
            $env,
            $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR))
        );
    }

    protected function analyzePhpAPush(Tuple $x, NodeEnvironment $env) {
        return new PhpArrayPushNode(
            $env,
            $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR))
        );
    }

    protected function analyzePhpASet(Tuple $x, NodeEnvironment $env) {
        return new PhpArraySetNode(
            $env,
            $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyze($x[2], $env->withContext(NodeEnvironment::CTX_EXPR)),
            $this->analyze($x[3], $env->withContext(NodeEnvironment::CTX_EXPR))
        );
    }

    protected function analyzeLoop(Tuple $x, NodeEnvironment $env) {
        assert(count($x) >= 2, "At least two arguments are required");
        assert($x[1] instanceof Tuple, "Binding parameter must be a tuple");
        assert(count($x[1]) % 2 == 0, "bindings must be a even number of parameters");

        $loopBindings = $x[1];

        $preInits = [];
        $lets = [];
        for ($i = 0; $i < count($loopBindings); $i+=2) {
            $b = $loopBindings[$i];
            $init = $loopBindings[$i+1];

            if ($b instanceof Symbol) {
                $preInits[] = $b;
                $preInits[] = $init;
            } else {
                $tempSym = Symbol::gen();
                $preInits[] = $tempSym;
                $preInits[] = $init;
                $lets[] = $b;
                $lets[] = $tempSym;
            }
        }

        if (count($lets) > 0) {
            $bodyExpr = [];
            for ($i = 2; $i < count($x); $i++) {
                $bodyExpr[] = $x[$i];
            }
            $newExpr = Tuple::create(
                new Symbol('loop'),
                new Tuple($preInits, true),
                Tuple::create(
                    new Symbol('let'),
                    new Tuple($lets, true),
                    ...$bodyExpr
                )
            );

            return $this->analyzeLetOrLoop($newExpr, $env, true);
        } else {
            return $this->analyzeLetOrLoop($x, $env, true);
        }
    }

    protected function analyzeThrow(Tuple $x, NodeEnvironment $env) {
        assert(count($x) == 2, "Exact one argument is required in 'throw");
        return new ThrowNode(
            $env,
            $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame())
        );
    }

    protected function analyzeTry(Tuple $x, NodeEnvironment $env) {
        $state = 'start';
        $body = [];
        $catches = [];
        /** @var Tuple|null $finally */
        $finally = null;
        for ($i = 1; $i < count($x); $i++) {
            $form = $x[$i];

            switch ($state) {
                case 'start':
                    if ($this->isSymWithName($form[0], 'catch')) {
                        $state = 'catches';
                        $catches[] = $form;
                    } else if ($this->isSymWithName($form[0], 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        $body[] = $form;
                    }
                    break;

                case 'catches':
                    if ($this->isSymWithName($form[0], 'catch')) {
                        $catches[] = $form;
                    } else if ($this->isSymWithName($form[0], 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        throw new AnalyzerException('Invalid try form');
                    }
                    break;

                case 'done':
                    throw new AnalyzerException('Unexpected form after finally');

                default:
                    throw new AnalyzerException('Unexpected parser state in try');
            }
        }

        if ($finally) {
            $finally = $finally->update(0, new Symbol('do'));
            $finally = $this->analyze($finally, $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame());
        }

        $catchCtx = $env->getContext() == NodeEnvironment::CTX_EXPR ? NodeEnvironment::CTX_RET : $env->getContext();
        $catchNodes = [];
        foreach ($catches as $catch) {
            $type = $catch[1];
            $name = $catch[2];

            assert($type instanceof Symbol, "First argument of catch must be a Symbol");
            assert($name instanceof Symbol, "Second argument of catch must be a Symbol");

            $exprs = [new Symbol('do')];
            for ($i = 3; $i < count($catch); $i++) {
                $exprs[] = $catch[$i];
            }

            $catchBody = $this->analyze(
                new Tuple($exprs),
                $env->withContext($catchCtx)
                    ->withMergedLocals([$name])
                    ->withDisallowRecurFrame()
            );
            
            $catchNodes[] = new CatchNode(
                $env,
                $type,
                $name,
                $catchBody
            );
        }

        $body = $this->analyze(
            new Tuple(array_merge([new Symbol('do')], $body)),
            $env->withContext(count($catchNodes) > 0 || $finally ? $catchCtx : $env->getContext())
                ->withDisallowRecurFrame()
        );

        return new TryNode(
            $env,
            $body,
            $catchNodes,
            $finally
        );
    }

    protected function analyzeRecur(Tuple $x, NodeEnvironment $env) {
        $frame = $env->getCurrentRecurFrame();

        assert($frame, "Can't recur here");
        assert(count($x) - 1 == count($frame->getParams()), "Recur argument count mismatch. Expected: " 
            . count($frame->getParams()) . ' args, got: ' . (count($x) - 1));

        if ($frame) {
            $frame->setIsActive(true);

            $exprs = [];
            for($i = 1; $i < count($x); $i++) {
                $exprs[] = $this->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
            }

            return new RecurNode(
                $env,
                $frame,
                $exprs
            );
        } else {
            throw new AnalyzerException("Can't recur here");
        }
    }

    protected function analyzePhpNew(Tuple $x, NodeEnvironment $env) {
        assert(count($x) >= 2, "At least one arguments is required for 'php/new");

        $classExpr = $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        $args = [];
        for ($i = 2; $i < count($x); $i++) {
            $args[] = $this->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new PhpNewNode(
            $env,
            $classExpr,
            $args
        );
    }

    protected function analyzePhpObjectCall(Tuple $x, NodeEnvironment $env, $isStatic) {
        assert(count($x) == 3, "Exactly two arguments are expected");
        assert($x[2] instanceof Tuple || $x[2] instanceof Symbol, "Second argument must be a Tuple or a Symbol");

        $targetExpr = $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        if ($x[2] instanceof Tuple) {
            // Method call
            $methodCall = true;

            $tuple = $x[2];
            $args = [];
            for($i = 1; $i < count($tuple); $i++) {
                $args[] = $this->analyze($tuple[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
            }

            $callExpr = new MethodCallNode(
                $env,
                $tuple[0],
                $args
            );
        } else {
            // Property call
            $methodCall = false;

            $callExpr = new PropertyOrConstantAccessNode(
                $env,
                $x[2]
            );
        }

        return new PhpObjectCallNode(
            $env,
            $targetExpr,
            $callExpr,
            $isStatic,
            $methodCall
        );
    }

    protected function analyzeBracketTuple(Tuple $x, NodeEnvironment $env) {
        $args = [];
        foreach ($x as $arg) {
            $args[] = $this->analyze($arg, $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new TupleNode($env, $args);
    }

    protected function analyzePhelArray(PhelArray $x, NodeEnvironment $env) {
        $args = [];
        foreach ($x as $arg) {
            $args[] = $this->analyze($arg, $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new PhelArrayNode($env, $args);
    }

    protected function analyzeVar(Symbol $x, NodeEnvironment $env) {
        if (substr($x->getName(),0,4) == 'php/') {
            return new PhpVarNode($env, substr($x->getName(), 4));
        } else if ($env->hasLocal($x)) {
            if ($env->isShadowed($x)) {
                return new LocalVarNode($env, $env->getShadowed($x));
            } else {
                return new LocalVarNode($env, $x);
            }
        } else {
            $globalResolve = $this->globalEnvironment->resolve($x, $env);
            if ($globalResolve) {
                return $globalResolve;
            } else {
                throw new AnalyzerException('Can not resolve symbol ' . $x->getName(), $x->getStartLocation(), $x->getEndLocation());
            }
        }
    }

    protected function analyzeLet(Tuple $x, NodeEnvironment $env) {
        assert(count($x) >= 2, "At least two arguments are required");
        assert($x[1] instanceof Tuple, "Binding parameter must be a tuple");
        assert(count($x[1]) % 2 == 0, "bindings must be a even number of parameters");

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

    protected function analyzeLetOrLoop(Tuple $x, NodeEnvironment $env, $isLoop = false) {
        assert(count($x) >= 2, "At least two arguments are required");
        assert($x[1] instanceof Tuple, "Binding parameter must be a tuple");
        assert(count($x[1]) % 2 == 0, "bindings must be a even number of parameters");

        $exprs = [];
        for ($i = 2; $i < count($x); $i++) {
            $exprs[] = $x[$i];
        }

        $bindings = $this->analyzeBindings($x[1], $env->withDisallowRecurFrame());

        $locals = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
        }

        $recurFrame = new RecurFrame($locals);

        $bodyEnv = $env
            ->withMergedLocals($locals)
            ->withContext(
                $env->getContext() == NodeEnvironment::CTX_EXPR
                    ? NodeEnvironment::CTX_RET
                    : $env->getContext()
            );

        if ($isLoop) {
            $bodyEnv = $bodyEnv->withAddedRecurFrame($recurFrame);
        }

        foreach ($bindings as $binding) {
            $bodyEnv = $bodyEnv->withShadowedLocal($binding->getSymbol(), $binding->getShadow());
        }

        $bodyExpr = $this->analyze(Tuple::create(new Symbol('do'), ...$exprs), $bodyEnv);

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $isLoop && $recurFrame->isActive()
        );
    }

    protected function analyzeBindings(Tuple $x, NodeEnvironment $env) {
        $initEnv = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < count($x); $i+=2) {
            $sym = $x[$i];
            $shadowSym = Symbol::gen($sym->getName() . '_');
            $init = $x[$i+1];

            assert($sym instanceof Symbol, 'Binding name must be a symbol, got: ' . \gettype($sym));
            $expr = $this->analyze($init, $initEnv);

            $nodes[] = new BindingNode(
                $env,
                $sym,
                $shadowSym,
                $expr
            );

            $initEnv = $initEnv->withMergedLocals([$sym])->withShadowedLocal($sym, $shadowSym);
        }

        return $nodes;
    }

    protected function analyzeApply(Tuple $x, NodeEnvironment $env) {
        assert(count($x) >= 3, 'At least three arguments are required');

        $fn = $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        $args = [];
        for ($i = 2; $i < count($x); $i++) {
            $args[] = $this->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new ApplyNode(
            $env,
            $fn,
            $args
        );
    }

    protected function analyzeDo(Tuple $x, NodeEnvironment $env) {
        $stmts = [];
        for ($i = 1; $i < count($x) - 1; $i++) {
            $stmts[] = $this->analyze($x[$i], $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame());
        }

        if (count($x) > 2) {
            $retEnv = $env->getContext() == NodeEnvironment::CTX_STMT
                ? $env->withContext(NodeEnvironment::CTX_STMT)
                : $env->withContext(NodeEnvironment::CTX_RET);
            $ret = $this->analyze($x[count($x) - 1], $retEnv);
        } else if (count($x) == 2) {
            $ret = $this->analyze($x[count($x) - 1], $env);
        } else {
            $ret = $this->analyze(null, $env);
        }

        return new DoNode(
            $env,
            $stmts,
            $ret
        );
    }

    protected function analyzeIf(Tuple $x, NodeEnvironment $env) {
        assert(!(count($x) < 3), "Too few arguments for if");
        assert(!(count($x) > 4), "Too many arguments for if");

        $testExpr = $this->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        $thenExpr = $this->analyze($x[2], $env);
        if (count($x) == 3) {
            $elseExpr = $this->analyze(null, $env);
        } else {
            $elseExpr = $this->analyze($x[3], $env);
        }

        return new IfNode(
            $env,
            $testExpr,
            $thenExpr,
            $elseExpr
        );
    }

    protected function analyzeQuote(Tuple $x, NodeEnvironment $env) {
        assert(count($x) == 2, "A quote tuple must have exactly two arguments");

        return new QuoteNode(
            $env,
            $x[1]
        );
    }

    protected function analyzeFn(Tuple $x, NodeEnvironment $env) {
        assert(count($x) >= 2 && count($x) <= 3, "A fn tuple must have two or three arguments");
        assert($x[1] instanceof Tuple, "Second argument must be a list of arguments");

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
                        } else if ($param->getName() == '_') {
                            $params[] = Symbol::gen(); // Add dummy variadic symbol
                        } else {
                            $params[] = $param;
                        }
                    } else {
                        $tempSym = Symbol::gen();
                        $params[] = $tempSym;
                        $lets[] = $param;
                        $lets[] = $tempSym;
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    $hasVariadicForm = true;
                    if ($this->isSymWithName($param, '_')) {
                        $params[] = Symbol::gen(); // Add dummy variadic symbol
                    } else if ($param instanceof Symbol) {
                        $params[] = $param;
                    } else {
                        $tempSym = Symbol::gen();
                        $params[] = $tempSym;
                        $lets[] = $param;
                        $lets[] = $tempSym;
                    }
                    break;
                case 'done':
                    throw new AnalyzerException('Unsupported parameter form, only one symbol can follow the & parameter');
            }
        }

        // Add a dummy variadic symbol
        if ($isVariadic && !$hasVariadicForm) {
            $params[] = Symbol::gen();
        }

        $recurFrame = new RecurFrame($params);

        $body = $x[2];
        if (count($lets) > 0) {
            $body = Tuple::create(new Symbol('let'), new Tuple($lets, true), $body);
        }

        $bodyEnv = $env
            ->withMergedLocals($params)
            ->withContext(NodeEnvironment::CTX_RET)
            ->withAddedRecurFrame($recurFrame);

        $body = $this->analyze($body, $bodyEnv);

        $uses = array_diff($env->getLocals(), $params);

        return new FnNode(
            $env,
            $params,
            $body,
            $uses,
            $isVariadic,
            $recurFrame->isActive()
        );
    }

    protected function analyzeInvoke(Tuple $x, NodeEnvironment $nodeEnvironment): Node {
        $f = $this->analyze($x[0], $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        if ($f instanceof GlobalVarNode && $f->isMacro()) {
            return $this->analyze($this->macroExpand($x, $nodeEnvironment), $nodeEnvironment);
        } else {
            $arguments = [];
            for ($i = 1; $i < count($x); $i++) {
                $arguments[] = $this->analyze($x[$i], $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
            }

            return new CallNode(
                $nodeEnvironment,
                $f,
                $arguments
            );
        }
    }

    protected function macroExpand(Tuple $x, NodeEnvironment $env) {
        $node = $this->globalEnvironment->resolve($x[0], $env);
        if ($node instanceof GlobalVarNode) {
            $fn = $GLOBALS['__phel'][$node->getNamespace()][$node->getName()->getName()]->get();

            $arguments = [];
            for ($i = 1; $i < count($x); $i++) {
                $arguments[] = $x[$i];
            }

            try {
                return $fn(...$arguments);
            } catch (Throwable $e) {
                throw new AnalyzerException(
                    'Error in expanding macro "' . $node->getNamespace() . '\\'. $node->getName()->getName() . '": ' . $e->getMessage(),
                    $x->getStartLocation(),
                    $x->getEndLocation(),
                    $e
                );
            }
            
        } else {
            throw new AnalyzerException(
                'This is not macro expandable: ' . $node,
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }
    }

    protected function analyzeLiteral($x, NodeEnvironment $env): LiteralNode {
        return new LiteralNode($env, $x);
    }

    protected function analyzeNs(Tuple $x, NodeEnvironment $env) {
        assert($x[1] instanceof Symbol, "Namespace must be of type Symbol");

        $this->globalEnvironment->setNs($x[1]->getName());

        $requireNs = [];
        for ($i = 2; $i < count($x); $i++) {
            $import = $x[$i];
            assert($import instanceof Tuple);

            if ($this->isKeywordWithName($import[0], 'use')) {
                assert($import[1] instanceof Symbol, 'First arugment in :use must be a symbol');

                if (count($import) == 2) {
                    $parts = explode('\\', $import[1]->getName());
                    $alias = new Symbol($parts[count($parts) - 1]);
                } else if (count($import) == 4 && $this->isKeywordWithName($import[2], 'as')) {
                    $alias = $import[3];
                }

                $this->globalEnvironment->addUseAlias($alias, $import[1]);
            } else if ($this->isKeywordWithName($import[0], 'require')) {
                $requireNs[] = $import[1];

                if (count($import) == 2) {
                    $parts = explode('\\', $import[1]->getName());
                    $alias = new Symbol($parts[count($parts) - 1]);
                } else if (count($import) == 4 && $this->isKeywordWithName($import[2], 'as')) {
                    $alias = $import[3];
                }

                $this->globalEnvironment->addRequireAlias($alias, $import[1]);
            }
        }

        return new NsNode($requireNs);
    }

    protected function analyzeDef(Tuple $x, NodeEnvironment $nodeEnvironment): DefNode {
        assert($x[1] instanceof Symbol, "Name of Def must be of type Symbol, got: " . print_r($x, true));
        assert($this->isValidPhelType($x[count($x)-1]), "Last attribute must be a Phel Type");
        assert(count($x) >= 3, "Def must have at least two arguments");
        // TODO: Check that it is called in global scope

        $namespace = $this->globalEnvironment->getNs();
        $name = $x[1];
        $meta = new Table();
        for ($i = 2; $i <= count($x) - 2; $i++) {
            $metaAttribute = $x[$i];
            assert(is_string($metaAttribute) || $metaAttribute instanceof Keyword, "Meta Attribute must be either a String or Keyword");
            
            if (is_string($metaAttribute)) {
                $meta[new Keyword('doc')] = $metaAttribute;
            } else {
                $meta[$metaAttribute] = true;
            }
        }
        $init = $x[count($x)-1];

        $this->globalEnvironment->addDefintion($namespace, $name, $meta);

        return new DefNode(
            $nodeEnvironment,
            $namespace,
            $name,
            $meta,
            $this->analyze($init, $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame())
        );
    }

    private function isSymWithName($x, $name) {
        return $x instanceof Symbol && $x->getName() == $name;
    }

    private function isKeywordWithName($x, $name) {
        return $x instanceof Keyword && $x->getName() == $name;
    }
}