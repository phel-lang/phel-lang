<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ApplySymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefExceptionSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefInterfaceSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefStructSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DoSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\FnSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ForeachSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InNsSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InvokeSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LetSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LoadSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LoopSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\MethodBodyAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\NsSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAGetInSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAGetSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAPushInSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAPushSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpASetInSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpASetSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAUnsetInSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpAUnsetSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpNewSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpObjectCallSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpOSetSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\QuoteSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\RecurSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReifySymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SetVarSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SpecialFormAnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ThrowSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\TrySymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\UseSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function in_array;
use function str_ends_with;
use function strlen;
use function substr;

final class AnalyzePersistentList
{
    private const string EMPTY_SYMBOL_NAME = '';

    /** @var array<string, SpecialFormAnalyzerInterface> */
    private array $symbolAnalyzerCache = [];

    public function __construct(
        private readonly AnalyzerInterface $analyzer,
        private readonly bool $assertsEnabled,
    ) {}

    /**
     * @throws AbstractLocatedException|AnalyzerException
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($list) === 0) {
            // `()` is a self-quoting empty list literal — not an invocation
            // of a missing head. Matches Clojure/Janet and keeps forms like
            // `(into () ...)` or `(= () (list))` usable.
            return new QuoteNode($env, $list, $list->getStartLocation());
        }

        $list = $this->expandConstructorShorthand($list);
        $list = $this->expandMemberAccessShorthand($list);

        $symbolName = $this->getSymbolName($list);

        return $this
            ->createSymbolAnalyzerByName($symbolName)
            ->analyze($list, $env);
    }

    private function getSymbolName(PersistentListInterface $list): string
    {
        $first = $list->first();
        return $first instanceof Symbol
            ? $first->getFullName()
            : self::EMPTY_SYMBOL_NAME;
    }

    /**
     * Expands Clojure's `(ClassName. args)` shorthand to `(php/new ClassName args)`.
     *
     * Only triggers when the head symbol looks like a class reference ending
     * with `.`: the preceding character must be a PHP identifier character or
     * a namespace separator. This excludes operator-style symbols such as
     * `php/.` (string concatenation) and lone punctuation like `.` / `..`.
     * Preserves source locations so downstream errors still point at the
     * user's form.
     */
    private function expandConstructorShorthand(PersistentListInterface $list): PersistentListInterface
    {
        $first = $list->first();
        if (!$first instanceof Symbol) {
            return $list;
        }

        if (!$this->isConstructorShorthandName($first->getFullName())) {
            return $list;
        }

        $name = $first->getFullName();
        $className = substr($name, 0, -1);
        $newSymbol = Symbol::create(Symbol::NAME_PHP_NEW)->copyLocationFrom($first);
        $classSymbol = Symbol::create($className)->copyLocationFrom($first);

        $elements = [$newSymbol, $classSymbol];
        $count = count($list);
        for ($i = 1; $i < $count; ++$i) {
            $elements[] = $list->get($i);
        }

        return Phel::list($elements)->copyLocationFrom($list);
    }

    private function isConstructorShorthandName(string $name): bool
    {
        $len = strlen($name);
        if ($len < 2 || !str_ends_with($name, '.')) {
            return false;
        }

        $prev = $name[$len - 2];
        // Accept a-z, A-Z, 0-9, `_`, or `\` — i.e. valid trailing chars of a
        // PHP class reference. Rejects `php/.`, `..`, `:.`, etc.
        return ($prev >= 'a' && $prev <= 'z')
            || ($prev >= 'A' && $prev <= 'Z')
            || ($prev >= '0' && $prev <= '9')
            || $prev === '_'
            || $prev === '\\';
    }

    /**
     * Expands Clojure-style member access shorthand:
     *   `(.method obj args...)`       -> `(php/-> obj (method args...))`
     *   `(.-field obj)`               -> `(php/-> obj field)`
     *   `(Classname/method args...)`  -> `(php/:: Classname (method args...))`
     *
     * Only triggers when the head symbol starts with `.` followed by a valid
     * PHP identifier start character, or when the head symbol's namespace
     * looks like a PHP class reference (uppercase first letter or `\` prefix).
     * Operator-style symbols like `php/.`, `.` and `..` are left alone, and
     * Phel-style namespaces (lowercase first letter) resolve as before.
     */
    private function expandMemberAccessShorthand(PersistentListInterface $list): PersistentListInterface
    {
        $first = $list->first();
        if (!$first instanceof Symbol) {
            return $list;
        }

        $name = $first->getFullName();
        $count = count($list);

        if ($this->isPropertyAccessShorthandName($name) && $count === 2) {
            $callSymbol = Symbol::create(Symbol::NAME_PHP_OBJECT_CALL)->copyLocationFrom($first);
            $instance = $list->get(1);
            $propertySymbol = Symbol::create(substr($name, 2))->copyLocationFrom($first);

            return Phel::list([$callSymbol, $instance, $propertySymbol])->copyLocationFrom($list);
        }

        if ($this->isMethodCallShorthandName($name) && $count >= 2) {
            $callSymbol = Symbol::create(Symbol::NAME_PHP_OBJECT_CALL)->copyLocationFrom($first);
            $instance = $list->get(1);
            $methodSymbol = Symbol::create(substr($name, 1))->copyLocationFrom($first);

            $methodSegment = [$methodSymbol];
            for ($i = 2; $i < $count; ++$i) {
                $methodSegment[] = $list->get($i);
            }

            return Phel::list([
                $callSymbol,
                $instance,
                Phel::list($methodSegment)->copyLocationFrom($first),
            ])->copyLocationFrom($list);
        }

        if ($this->isStaticCallShorthand($first)) {
            $staticSymbol = Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL)->copyLocationFrom($first);
            $classSymbol = Symbol::create((string) $first->getNamespace())->copyLocationFrom($first);
            $methodSymbol = Symbol::create($first->getName())->copyLocationFrom($first);

            $methodSegment = [$methodSymbol];
            for ($i = 1; $i < $count; ++$i) {
                $methodSegment[] = $list->get($i);
            }

            return Phel::list([
                $staticSymbol,
                $classSymbol,
                Phel::list($methodSegment)->copyLocationFrom($first),
            ])->copyLocationFrom($list);
        }

        return $list;
    }

    private function isStaticCallShorthand(Symbol $symbol): bool
    {
        $ns = $symbol->getNamespace();
        if (in_array($ns, [null, '', 'php'], true)) {
            return false;
        }

        $method = $symbol->getName();
        if ($method === '' || !$this->isIdentifierStartChar($method[0])) {
            return false;
        }

        return $ns[0] === '\\'
            || ($ns[0] >= 'A' && $ns[0] <= 'Z');
    }

    private function isMethodCallShorthandName(string $name): bool
    {
        $len = strlen($name);
        if ($len < 2 || $name[0] !== '.' || $name[1] === '.' || $name[1] === '-') {
            return false;
        }

        return $this->isIdentifierStartChar($name[1]);
    }

    private function isPropertyAccessShorthandName(string $name): bool
    {
        $len = strlen($name);
        if ($len < 3 || $name[0] !== '.' || $name[1] !== '-') {
            return false;
        }

        return $this->isIdentifierStartChar($name[2]);
    }

    private function isIdentifierStartChar(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z')
            || ($c >= 'A' && $c <= 'Z')
            || $c === '_';
    }

    private function createSymbolAnalyzerByName(string $symbolName): SpecialFormAnalyzerInterface
    {
        if (isset($this->symbolAnalyzerCache[$symbolName])) {
            return $this->symbolAnalyzerCache[$symbolName];
        }

        $analyzer = match ($symbolName) {
            Symbol::NAME_DEF => new DefSymbol($this->analyzer),
            Symbol::NAME_NS => new NsSymbol($this->analyzer),
            Symbol::NAME_IN_NS => new InNsSymbol($this->analyzer),
            Symbol::NAME_USE => new UseSymbol($this->analyzer),
            Symbol::NAME_LOAD => new LoadSymbol($this->analyzer),
            Symbol::NAME_FN => new FnSymbol($this->analyzer, $this->assertsEnabled),
            Symbol::NAME_QUOTE => new QuoteSymbol(),
            Symbol::NAME_DO => new DoSymbol($this->analyzer),
            Symbol::NAME_IF => new IfSymbol($this->analyzer),
            Symbol::NAME_APPLY => new ApplySymbol($this->analyzer),
            Symbol::NAME_LET => new LetSymbol($this->analyzer, new Deconstructor(new BindingValidator())),
            Symbol::NAME_PHP_NEW, Symbol::NAME_NEW => new PhpNewSymbol($this->analyzer),
            Symbol::NAME_PHP_OBJECT_CALL => new PhpObjectCallSymbol($this->analyzer, isStatic: false),
            Symbol::NAME_PHP_OBJECT_STATIC_CALL => new PhpObjectCallSymbol($this->analyzer, isStatic: true),
            Symbol::NAME_PHP_ARRAY_GET => new PhpAGetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_GET_IN => new PhpAGetInSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_SET => new PhpASetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_SET_IN => new PhpASetInSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_PUSH => new PhpAPushSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_PUSH_IN => new PhpAPushInSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_UNSET => new PhpAUnsetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_UNSET_IN => new PhpAUnsetInSymbol($this->analyzer),
            Symbol::NAME_RECUR => new RecurSymbol($this->analyzer),
            Symbol::NAME_TRY => new TrySymbol($this->analyzer),
            Symbol::NAME_THROW => new ThrowSymbol($this->analyzer),
            Symbol::NAME_LOOP => new LoopSymbol($this->analyzer, new BindingValidator()),
            Symbol::NAME_FOREACH => new ForeachSymbol($this->analyzer),
            Symbol::NAME_DEF_STRUCT => new DefStructSymbol($this->analyzer, new Munge(), new MethodBodyAnalyzer($this->analyzer)),
            Symbol::NAME_DEF_EXCEPTION => new DefExceptionSymbol($this->analyzer),
            Symbol::NAME_PHP_OBJECT_SET => new PhpOSetSymbol($this->analyzer),
            Symbol::NAME_SET_VAR => new SetVarSymbol($this->analyzer),
            Symbol::NAME_DEF_INTERFACE => new DefInterfaceSymbol($this->analyzer),
            Symbol::NAME_REIFY => new ReifySymbol(new MethodBodyAnalyzer($this->analyzer)),
            default => new InvokeSymbol($this->analyzer),
        };

        $this->symbolAnalyzerCache[$symbolName] = $analyzer;

        return $analyzer;
    }
}
