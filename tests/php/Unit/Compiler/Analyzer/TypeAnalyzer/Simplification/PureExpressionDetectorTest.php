<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\PureExpressionDetector;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class PureExpressionDetectorTest extends TestCase
{
    public function test_literal_is_pure(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        self::assertTrue(new PureExpressionDetector()->isPure(new LiteralNode($env, 42)));
    }

    public function test_local_var_is_pure(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $node = new LocalVarNode($env, Symbol::create('x'));

        self::assertTrue(new PureExpressionDetector()->isPure($node));
    }

    public function test_global_var_reference_is_pure(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $node = new GlobalVarNode($env, 'user', Symbol::create('my-fn'), Phel::map());

        self::assertTrue(new PureExpressionDetector()->isPure($node));
    }

    public function test_foldable_call_is_pure(): void
    {
        // `(+ 1 2)` folds → safe to drop.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            $env,
            new GlobalVarNode($env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('+'), Phel::map()),
            [new LiteralNode($env, 1), new LiteralNode($env, 2)],
        );

        self::assertTrue(new PureExpressionDetector()->isPure($call));
    }

    public function test_call_that_would_throw_is_impure(): void
    {
        // `(abs nil)` is in the fold whitelist but the folder rejects
        // nil (it would throw at runtime) so we must keep the call.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            $env,
            new GlobalVarNode($env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('abs'), Phel::map()),
            [new LiteralNode($env, null)],
        );

        self::assertFalse(new PureExpressionDetector()->isPure($call));
    }

    public function test_side_effecting_call_is_impure(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            $env,
            new GlobalVarNode($env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create('println'), Phel::map()),
            [new LiteralNode($env, 'x')],
        );

        self::assertFalse(new PureExpressionDetector()->isPure($call));
    }

    public function test_user_call_is_impure(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            $env,
            new GlobalVarNode($env, 'user', Symbol::create('add'), Phel::map()),
            [new LiteralNode($env, 1)],
        );

        self::assertFalse(new PureExpressionDetector()->isPure($call));
    }
}
