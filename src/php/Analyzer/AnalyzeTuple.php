<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer\TupleSymbol\ApplySymbol;
use Phel\Analyzer\TupleSymbol\DefStructSymbol;
use Phel\Analyzer\TupleSymbol\DefSymbol;
use Phel\Analyzer\TupleSymbol\DoSymbol;
use Phel\Analyzer\TupleSymbol\FnSymbol;
use Phel\Analyzer\TupleSymbol\ForeachSymbol;
use Phel\Analyzer\TupleSymbol\IfSymbol;
use Phel\Analyzer\TupleSymbol\InvokeSymbol;
use Phel\Analyzer\TupleSymbol\LetSymbol;
use Phel\Analyzer\TupleSymbol\LoopSymbol;
use Phel\Analyzer\TupleSymbol\NsSymbol;
use Phel\Analyzer\TupleSymbol\PhpAGetSymbol;
use Phel\Analyzer\TupleSymbol\PhpAPushSymbol;
use Phel\Analyzer\TupleSymbol\PhpASetSymbol;
use Phel\Analyzer\TupleSymbol\PhpAUnsetSymbol;
use Phel\Analyzer\TupleSymbol\PhpNewSymbol;
use Phel\Analyzer\TupleSymbol\PhpObjectCallSymbol;
use Phel\Analyzer\TupleSymbol\QuoteSymbol;
use Phel\Analyzer\TupleSymbol\RecurSymbol;
use Phel\Analyzer\TupleSymbol\ThrowSymbol;
use Phel\Analyzer\TupleSymbol\TrySymbol;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeTuple
{
    use WithAnalyzer;

    /** @throws AnalyzerException */
    public function __invoke(Tuple $x, NodeEnvironment $env): Node
    {
        if (!$x[0] instanceof Symbol) {
            return (new InvokeSymbol($this->analyzer))($x, $env);
        }

        switch ($x[0]->getFullName()) {
            case 'def':
                return (new DefSymbol($this->analyzer))($x, $env);
            case 'ns':
                return (new NsSymbol($this->analyzer))($x, $env);
            case 'fn':
                return (new FnSymbol($this->analyzer))($x, $env);
            case 'quote':
                return (new QuoteSymbol())($x, $env);
            case 'do':
                return (new DoSymbol($this->analyzer))($x, $env);
            case 'if':
                return (new IfSymbol($this->analyzer))($x, $env);
            case 'apply':
                return (new ApplySymbol($this->analyzer))($x, $env);
            case 'let':
                return (new LetSymbol($this->analyzer))($x, $env);
            case 'php/new':
                return (new PhpNewSymbol($this->analyzer))($x, $env);
            case 'php/->':
                return (new PhpObjectCallSymbol($this->analyzer))($x, $env, false);
            case 'php/::':
                return (new PhpObjectCallSymbol($this->analyzer))($x, $env, true);
            case 'php/aget':
                return (new PhpAGetSymbol($this->analyzer))($x, $env);
            case 'php/aset':
                return (new PhpASetSymbol($this->analyzer))($x, $env);
            case 'php/apush':
                return (new PhpAPushSymbol($this->analyzer))($x, $env);
            case 'php/aunset':
                return (new PhpAUnsetSymbol($this->analyzer))($x, $env);
            case 'recur':
                return (new RecurSymbol($this->analyzer))($x, $env);
            case 'try':
                return (new TrySymbol($this->analyzer))($x, $env);
            case 'throw':
                return (new ThrowSymbol($this->analyzer))($x, $env);
            case 'loop':
                return (new LoopSymbol($this->analyzer))($x, $env);
            case 'foreach':
                return (new ForeachSymbol($this->analyzer))($x, $env);
            case 'defstruct*':
                return (new DefStructSymbol($this->analyzer))($x, $env);
            default:
                return (new InvokeSymbol($this->analyzer))($x, $env);
        }
    }
}
