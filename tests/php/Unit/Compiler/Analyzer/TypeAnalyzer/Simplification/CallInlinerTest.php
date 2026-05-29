<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\CallInliner;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class CallInlinerTest extends TestCase
{
    private CallInliner $inliner;

    private Analyzer $analyzer;

    private NodeEnvironment $env;

    protected function setUp(): void
    {
        $this->inliner = new CallInliner();
        $this->analyzer = new Analyzer(new GlobalEnvironment());
        $this->analyzer->setOptimizationLevel(2);

        $this->env = NodeEnvironment::empty()->withExpressionContext();
    }

    public function test_inlines_and_folds_literal_call(): void
    {
        // (defn my-inc [x] (+ x 1)) ; call (my-inc 5) -> 6
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        $result = $this->inline('my-inc', [new LiteralNode($this->env, 5)]);

        self::assertInstanceOf(LiteralNode::class, $result);
        self::assertSame(6, $result->getValue());
    }

    public function test_inlines_symbolic_call_to_a_pure_body(): void
    {
        // (my-inc y) where y is a caller local -> a (+ y 1) call, no dispatch.
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        $result = $this->inline('my-inc', [new LocalVarNode($this->env, Symbol::create('y'))]);

        self::assertInstanceOf(CallNode::class, $result);
        $args = $result->getArguments();
        self::assertInstanceOf(LocalVarNode::class, $args[0]);
        self::assertSame('y', $args[0]->getName()->getName());
    }

    public function test_declines_when_optimization_level_below_2(): void
    {
        $this->analyzer->setOptimizationLevel(1);
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        self::assertNull($this->inline('my-inc', [new LiteralNode($this->env, 5)]));
    }

    public function test_declines_when_no_side_table_entry(): void
    {
        // No seedDefn call: the callee is unknown / multi-arity.
        self::assertNull($this->inline('unknown', [new LiteralNode($this->env, 5)]));
    }

    public function test_declines_variadic_callee(): void
    {
        $this->seedDefn('my-list', ['xs'], $this->plusBody('xs', 1), isVariadic: true);

        self::assertNull($this->inline('my-list', [new LiteralNode($this->env, 5)]));
    }

    public function test_declines_recursive_callee(): void
    {
        $this->seedDefn('my-loop', ['x'], $this->plusBody('x', 1), recurs: true);

        self::assertNull($this->inline('my-loop', [new LiteralNode($this->env, 5)]));
    }

    public function test_declines_on_arity_mismatch(): void
    {
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        self::assertNull($this->inline('my-inc', [
            new LiteralNode($this->env, 5),
            new LiteralNode($this->env, 6),
        ]));
    }

    public function test_binds_impure_argument_to_a_let(): void
    {
        // (defn my-inc [x] (+ x 1)) called with the impure arg `(some-fn)`:
        // the arg must be bound once, so the result wraps the body in a let.
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        $impureArg = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, 'user', Symbol::create('some-fn'), Phel::map()),
            [],
        );

        $result = $this->inline('my-inc', [$impureArg]);

        self::assertInstanceOf(LetNode::class, $result);
        self::assertCount(1, $result->getBindings());
        self::assertSame($impureArg, $result->getBindings()[0]->getInitExpr());
    }

    public function test_binds_pure_argument_used_more_than_once(): void
    {
        // (defn sq [x] (* x x)) called with `(+ y 1)` (pure, two uses):
        // binding avoids duplicating the addition.
        $this->seedDefn('sq', ['x'], $this->squareBody('x'));

        $pureMultiUse = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('+'), Phel::map()),
            [new LocalVarNode($this->env, Symbol::create('y')), new LiteralNode($this->env, 1)],
        );

        $result = $this->inline('sq', [$pureMultiUse]);

        self::assertInstanceOf(LetNode::class, $result);
        self::assertCount(1, $result->getBindings());
    }

    public function test_substitutes_pure_single_use_argument_without_binding(): void
    {
        // A pure arg used once is substituted directly (no let wrapper).
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        $result = $this->inline('my-inc', [new LocalVarNode($this->env, Symbol::create('y'))]);

        self::assertInstanceOf(CallNode::class, $result);
    }

    public function test_inlines_defn_returning_a_vector(): void
    {
        // (defn pair [a b] [a b]) ; (pair y z) -> [y z], no dispatch.
        $body = new DoNode($this->env, [], new VectorNode($this->env, [
            new LocalVarNode($this->env, Symbol::create('a')),
            new LocalVarNode($this->env, Symbol::create('b')),
        ]));
        $this->seedDefn('pair', ['a', 'b'], $body);

        $result = $this->inline('pair', [
            new LocalVarNode($this->env, Symbol::create('y')),
            new LocalVarNode($this->env, Symbol::create('z')),
        ]);

        self::assertInstanceOf(VectorNode::class, $result);
        $args = $result->getArgs();
        self::assertInstanceOf(LocalVarNode::class, $args[0]);
        self::assertInstanceOf(LocalVarNode::class, $args[1]);
        self::assertSame('y', $args[0]->getName()->getName());
        self::assertSame('z', $args[1]->getName()->getName());
    }

    public function test_declines_impure_body(): void
    {
        // (defn log-it [x] (println x)) -> body is side-effecting
        $body = new DoNode($this->env, [], new CallNode(
            $this->env,
            new GlobalVarNode($this->env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('println'), Phel::map()),
            [new LocalVarNode($this->env, Symbol::create('x'))],
        ));
        $this->seedDefn('log-it', ['x'], $body);

        self::assertNull($this->inline('log-it', [new LiteralNode($this->env, 5)]));
    }

    public function test_declines_multi_statement_body(): void
    {
        $body = new DoNode(
            $this->env,
            [new LiteralNode($this->env, 1)],
            $this->plusRet('x', 1),
        );
        $this->seedDefn('my-inc', ['x'], $body);

        self::assertNull($this->inline('my-inc', [new LiteralNode($this->env, 5)]));
    }

    public function test_declines_memoised_callee(): void
    {
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));
        $meta = Phel::map(Keyword::create('memoize'), true);

        self::assertNull($this->inline('my-inc', [new LiteralNode($this->env, 5)], $meta));
    }

    public function test_inlines_pure_annotated_callee_with_unprovable_body(): void
    {
        // (defn ^:pure wrap [x] (helper x)) — the body calls a user fn, so
        // it is not structurally pure; the :pure annotation inlines it anyway.
        $this->seedDefn('wrap', ['x'], $this->userCallBody('helper', 'x'));

        $result = $this->inline(
            'wrap',
            [new LiteralNode($this->env, 5)],
            Phel::map(Keyword::create('pure'), true),
        );

        self::assertInstanceOf(CallNode::class, $result);
    }

    public function test_declines_unannotated_callee_with_impure_body(): void
    {
        // Same body without :pure: structural purity fails, so no inline.
        $this->seedDefn('wrap2', ['x'], $this->userCallBody('helper', 'x'));

        self::assertNull($this->inline('wrap2', [new LiteralNode($this->env, 5)]));
    }

    public function test_inlines_the_matching_arity_of_a_multi_arity_defn(): void
    {
        // (defn poly ([x] (+ x 1)) ([a b] (+ a b)))
        $arity1 = new FnNode($this->env, [Symbol::create('x')], $this->plusBody('x', 1), [], false, false);
        $arity2 = new FnNode($this->env, [Symbol::create('a'), Symbol::create('b')], $this->sumBody('a', 'b'), [], false, false);
        $this->analyzer->setDefFnNode('user', Symbol::create('poly'), new MultiFnNode($this->env, [$arity1, $arity2]));

        $one = $this->inline('poly', [new LiteralNode($this->env, 5)]);
        self::assertInstanceOf(LiteralNode::class, $one);
        self::assertSame(6, $one->getValue());

        $two = $this->inline('poly', [new LiteralNode($this->env, 2), new LiteralNode($this->env, 3)]);
        self::assertInstanceOf(LiteralNode::class, $two);
        self::assertSame(5, $two->getValue());
    }

    public function test_declines_when_no_arity_matches(): void
    {
        $arity1 = new FnNode($this->env, [Symbol::create('x')], $this->plusBody('x', 1), [], false, false);
        $this->analyzer->setDefFnNode('user', Symbol::create('mono'), new MultiFnNode($this->env, [$arity1]));

        // Two args, but the only arity takes one.
        self::assertNull($this->inline('mono', [
            new LiteralNode($this->env, 1),
            new LiteralNode($this->env, 2),
        ]));
    }

    /**
     * @param list<string> $paramNames
     */
    private function seedDefn(
        string $name,
        array $paramNames,
        DoNode $body,
        bool $isVariadic = false,
        bool $recurs = false,
    ): void {
        $params = array_map(Symbol::create(...), $paramNames);
        $fnNode = new FnNode($this->env, $params, $body, [], $isVariadic, $recurs);
        $this->analyzer->setDefFnNode('user', Symbol::create($name), $fnNode);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function inline(string $name, array $args, ?PersistentMapInterface $meta = null): ?AbstractNode
    {
        $f = new GlobalVarNode($this->env, 'user', Symbol::create($name), $meta ?? Phel::map());

        return $this->inliner->tryInline($f, $args, $this->env, $this->analyzer);
    }

    private function plusBody(string $param, int $addend): DoNode
    {
        return new DoNode($this->env, [], $this->plusRet($param, $addend));
    }

    private function plusRet(string $param, int $addend): CallNode
    {
        return new CallNode(
            $this->env,
            new GlobalVarNode($this->env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('+'), Phel::map()),
            [new LocalVarNode($this->env, Symbol::create($param)), new LiteralNode($this->env, $addend)],
        );
    }

    private function userCallBody(string $userFn, string $param): DoNode
    {
        // (<userFn> <param>) — a user call, impure to the structural detector.
        $ret = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, 'user', Symbol::create($userFn), Phel::map()),
            [new LocalVarNode($this->env, Symbol::create($param))],
        );

        return new DoNode($this->env, [], $ret);
    }

    private function sumBody(string $a, string $b): DoNode
    {
        // (+ a b)
        $ret = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('+'), Phel::map()),
            [
                new LocalVarNode($this->env, Symbol::create($a)),
                new LocalVarNode($this->env, Symbol::create($b)),
            ],
        );

        return new DoNode($this->env, [], $ret);
    }

    private function squareBody(string $param): DoNode
    {
        // (* param param) -> the param is used twice.
        $ret = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('*'), Phel::map()),
            [
                new LocalVarNode($this->env, Symbol::create($param)),
                new LocalVarNode($this->env, Symbol::create($param)),
            ],
        );

        return new DoNode($this->env, [], $ret);
    }
}
