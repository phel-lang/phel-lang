<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Exception;
use Phel\Analyzer;
use Phel\Analyzer\AnalyzeTuple\AnalyzeApply;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDef;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDo;
use Phel\Analyzer\AnalyzeTuple\AnalyzeFn;
use Phel\Analyzer\AnalyzeTuple\AnalyzeForeach;
use Phel\Analyzer\AnalyzeTuple\AnalyzeIf;
use Phel\Analyzer\AnalyzeTuple\AnalyzeLet;
use Phel\Analyzer\AnalyzeTuple\AnalyzeLoop;
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
use Phel\Ast\CallNode;
use Phel\Ast\DefStructNode;
use Phel\Ast\GlobalVarNode;
use Phel\Ast\Node;
use Phel\Ast\PhelArrayNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

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
                return (new AnalyzeLoop($this->analyzer))($x, $env);
            case 'foreach':
                return (new AnalyzeForeach($this->analyzer))($x, $env);
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
