<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\BooleanExprDetector;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class BooleanExprDetectorTest extends TestCase
{
    public function test_bool_literal_is_bool(): void
    {
        self::assertTrue(BooleanExprDetector::isBool(new LiteralNode(NodeEnvironment::empty(), true)));
        self::assertTrue(BooleanExprDetector::isBool(new LiteralNode(NodeEnvironment::empty(), false)));
    }

    public function test_non_bool_literal_is_not_bool(): void
    {
        self::assertFalse(BooleanExprDetector::isBool(new LiteralNode(NodeEnvironment::empty(), 1)));
        self::assertFalse(BooleanExprDetector::isBool(new LiteralNode(NodeEnvironment::empty(), 'x')));
        self::assertFalse(BooleanExprDetector::isBool(new LiteralNode(NodeEnvironment::empty(), null)));
    }

    public function test_infix_comparison_is_bool(): void
    {
        foreach (['===', '!==', '==', '!=', '<', '>', '<=', '>='] as $op) {
            $call = new CallNode(
                NodeEnvironment::empty(),
                new PhpVarNode(NodeEnvironment::empty(), $op),
                [
                    new LiteralNode(NodeEnvironment::empty(), 1),
                    new LiteralNode(NodeEnvironment::empty(), 2),
                ],
            );
            self::assertTrue(BooleanExprDetector::isBool($call), $op . ' should be bool');
        }
    }

    public function test_infix_arithmetic_is_not_bool(): void
    {
        foreach (['+', '-', '*', '/', '%', '.', '<<', '>>'] as $op) {
            $call = new CallNode(
                NodeEnvironment::empty(),
                new PhpVarNode(NodeEnvironment::empty(), $op),
                [
                    new LiteralNode(NodeEnvironment::empty(), 1),
                    new LiteralNode(NodeEnvironment::empty(), 2),
                ],
            );
            self::assertFalse(BooleanExprDetector::isBool($call), $op . ' should not be bool');
        }
    }

    public function test_known_bool_php_function_is_bool(): void
    {
        foreach (['is_int', 'is_string', 'is_a', 'in_array', 'array_key_exists', 'method_exists'] as $fn) {
            $call = new CallNode(
                NodeEnvironment::empty(),
                new PhpVarNode(NodeEnvironment::empty(), $fn),
                [],
            );
            self::assertTrue(BooleanExprDetector::isBool($call), $fn . ' should be bool');
        }
    }

    public function test_namespaced_known_bool_php_function_is_bool(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '\\is_int'),
            [],
        );
        self::assertTrue(BooleanExprDetector::isBool($call));
    }

    public function test_unknown_php_function_is_not_bool(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), 'strlen'),
            [],
        );
        self::assertFalse(BooleanExprDetector::isBool($call));
    }

    public function test_global_var_call_is_not_bool(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new GlobalVarNode(NodeEnvironment::empty(), 'phel.core', Symbol::create('foo'), Phel::map()),
            [],
        );
        self::assertFalse(BooleanExprDetector::isBool($call));
    }
}
