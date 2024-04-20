<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ApplySymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefInterfaceSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefStructSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DoSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\FnSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ForeachSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InvokeSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LetSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LoopSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\NsSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAGetSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAPushSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpASetSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAUnsetSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpNewSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpObjectCallSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpOSetSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\QuoteSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\RecurSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SetVarSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SpecialFormAnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ThrowSymbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\TrySymbol;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Transpiler\Infrastructure\Munge;

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
        return $first instanceof Symbol
            ? $first->getFullName()
            : self::EMPTY_SYMBOL_NAME;
    }

    private function createSymbolAnalyzerByName(string $symbolName): SpecialFormAnalyzerInterface
    {
        return match ($symbolName) {
            Symbol::NAME_DEF => new DefSymbol($this->analyzer),
            Symbol::NAME_NS => new NsSymbol($this->analyzer),
            Symbol::NAME_FN => new FnSymbol($this->analyzer),
            Symbol::NAME_QUOTE => new QuoteSymbol(),
            Symbol::NAME_DO => new DoSymbol($this->analyzer),
            Symbol::NAME_IF => new IfSymbol($this->analyzer),
            Symbol::NAME_APPLY => new ApplySymbol($this->analyzer),
            Symbol::NAME_LET => new LetSymbol($this->analyzer, new Deconstructor(new BindingValidator())),
            Symbol::NAME_PHP_NEW => new PhpNewSymbol($this->analyzer),
            Symbol::NAME_PHP_OBJECT_CALL => new PhpObjectCallSymbol($this->analyzer, isStatic: false),
            Symbol::NAME_PHP_OBJECT_STATIC_CALL => new PhpObjectCallSymbol($this->analyzer, isStatic: true),
            Symbol::NAME_PHP_ARRAY_GET => new PhpAGetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_SET => new PhpASetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_PUSH => new PhpAPushSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_UNSET => new PhpAUnsetSymbol($this->analyzer),
            Symbol::NAME_RECUR => new RecurSymbol($this->analyzer),
            Symbol::NAME_TRY => new TrySymbol($this->analyzer),
            Symbol::NAME_THROW => new ThrowSymbol($this->analyzer),
            Symbol::NAME_LOOP => new LoopSymbol($this->analyzer, new BindingValidator()),
            Symbol::NAME_FOREACH => new ForeachSymbol($this->analyzer),
            Symbol::NAME_DEF_STRUCT => new DefStructSymbol($this->analyzer, new Munge()),
            Symbol::NAME_PHP_OBJECT_SET => new PhpOSetSymbol($this->analyzer),
            Symbol::NAME_SET_VAR => new SetVarSymbol($this->analyzer),
            Symbol::NAME_DEF_INTERFACE => new DefInterfaceSymbol($this->analyzer),
            default => new InvokeSymbol($this->analyzer),
        };
    }
}
