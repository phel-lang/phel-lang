<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\TailCallRewriter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class TailCallRewriterTest extends TestCase
{
    private const string SELF_NS = 'user';

    private const string SELF_NAME = 'count-down';

    public function test_rewrites_direct_tail_self_call_to_recur(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $body = $this->selfCall($env, [new LiteralNode($env, 5)]);

        [$rewritten, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            false,
        );

        self::assertTrue($changed);
        self::assertTrue($frame->isActive());
        self::assertInstanceOf(RecurNode::class, $rewritten);
        self::assertSame($frame, $rewritten->getFrame());
    }

    public function test_rewrites_self_call_in_if_branches(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $body = new IfNode(
            $env,
            new LiteralNode($env, true),
            new LiteralNode($env, 'done'),
            $this->selfCall($env, [new LiteralNode($env, 0)]),
        );

        [$rewritten, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            false,
        );

        self::assertTrue($changed);
        self::assertInstanceOf(IfNode::class, $rewritten);
        self::assertInstanceOf(LiteralNode::class, $rewritten->getThenExpr());
        self::assertInstanceOf(RecurNode::class, $rewritten->getElseExpr());
    }

    public function test_does_not_rewrite_calls_outside_tail_position(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $inner = $this->selfCall($env, [new LiteralNode($env, 1)]);
        $body = new DoNode(
            $env,
            [$inner],
            new LiteralNode($env, 'done'),
        );

        [$rewritten, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            false,
        );

        self::assertFalse($changed);
        self::assertFalse($frame->isActive());
        self::assertInstanceOf(DoNode::class, $rewritten);
        self::assertSame($inner, $rewritten->getStmts()[0]);
    }

    public function test_skips_variadic_fn(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $body = $this->selfCall($env, [new LiteralNode($env, 1)]);

        [$rewritten, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            true,
        );

        self::assertFalse($changed);
        self::assertSame($body, $rewritten);
    }

    public function test_skips_arity_mismatch(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $body = $this->selfCall($env, [new LiteralNode($env, 1), new LiteralNode($env, 2)]);

        [$rewritten, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            false,
        );

        self::assertFalse($changed);
        self::assertSame($body, $rewritten);
    }

    public function test_skips_call_to_other_global(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $body = new CallNode(
            $env,
            new GlobalVarNode($env, self::SELF_NS, Symbol::create('other'), Phel::map()),
            [new LiteralNode($env, 1)],
        );

        [, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            false,
        );

        self::assertFalse($changed);
        self::assertFalse($frame->isActive());
    }

    public function test_skips_loop_let_body(): void
    {
        $env = NodeEnvironment::empty()->withReturnContext();
        $frame = new RecurFrame([Symbol::create('n')]);
        $body = new LetNode(
            $env,
            [],
            $this->selfCall($env, [new LiteralNode($env, 1)]),
            isLoop: true,
        );

        [$rewritten, $changed] = new TailCallRewriter()->rewrite(
            $body,
            $frame,
            self::SELF_NS,
            self::SELF_NAME,
            1,
            false,
        );

        self::assertFalse($changed);
        self::assertSame($body, $rewritten);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function selfCall(NodeEnvironment $env, array $args): CallNode
    {
        return new CallNode(
            $env,
            new GlobalVarNode($env, self::SELF_NS, Symbol::create(self::SELF_NAME), Phel::map()),
            $args,
        );
    }
}
