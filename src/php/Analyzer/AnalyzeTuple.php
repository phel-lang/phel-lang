<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer\TupleSymbol;
use Phel\Ast\Node;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeTuple
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): Node
    {
        if (!$x[0] instanceof Symbol) {
            return (new TupleSymbol\InvokeSymbol($this->analyzer))($x, $env);
        }

        switch ($x[0]->getName()) {
            case 'def':
                return (new TupleSymbol\DefSymbol($this->analyzer))($x, $env);
            case 'ns':
                return (new TupleSymbol\NsSymbol($this->analyzer))($x, $env);
            case 'fn':
                return (new TupleSymbol\FnSymbol($this->analyzer))($x, $env);
            case 'quote':
                return (new TupleSymbol\QuoteSymbol())($x, $env);
            case 'do':
                return (new TupleSymbol\DoSymbol($this->analyzer))($x, $env);
            case 'if':
                return (new TupleSymbol\IfSymbol($this->analyzer))($x, $env);
            case 'apply':
                return (new TupleSymbol\ApplySymbol($this->analyzer))($x, $env);
            case 'let':
                return (new TupleSymbol\LetSymbol($this->analyzer))($x, $env);
            case 'php/new':
                return (new TupleSymbol\PhpNewSymbol($this->analyzer))($x, $env);
            case 'php/->':
                return (new TupleSymbol\PhpObjectCallSymbol($this->analyzer))($x, $env, false);
            case 'php/::':
                return (new TupleSymbol\PhpObjectCallSymbol($this->analyzer))($x, $env, true);
            case 'php/aget':
                return (new TupleSymbol\PhpAGetSymbol($this->analyzer))($x, $env);
            case 'php/aset':
                return (new TupleSymbol\PhpASetSymbol($this->analyzer))($x, $env);
            case 'php/apush':
                return (new TupleSymbol\PhpAPushSymbol($this->analyzer))($x, $env);
            case 'php/aunset':
                return (new TupleSymbol\PhpAUnsetSymbol($this->analyzer))($x, $env);
            case 'recur':
                return (new TupleSymbol\RecurSymbol($this->analyzer))($x, $env);
            case 'try':
                return (new TupleSymbol\TrySymbol($this->analyzer))($x, $env);
            case 'throw':
                return (new TupleSymbol\ThrowSymbol($this->analyzer))($x, $env);
            case 'loop':
                return (new TupleSymbol\LoopSymbol($this->analyzer))($x, $env);
            case 'foreach':
                return (new TupleSymbol\ForeachSymbol($this->analyzer))($x, $env);
            case 'defstruct*':
                return (new TupleSymbol\DefStructSymbol($this->analyzer))($x, $env);
            default:
                return (new TupleSymbol\InvokeSymbol($this->analyzer))($x, $env);
        }
    }
}
