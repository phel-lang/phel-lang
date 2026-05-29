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
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
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

    public function test_declines_impure_argument(): void
    {
        $this->seedDefn('my-inc', ['x'], $this->plusBody('x', 1));

        // arg `(some-fn)` is a user call -> impure
        $impureArg = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, 'user', Symbol::create('some-fn'), Phel::map()),
            [],
        );

        self::assertNull($this->inline('my-inc', [$impureArg]));
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
}
