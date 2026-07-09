<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReturnTypeInferrer;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

/**
 * Pins the tail-position return inference contract directly. Of note is the
 * pass-through rule: a body that returns an already-typed local (a bare param
 * or a let binding) publishes that local's type, so the emitter can stamp the
 * signature without an arithmetic operator in the body. The gate that keeps
 * bare literals unannotated must survive that widening.
 */
final class ReturnTypeInferrerTest extends TestCase
{
    private NodeEnvironmentInterface $env;

    protected function setUp(): void
    {
        $this->env = NodeEnvironment::empty();
    }

    public function test_bare_typed_param_passthrough_publishes_its_type(): void
    {
        // (fn [^int x] x) -> int
        $type = new ReturnTypeInferrer()->infer(
            new LocalVarNode($this->env, Symbol::create('x')),
            [$this->typedParam('x', 'int')],
        );

        self::assertSame('int', $type);
    }

    public function test_passthrough_preserves_nullable_and_class_types(): void
    {
        self::assertSame(
            '?int',
            new ReturnTypeInferrer()->infer(
                new LocalVarNode($this->env, Symbol::create('a')),
                [$this->typedParam('a', '?int')],
            ),
        );

        // A class-name tag passes through verbatim; the `\`-prefixed FQN form
        // the emitter stamps is covered end-to-end by the fn-typed-fqn fixture.
        self::assertSame(
            Symbol::class,
            new ReturnTypeInferrer()->infer(
                new LocalVarNode($this->env, Symbol::create('s')),
                [$this->typedParam('s', Symbol::class)],
            ),
        );
    }

    public function test_untyped_param_passthrough_stays_null(): void
    {
        // (fn [x] x) -> untyped: nothing is known about x.
        $type = new ReturnTypeInferrer()->infer(
            new LocalVarNode($this->env, Symbol::create('x')),
            [Symbol::create('x')],
        );

        self::assertNull($type);
    }

    public function test_literal_body_stays_null_even_with_typed_param(): void
    {
        // (fn [^int x] 5): the return is a literal the user never annotated,
        // so the gate keeps the signature untyped despite the typed param.
        $type = new ReturnTypeInferrer()->infer(
            new LiteralNode($this->env, 5),
            [$this->typedParam('x', 'int')],
        );

        self::assertNull($type);
    }

    public function test_operator_body_still_infers_alongside_passthrough(): void
    {
        // (fn [^int x] (php/+ x 1)) -> int: the arithmetic path is untouched.
        $type = new ReturnTypeInferrer()->infer(
            $this->phpCall('+', [
                new LocalVarNode($this->env, Symbol::create('x')),
                new LiteralNode($this->env, 1),
            ]),
            [$this->typedParam('x', 'int')],
        );

        self::assertSame('int', $type);
    }

    private function typedParam(string $name, string $tag): Symbol
    {
        return Symbol::create($name)
            ->withMeta(Phel::map(Keyword::create('tag'), $tag));
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function phpCall(string $fn, array $args): CallNode
    {
        return new CallNode($this->env, new PhpVarNode($this->env, $fn), $args);
    }
}
