<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Analyzer\TupleSymbol\ApplySymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\DefStructSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\DefSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\DoSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\FnSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\ForeachSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\IfSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\InvokeSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\LetSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\LoopSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\NsSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\PhpAGetSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\PhpAPushSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\PhpASetSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\PhpAUnsetSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\PhpNewSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\PhpObjectCallSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\QuoteSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\RecurSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\ThrowSymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\TrySymbolInterface;
use Phel\Compiler\Analyzer\TupleSymbol\TupleSymbolAnalyzerInterface;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class AnalyzeTuple
{
    use WithAnalyzerTrait;

    private const EMPTY_SYMBOL_NAME = '';

    /**
     * @throws AnalyzerException|PhelCodeException
     */
    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): AbstractNode
    {
        $symbolName = $this->getSymbolName($tuple);
        $symbol = $this->createSymbolAnalyzerByName($symbolName);

        return $symbol->analyze($tuple, $env);
    }

    private function getSymbolName(Tuple $tuple): string
    {
        return isset($tuple[0]) && $tuple[0] instanceof Symbol
            ? $tuple[0]->getFullName()
            : self::EMPTY_SYMBOL_NAME;
    }

    private function createSymbolAnalyzerByName(string $symbolName): TupleSymbolAnalyzerInterface
    {
        switch ($symbolName) {
            case Symbol::NAME_DEF:
                return new DefSymbolInterface($this->analyzer);
            case Symbol::NAME_NS:
                return new NsSymbolInterface($this->analyzer);
            case Symbol::NAME_FN:
                return new FnSymbolInterface($this->analyzer);
            case Symbol::NAME_QUOTE:
                return new QuoteSymbolInterface();
            case Symbol::NAME_DO:
                return new DoSymbolInterface($this->analyzer);
            case Symbol::NAME_IF:
                return new IfSymbolInterface($this->analyzer);
            case Symbol::NAME_APPLY:
                return new ApplySymbolInterface($this->analyzer);
            case Symbol::NAME_LET:
                return new LetSymbolInterface($this->analyzer, new TupleDeconstructor(new BindingValidator()));
            case Symbol::NAME_PHP_NEW:
                return new PhpNewSymbolInterface($this->analyzer);
            case Symbol::NAME_PHP_OBJECT_CALL:
                return new PhpObjectCallSymbolInterface($this->analyzer, $isStatic = false);
            case Symbol::NAME_PHP_OBJECT_STATIC_CALL:
                return new PhpObjectCallSymbolInterface($this->analyzer, $isStatic = true);
            case Symbol::NAME_PHP_ARRAY_GET:
                return new PhpAGetSymbolInterface($this->analyzer);
            case Symbol::NAME_PHP_ARRAY_SET:
                return new PhpASetSymbolInterface($this->analyzer);
            case Symbol::NAME_PHP_ARRAY_PUSH:
                return new PhpAPushSymbolInterface($this->analyzer);
            case Symbol::NAME_PHP_ARRAY_UNSET:
                return new PhpAUnsetSymbolInterface($this->analyzer);
            case Symbol::NAME_RECUR:
                return new RecurSymbolInterface($this->analyzer);
            case Symbol::NAME_TRY:
                return new TrySymbolInterface($this->analyzer);
            case Symbol::NAME_THROW:
                return new ThrowSymbolInterface($this->analyzer);
            case Symbol::NAME_LOOP:
                return new LoopSymbolInterface($this->analyzer, new BindingValidator());
            case Symbol::NAME_FOREACH:
                return new ForeachSymbolInterface($this->analyzer);
            case Symbol::NAME_DEF_STRUCT:
                return new DefStructSymbolInterface($this->analyzer);
            default:
                return new InvokeSymbolInterface($this->analyzer);
        }
    }
}
