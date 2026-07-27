<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use ReflectionClass;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function strlen;
use function substr;

/**
 * Expands a qualified member used in value position, the Clojure 1.12 shapes
 * that are safe in PHP:
 *
 *   `\C/CONST`  -> `(php/:: \C CONST)`            class constant, unchanged
 *   `\C/m`      -> `(php/callable \C m)`          static method as a value
 *   `\C/.m`     -> `(fn [o & args] ...)`          instance method as a value
 *
 * A class carrying a constant and a static method of the same name resolves to
 * the constant, which is what happened before this expansion existed; the
 * method stays reachable as `(fn [x] (\C/m x))`.
 *
 * `\C/new` is deliberately not a constructor: `Foo::new()` is a legal, common
 * PHP named constructor, so the name is already taken. See
 * `docs/spec/clojure-divergences.md`.
 *
 * @internal
 */
final readonly class QualifiedMemberExpander
{
    public function __construct(
        private AnalyzerInterface $analyzer,
    ) {}

    /**
     * @return PersistentListInterface<mixed>|null the form to analyze instead
     *                                             of the symbol, or null when the symbol is not a qualified member
     */
    public function expand(Symbol $symbol, NodeEnvironmentInterface $env): ?PersistentListInterface
    {
        $ns = $symbol->getNamespace();
        if (!$this->isClassReference($ns)) {
            return null;
        }

        $name = $symbol->getName();

        if ($this->isInstanceMemberName($name)) {
            return $this->instanceMethodValue($symbol, substr($name, 1));
        }

        if ($name === '' || !$this->isIdentifierStartChar($name[0])) {
            return null;
        }

        if ($this->isStaticMethodOnly((string) $ns, $name, $symbol, $env)) {
            return $this->staticMethodValue($symbol);
        }

        return $this->constantAccess($symbol);
    }

    private function isClassReference(?string $ns): bool
    {
        if (in_array($ns, [null, '', 'php'], true)) {
            return false;
        }

        return $ns[0] === '\\'
            || ($ns[0] >= 'A' && $ns[0] <= 'Z');
    }

    /**
     * `.m` names an instance method; `.` is not a legal PHP identifier
     * character, so the form cannot collide with a real member name.
     */
    private function isInstanceMemberName(string $name): bool
    {
        return strlen($name) > 1
            && $name[0] === '.'
            && $this->isIdentifierStartChar($name[1]);
    }

    /**
     * Reflects the class to tell a static method from a constant. Both can
     * exist under one name, and only the constant is reachable in value
     * position, so the constant wins.
     */
    private function isStaticMethodOnly(
        string $ns,
        string $name,
        Symbol $symbol,
        NodeEnvironmentInterface $env,
    ): bool {
        $classNode = $this->analyzer->resolve(
            Symbol::create($ns)->copyLocationFrom($symbol),
            $env->withExpressionContext(),
        );

        if (!$classNode instanceof PhpClassNameNode) {
            return false;
        }

        $className = $classNode->getAbsolutePhpName();
        if (!class_exists($className) && !interface_exists($className) && !enum_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->hasConstant($name) || !$reflection->hasMethod($name)) {
            return false;
        }

        $method = $reflection->getMethod($name);

        return $method->isStatic() && $method->isPublic();
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private function staticMethodValue(Symbol $symbol): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_PHP_CALLABLE)->copyLocationFrom($symbol),
            Symbol::create((string) $symbol->getNamespace())->copyLocationFrom($symbol),
            Symbol::create($symbol->getName())->copyLocationFrom($symbol),
        ])->copyLocationFrom($symbol);
    }

    /**
     * `(fn [receiver & args] (apply (php/callable receiver m) args))`: the
     * receiver drives the dispatch, so the class in `\C/.m` documents the
     * intent without constraining the argument.
     *
     * @return PersistentListInterface<mixed>
     */
    private function instanceMethodValue(Symbol $symbol, string $method): PersistentListInterface
    {
        $receiver = Symbol::gen('receiver_')->copyLocationFrom($symbol);
        $args = Symbol::gen('args_')->copyLocationFrom($symbol);

        $callable = Phel::list([
            Symbol::create(Symbol::NAME_PHP_CALLABLE)->copyLocationFrom($symbol),
            $receiver,
            Symbol::create($method)->copyLocationFrom($symbol),
        ])->copyLocationFrom($symbol);

        $body = Phel::list([
            Symbol::create(Symbol::NAME_APPLY)->copyLocationFrom($symbol),
            $callable,
            $args,
        ])->copyLocationFrom($symbol);

        return Phel::list([
            Symbol::create(Symbol::NAME_FN)->copyLocationFrom($symbol),
            Phel::vector([$receiver, Symbol::create('&')->copyLocationFrom($symbol), $args])
                ->copyLocationFrom($symbol),
            $body,
        ])->copyLocationFrom($symbol);
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private function constantAccess(Symbol $symbol): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL)->copyLocationFrom($symbol),
            Symbol::create((string) $symbol->getNamespace())->copyLocationFrom($symbol),
            Symbol::create($symbol->getName())->copyLocationFrom($symbol),
        ])->copyLocationFrom($symbol);
    }

    private function isIdentifierStartChar(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z')
            || ($c >= 'A' && $c <= 'Z')
            || $c === '_';
    }
}
