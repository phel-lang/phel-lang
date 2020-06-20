<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer;
use Phel\Analyzer\AnalyzeTuple\AnalyzeApply;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDef;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDefStruct;
use Phel\Analyzer\AnalyzeTuple\AnalyzeDo;
use Phel\Analyzer\AnalyzeTuple\AnalyzeFn;
use Phel\Analyzer\AnalyzeTuple\AnalyzeForeach;
use Phel\Analyzer\AnalyzeTuple\AnalyzeIf;
use Phel\Analyzer\AnalyzeTuple\AnalyzeInvoke;
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
use Phel\Ast\Node;
use Phel\Ast\PhelArrayNode;
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
            return (new AnalyzeInvoke($this->analyzer))($x, $env);
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
                return (new AnalyzeDefStruct($this->analyzer))($x, $env);
            default:
                return (new AnalyzeInvoke($this->analyzer))($x, $env);
        }
    }

    private function analyzePhelArray(PhelArray $x, NodeEnvironment $env): PhelArrayNode
    {
        $args = [];
        foreach ($x as $arg) {
            $args[] = $this->analyzer->analyze($arg,
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new PhelArrayNode($env, $args, $x->getStartLocation());
    }
}
