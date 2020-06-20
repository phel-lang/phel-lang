<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Exception;
use Phel\Analyzer;
use Phel\Analyzer\AnalyzeTuple\AnalyzeApply;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDef;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDo;
use Phel\Analyzer\AnalyzeTuple\AnalyzeFn;
use Phel\Analyzer\AnalyzeTuple\AnalyzeIf;
use Phel\Analyzer\AnalyzeTuple\AnalyzeLet;
use Phel\Analyzer\AnalyzeTuple\AnalyzeNs;
use Phel\Analyzer\AnalyzeTuple\AnalyzePhpAGet;
use Phel\Analyzer\AnalyzeTuple\AnalyzePhpAPush;
use Phel\Analyzer\AnalyzeTuple\AnalyzePhpASet;
use Phel\Analyzer\AnalyzeTuple\AnalyzePhpAUnset;
use Phel\Analyzer\AnalyzeTuple\AnalyzePhpNew;
use Phel\Analyzer\AnalyzeTuple\AnalyzePhpObjectCall;
use Phel\Analyzer\AnalyzeTuple\AnalyzeQuote;
use Phel\Analyzer\AnalyzeTuple\AnalyzeRecur;
use Phel\Analyzer\AnalyzeTuple\AnalyzeThrow;
use Phel\Analyzer\AnalyzeTuple\AnalyzeTry;
use Phel\Ast\BindingNode;
use Phel\Ast\CallNode;
use Phel\Ast\DefStructNode;
use Phel\Ast\ForeachNode;
use Phel\Ast\GlobalVarNode;
use Phel\Ast\LetNode;
use Phel\Ast\Node;
use Phel\Ast\PhelArrayNode;
use Phel\Destructure;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
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
                return (new AnalyzeDef($this->analyzer))($x, $env);
            case 'ns':
                return (new AnalyzeNs($this->analyzer))($x, $env);
            case 'fn':
                return (new AnalyzeFn($this->analyzer))($x, $env);
            case 'quote':
                return (new AnalyzeQuote())($x, $env);
            case 'do':
                return (new AnalyzeDo($this->analyzer))($x, $env);
            case 'if':
                return (new AnalyzeIf($this->analyzer))($x, $env);
            case 'apply':
                return (new AnalyzeApply($this->analyzer))($x, $env);
            case 'let':
                return (new AnalyzeLet($this->analyzer))($x, $env);
            case 'php/new':
                return (new AnalyzePhpNew($this->analyzer))($x, $env);
            case 'php/->':
                return (new AnalyzePhpObjectCall($this->analyzer))($x, $env, false);
            case 'php/::':
                return (new AnalyzePhpObjectCall($this->analyzer))($x, $env, true);
            case 'php/aget':
                return (new AnalyzePhpAGet($this->analyzer))($x, $env);
            case 'php/aset':
                return (new AnalyzePhpASet($this->analyzer))($x, $env);
            case 'php/apush':
                return (new AnalyzePhpAPush($this->analyzer))($x, $env);
            case 'php/aunset':
                return (new AnalyzePhpAUnset($this->analyzer))($x, $env);
            case 'recur':
                return (new AnalyzeRecur($this->analyzer))($x, $env);
            case 'try':
                return (new AnalyzeTry($this->analyzer))($x, $env);
            case 'throw':
                return (new AnalyzeThrow($this->analyzer))($x, $env);
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

    /** @return AbstractType|scalar|null */
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
     * @param AbstractType $parent
     */
    private function enrichLocation($x, AbstractType $parent): void
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
        } elseif ($x instanceof AbstractType) {
            if (!$x->getStartLocation()) {
                $x->setStartLocation($parent->getStartLocation());
            }
            if (!$x->getEndLocation()) {
                $x->setEndLocation($parent->getEndLocation());
            }
        }
    }


    private function analyzeLoop(Tuple $x, NodeEnvironment $env): LetNode
    {
        $tupleCount = count($x);
        if (!($x[0] instanceof Symbol && $x[0] == 'loop')) {
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
                'Binding parameter must be a tuple.',
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!(count($x[1]) % 2 === 0)) {
            throw new AnalyzerException(
                'Bindings must be a even number of parameters',
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

    private function analyzeLetOrLoop(Tuple $x, NodeEnvironment $env, bool $isLoop = false): LetNode
    {
        $tupleCount = count($x);
        $exprs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $exprs[] = $x[$i];
        }

        /** @psalm-suppress PossiblyNullArgument */
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

    /** @return BindingNode[] */
    private function analyzeBindings(Tuple $x, NodeEnvironment $env): array
    {
        $tupleCount = count($x);
        $initEnv = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < $tupleCount; $i += 2) {
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
            $init = $x[$i + 1];

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
                    'Defstruct field elements must by Symbols.',
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
