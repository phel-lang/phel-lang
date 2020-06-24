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

    public const NAME_DEF = 'def';
    public const NAME_NS = 'ns';
    public const NAME_FN = 'fn';
    public const NAME_QUOTE = 'quote';
    public const NAME_DO = 'do';
    public const NAME_IF = 'if';
    public const NAME_APPLY = 'apply';
    public const NAME_LET = 'let';
    public const NAME_PHP_NEW = 'php/new';
    public const NAME_PHP_OBJECT_CALL = 'php/->';
    public const NAME_PHP_OBJECT_STATIC_CALL = 'php/::';
    public const NAME_PHP_ARRAY_GET = 'php/aget';
    public const NAME_PHP_ARRAY_SET = 'php/aset';
    public const NAME_PHP_ARRAY_PUSH = 'php/apush';
    public const NAME_PHP_ARRAY_UNSET = 'php/aunset';
    public const NAME_RECUR = 'recur';
    public const NAME_TRY = 'try';
    public const NAME_THROW = 'throw';
    public const NAME_LOOP = 'loop';
    public const NAME_FOREACH = 'foreach';
    public const NAME_DEFSTRUCT = 'defstruct*';

    /** @throws AnalyzerException|PhelCodeException */
    public function __invoke(Tuple $x, NodeEnvironment $env): Node
    {
        if (!$x[0] instanceof Symbol) {
            return (new InvokeSymbol($this->analyzer))($x, $env);
        }

        switch ($x[0]->getFullName()) {
            case self::NAME_DEF:
                return (new DefSymbol($this->analyzer))($x, $env);
            case self::NAME_NS:
                return (new NsSymbol($this->analyzer))($x, $env);
            case self::NAME_FN:
                return (new FnSymbol($this->analyzer))($x, $env);
            case self::NAME_QUOTE:
                return (new QuoteSymbol())($x, $env);
            case self::NAME_DO:
                return (new DoSymbol($this->analyzer))($x, $env);
            case self::NAME_IF:
                return (new IfSymbol($this->analyzer))($x, $env);
            case self::NAME_APPLY:
                return (new ApplySymbol($this->analyzer))($x, $env);
            case self::NAME_LET:
                return (new LetSymbol($this->analyzer))($x, $env);
            case self::NAME_PHP_NEW:
                return (new PhpNewSymbol($this->analyzer))($x, $env);
            case self::NAME_PHP_OBJECT_CALL:
                return (new PhpObjectCallSymbol($this->analyzer))($x, $env, false);
            case self::NAME_PHP_OBJECT_STATIC_CALL:
                return (new PhpObjectCallSymbol($this->analyzer))($x, $env, true);
            case self::NAME_PHP_ARRAY_GET:
                return (new PhpAGetSymbol($this->analyzer))($x, $env);
            case self::NAME_PHP_ARRAY_SET:
                return (new PhpASetSymbol($this->analyzer))($x, $env);
            case self::NAME_PHP_ARRAY_PUSH:
                return (new PhpAPushSymbol($this->analyzer))($x, $env);
            case self::NAME_PHP_ARRAY_UNSET:
                return (new PhpAUnsetSymbol($this->analyzer))($x, $env);
            case self::NAME_RECUR:
                return (new RecurSymbol($this->analyzer))($x, $env);
            case self::NAME_TRY:
                return (new TrySymbol($this->analyzer))($x, $env);
            case self::NAME_THROW:
                return (new ThrowSymbol($this->analyzer))($x, $env);
            case self::NAME_LOOP:
                return (new LoopSymbol($this->analyzer))($x, $env);
            case self::NAME_FOREACH:
                return (new ForeachSymbol($this->analyzer))($x, $env);
            case self::NAME_DEFSTRUCT:
                return (new DefStructSymbol($this->analyzer))($x, $env);
            default:
                return (new InvokeSymbol($this->analyzer))($x, $env);
        }
    }
}
