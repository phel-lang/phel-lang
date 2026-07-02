<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ApplySymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\BreakSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefEnumSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefExceptionSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefInterfaceSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefStructSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DoSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\FnSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ForeachSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InNsSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InterfaceImplementationsAnalyzer;
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
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpBlockAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpCallableSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpNewSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpObjectCallSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpOSetSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpRefSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\QuoteSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\RecurSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReifySymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SetVarSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SpecialFormAnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ThrowSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\TrySymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\UseSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\VarSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Munge;
use Phel\Shared\MungeInterface;

use function array_keys;
use function count;
use function in_array;
use function str_ends_with;
use function strlen;
use function substr;

final class AnalyzePersistentList
{
    /** @var array<string, SpecialFormAnalyzerInterface> */
    private array $symbolAnalyzerCache = [];

    /** @var array<string, callable(): SpecialFormAnalyzerInterface>|null */
    private ?array $specialFormFactories = null;

    public function __construct(
        private readonly AnalyzerInterface $analyzer,
        private readonly bool $assertsEnabled,
        private readonly MungeInterface $munge = new Munge(),
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @throws AbstractLocatedException|AnalyzerException
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($list) === 0) {
            // `()` is a self-quoting empty list literal — not an invocation
            // of a missing head. Matches Clojure and keeps forms like
            // `(into () ...)` or `(= () (list))` usable.
            return new QuoteNode($env, $list, $list->getStartLocation());
        }

        $list = $this->expandConstructorShorthand($list, $env);
        $list = $this->expandMemberAccessShorthand($list);

        $symbolName = $this->getSymbolName($list);

        return $this
            ->createSymbolAnalyzerByName($symbolName)
            ->analyze($list, $env);
    }

    /**
     * The list heads that dispatch to a dedicated special-form analyzer
     * (everything else falls through to {@see InvokeSymbol}). This is the single
     * enumerable source of truth for "which special forms exist" — useful for
     * docs, REPL completion and tooling.
     *
     * @return list<string>
     */
    public function specialFormNames(): array
    {
        return array_keys($this->specialFormFactories());
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function getSymbolName(PersistentListInterface $list): string
    {
        $first = $list->first();
        return $first instanceof Symbol
            ? $first->getFullName()
            : '';
    }

    /**
     * Expands the `(ClassName. args)` shorthand.
     *
     * - Bare PHP class references rewrite to `(php/new ClassName args)`.
     * - When `ClassName` resolves to a Phel definition (the constructor
     *   wrapper generated by `defstruct`/`defrecord`/`deftype`/`defexception`)
     *   it rewrites to a direct call `(ClassName args)`. Without this the
     *   `php/new` path would `new` the Phel function value itself and
     *   yield another function instance instead of a struct.
     *
     * Only triggers when the head symbol ends with `.` and the preceding
     * character is a PHP identifier character or namespace separator, so
     * operator-style symbols like `php/.`, `..`, `:.`, … are left alone.
     * Source locations are preserved.
     */
    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentListInterface<mixed>
     */
    private function expandConstructorShorthand(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
    ): PersistentListInterface {
        $first = $list->first();
        if (!$first instanceof Symbol) {
            return $list;
        }

        if (!$this->isConstructorShorthandName($first->getFullName())) {
            return $list;
        }

        $name = $first->getFullName();
        $className = substr($name, 0, -1);
        $classSymbol = Symbol::create($className)->copyLocationFrom($first);

        $elements = $this->resolvesToPhelDefinition($classSymbol, $env)
            ? [$classSymbol]
            : [Symbol::create(Symbol::NAME_PHP_NEW)->copyLocationFrom($first), $classSymbol];

        $count = count($list);
        for ($i = 1; $i < $count; ++$i) {
            $elements[] = $list->get($i);
        }

        return Phel::list($elements)->copyLocationFrom($list);
    }

    private function resolvesToPhelDefinition(Symbol $classSymbol, NodeEnvironmentInterface $env): bool
    {
        if ($env->hasLocal($classSymbol)) {
            return false;
        }

        return $this->analyzer->resolve($classSymbol, $env) instanceof GlobalVarNode;
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
     *
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentListInterface<mixed>
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

        $factory = $this->specialFormFactories()[$symbolName] ?? null;
        $analyzer = $factory !== null
            ? $factory()
            : new InvokeSymbol($this->analyzer);

        return $this->symbolAnalyzerCache[$symbolName] = $analyzer;
    }

    /**
     * Lazy `name => factory` dispatch registry, built once. The factories defer
     * construction so each analyzer is still only instantiated on first use
     * (and then memoized in `$symbolAnalyzerCache`), matching the old `match`.
     *
     * @return array<string, callable(): SpecialFormAnalyzerInterface>
     */
    private function specialFormFactories(): array
    {
        if ($this->specialFormFactories !== null) {
            return $this->specialFormFactories;
        }

        // `php/new` and `new` dispatch identically; share one factory.
        $phpNew = fn(): SpecialFormAnalyzerInterface => new PhpNewSymbol($this->analyzer);

        return $this->specialFormFactories = [
            Symbol::NAME_DEF => fn(): SpecialFormAnalyzerInterface => new DefSymbol($this->analyzer),
            Symbol::NAME_DEF_ONCE => fn(): SpecialFormAnalyzerInterface => new DefSymbol($this->analyzer, defonce: true),
            Symbol::NAME_NS => fn(): SpecialFormAnalyzerInterface => new NsSymbol($this->analyzer),
            Symbol::NAME_IN_NS => fn(): SpecialFormAnalyzerInterface => new InNsSymbol($this->analyzer),
            Symbol::NAME_USE => fn(): SpecialFormAnalyzerInterface => new UseSymbol($this->analyzer),
            Symbol::NAME_LOAD => fn(): SpecialFormAnalyzerInterface => new LoadSymbol($this->analyzer),
            Symbol::NAME_FN => fn(): SpecialFormAnalyzerInterface => new FnSymbol($this->analyzer, $this->assertsEnabled),
            Symbol::NAME_QUOTE => static fn(): SpecialFormAnalyzerInterface => new QuoteSymbol(),
            Symbol::NAME_VAR => fn(): SpecialFormAnalyzerInterface => new VarSymbol($this->analyzer),
            Symbol::NAME_DO => fn(): SpecialFormAnalyzerInterface => new DoSymbol($this->analyzer),
            Symbol::NAME_BREAK => fn(): SpecialFormAnalyzerInterface => new BreakSymbol($this->analyzer),
            Symbol::NAME_IF => fn(): SpecialFormAnalyzerInterface => new IfSymbol($this->analyzer),
            Symbol::NAME_APPLY => fn(): SpecialFormAnalyzerInterface => new ApplySymbol($this->analyzer),
            Symbol::NAME_LET => fn(): SpecialFormAnalyzerInterface => new LetSymbol($this->analyzer, new Deconstructor(new BindingValidator())),
            Symbol::NAME_PHP_NEW => $phpNew,
            Symbol::NAME_NEW => $phpNew,
            Symbol::NAME_PHP_OBJECT_CALL => fn(): SpecialFormAnalyzerInterface => new PhpObjectCallSymbol($this->analyzer, isStatic: false),
            Symbol::NAME_PHP_OBJECT_STATIC_CALL => fn(): SpecialFormAnalyzerInterface => new PhpObjectCallSymbol($this->analyzer, isStatic: true),
            Symbol::NAME_PHP_CALLABLE => fn(): SpecialFormAnalyzerInterface => new PhpCallableSymbol($this->analyzer),
            Symbol::NAME_PHP_REF => fn(): SpecialFormAnalyzerInterface => new PhpRefSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_GET => fn(): SpecialFormAnalyzerInterface => new PhpAGetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_GET_IN => fn(): SpecialFormAnalyzerInterface => new PhpAGetInSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_SET => fn(): SpecialFormAnalyzerInterface => new PhpASetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_SET_IN => fn(): SpecialFormAnalyzerInterface => new PhpASetInSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_PUSH => fn(): SpecialFormAnalyzerInterface => new PhpAPushSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_PUSH_IN => fn(): SpecialFormAnalyzerInterface => new PhpAPushInSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_UNSET => fn(): SpecialFormAnalyzerInterface => new PhpAUnsetSymbol($this->analyzer),
            Symbol::NAME_PHP_ARRAY_UNSET_IN => fn(): SpecialFormAnalyzerInterface => new PhpAUnsetInSymbol($this->analyzer),
            Symbol::NAME_RECUR => fn(): SpecialFormAnalyzerInterface => new RecurSymbol($this->analyzer),
            Symbol::NAME_TRY => fn(): SpecialFormAnalyzerInterface => new TrySymbol($this->analyzer),
            Symbol::NAME_THROW => fn(): SpecialFormAnalyzerInterface => new ThrowSymbol($this->analyzer),
            Symbol::NAME_LOOP => fn(): SpecialFormAnalyzerInterface => new LoopSymbol($this->analyzer, new BindingValidator()),
            Symbol::NAME_FOREACH => fn(): SpecialFormAnalyzerInterface => new ForeachSymbol($this->analyzer),
            Symbol::NAME_DEF_STRUCT => fn(): SpecialFormAnalyzerInterface => new DefStructSymbol($this->analyzer, $this->createImplementationsAnalyzer()),
            Symbol::NAME_DEF_EXCEPTION => fn(): SpecialFormAnalyzerInterface => new DefExceptionSymbol($this->analyzer),
            Symbol::NAME_DEF_ENUM => fn(): SpecialFormAnalyzerInterface => new DefEnumSymbol($this->analyzer, $this->createImplementationsAnalyzer()),
            Symbol::NAME_PHP_OBJECT_SET => fn(): SpecialFormAnalyzerInterface => new PhpOSetSymbol($this->analyzer),
            Symbol::NAME_SET_VAR => fn(): SpecialFormAnalyzerInterface => new SetVarSymbol($this->analyzer),
            Symbol::NAME_DEF_INTERFACE => fn(): SpecialFormAnalyzerInterface => new DefInterfaceSymbol($this->analyzer),
            Symbol::NAME_REIFY => fn(): SpecialFormAnalyzerInterface => new ReifySymbol(new MethodBodyAnalyzer($this->analyzer)),
        ];
    }

    private function createImplementationsAnalyzer(): InterfaceImplementationsAnalyzer
    {
        return new InterfaceImplementationsAnalyzer(
            $this->analyzer,
            $this->munge,
            new MethodBodyAnalyzer($this->analyzer),
            new PhpBlockAnalyzer($this->munge, new MethodBodyAnalyzer($this->analyzer)),
        );
    }
}
