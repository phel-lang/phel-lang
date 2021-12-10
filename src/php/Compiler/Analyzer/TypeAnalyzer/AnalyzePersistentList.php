<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\ApplySymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DefInterfaceSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DefStructSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DoSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\FnSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\ForeachSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\InvokeSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\LetSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\LoopSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\NsSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpAGetSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpAPushSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpASetSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpAUnsetSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpNewSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpObjectCallSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\PhpOSetSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\QuoteSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\RecurSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\SetVarSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\SpecialFormAnalyzerInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\ThrowSymbol;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\TrySymbol;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

final class AnalyzePersistentList
{
    use WithAnalyzerTrait;

    private const EMPTY_SYMBOL_NAME = '';

    /**
     * @throws AnalyzerException|AbstractLocatedException
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $symbolName = $this->getSymbolName($list);
        $symbol = $this->createSymbolAnalyzerByName($symbolName);

        return $symbol->analyze($list, $env);
    }

    private function getSymbolName(PersistentListInterface $list): string
    {
        $first = $list->first();
        return $first && $first instanceof Symbol
            ? $first->getFullName()
            : self::EMPTY_SYMBOL_NAME;
    }

    private function createSymbolAnalyzerByName(string $symbolName): SpecialFormAnalyzerInterface
    {
        switch ($symbolName) {
            case Symbol::NAME_DEF:
                return new DefSymbol($this->analyzer);
            case Symbol::NAME_NS:
                return new NsSymbol($this->analyzer);
            case Symbol::NAME_FN:
                return new FnSymbol($this->analyzer);
            case Symbol::NAME_QUOTE:
                return new QuoteSymbol();
            case Symbol::NAME_DO:
                return new DoSymbol($this->analyzer);
            case Symbol::NAME_IF:
                return new IfSymbol($this->analyzer);
            case Symbol::NAME_APPLY:
                return new ApplySymbol($this->analyzer);
            case Symbol::NAME_LET:
                return new LetSymbol($this->analyzer, new Deconstructor(new BindingValidator()));
            case Symbol::NAME_PHP_NEW:
                return new PhpNewSymbol($this->analyzer);
            case Symbol::NAME_PHP_OBJECT_CALL:
                return new PhpObjectCallSymbol($this->analyzer, $isStatic = false);
            case Symbol::NAME_PHP_OBJECT_STATIC_CALL:
                return new PhpObjectCallSymbol($this->analyzer, $isStatic = true);
            case Symbol::NAME_PHP_ARRAY_GET:
                return new PhpAGetSymbol($this->analyzer);
            case Symbol::NAME_PHP_ARRAY_SET:
                return new PhpASetSymbol($this->analyzer);
            case Symbol::NAME_PHP_ARRAY_PUSH:
                return new PhpAPushSymbol($this->analyzer);
            case Symbol::NAME_PHP_ARRAY_UNSET:
                return new PhpAUnsetSymbol($this->analyzer);
            case Symbol::NAME_RECUR:
                return new RecurSymbol($this->analyzer);
            case Symbol::NAME_TRY:
                return new TrySymbol($this->analyzer);
            case Symbol::NAME_THROW:
                return new ThrowSymbol($this->analyzer);
            case Symbol::NAME_LOOP:
                return new LoopSymbol($this->analyzer, new BindingValidator());
            case Symbol::NAME_FOREACH:
                return new ForeachSymbol($this->analyzer);
            case Symbol::NAME_DEF_STRUCT:
                return new DefStructSymbol($this->analyzer, new Munge());
            case Symbol::NAME_PHP_OBJECT_SET:
                return new PhpOSetSymbol($this->analyzer);
            case Symbol::NAME_SET_VAR:
                return new SetVarSymbol($this->analyzer);
            case Symbol::NAME_DEF_INTERFACE:
                return new DefInterfaceSymbol($this->analyzer);
            default:
                return new InvokeSymbol($this->analyzer);
        }
    }
}
