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
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeTuple
{
    use WithAnalyzer;

    /** @throws AnalyzerException|PhelCodeException */
    public function __invoke(Tuple $x, NodeEnvironment $env): Node
    {
        if (!$x[0] instanceof Symbol) {
            return (new InvokeSymbol($this->analyzer))($x, $env);
        }

        switch ($x[0]->getFullName()) {
            case Symbol::NAME_DEF:
                return (new DefSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_NS:
                return (new NsSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_FN:
                return (new FnSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_QUOTE:
                return (new QuoteSymbol())($x, $env);
            case Symbol::NAME_DO:
                return (new DoSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_IF:
                return (new IfSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_APPLY:
                return (new ApplySymbol($this->analyzer))($x, $env);
            case Symbol::NAME_LET:
                return (new LetSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_PHP_NEW:
                return (new PhpNewSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_PHP_OBJECT_CALL:
                return (new PhpObjectCallSymbol($this->analyzer))($x, $env, false);
            case Symbol::NAME_PHP_OBJECT_STATIC_CALL:
                return (new PhpObjectCallSymbol($this->analyzer))($x, $env, true);
            case Symbol::NAME_PHP_ARRAY_GET:
                return (new PhpAGetSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_PHP_ARRAY_SET:
                return (new PhpASetSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_PHP_ARRAY_PUSH:
                return (new PhpAPushSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_PHP_ARRAY_UNSET:
                return (new PhpAUnsetSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_RECUR:
                return (new RecurSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_TRY:
                return (new TrySymbol($this->analyzer))($x, $env);
            case Symbol::NAME_THROW:
                return (new ThrowSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_LOOP:
                return (new LoopSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_FOREACH:
                return (new ForeachSymbol($this->analyzer))($x, $env);
            case Symbol::NAME_DEF_STRUCT:
                return (new DefStructSymbol($this->analyzer))($x, $env);
            default:
                return (new InvokeSymbol($this->analyzer))($x, $env);
        }
    }
}
