<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Analyzer\Ast\Reference;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\Reference\LocalVarReferences;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalVarReferencesTest extends TestCase
{
    public function test_an_object_receiver_is_read_only(): void
    {
        $env = NodeEnvironment::empty();
        $binding = Symbol::create('e_1');
        $node = new PhpObjectCallNode($env, new LocalVarNode($env, $binding), new MethodCallNode($env, Symbol::create('getMessage'), []), false, true);

        self::assertFalse(LocalVarReferences::mayBeMutated($node, $binding));
    }

    #[DataProvider('argumentEscapes')]
    public function test_direct_local_arguments_may_be_mutated(AbstractNode $node): void
    {
        self::assertTrue(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
    }

    public static function argumentEscapes(): iterable
    {
        $env = NodeEnvironment::empty();
        $local = new LocalVarNode($env, Symbol::create('e_1'));
        $callee = new PhpVarNode($env, 'sscanf');

        yield 'function' => [new CallNode($env, $callee, [$local])];
        yield 'apply' => [new ApplyNode($env, $callee, [$local])];
        yield 'method' => [new MethodCallNode($env, Symbol::create('mutate'), [$local])];
        yield 'constructor' => [new PhpNewNode($env, new PhpClassNameNode($env, Symbol::create('\\stdClass')), [$local])];
    }

    #[DataProvider('frameMutators')]
    public function test_calls_that_write_caller_locals_may_mutate_the_binding(string $name): void
    {
        $env = NodeEnvironment::empty();
        $node = new CallNode($env, new PhpVarNode($env, $name), [new LiteralNode($env, 'data')]);

        self::assertTrue(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
    }

    public static function frameMutators(): iterable
    {
        foreach (['eval', 'extract', '\\EXTRACT', 'include', 'include_once', 'require', 'require_once'] as $name) {
            yield $name => [$name];
        }
    }

    public function test_a_reference_inside_a_nested_function_may_mutate_the_binding(): void
    {
        $env = NodeEnvironment::empty();
        $binding = Symbol::create('e_1');
        $body = new PhpRefNode($env, new LocalVarNode($env, $binding));
        $node = new FnNode($env, [], $body, [$binding], false, false);

        self::assertTrue(LocalVarReferences::mayBeMutated($node, $binding));
        self::assertFalse(LocalVarReferences::mayBeMutated($node, Symbol::create('e_2')));
    }

    public function test_an_unknown_node_may_mutate_the_binding(): void
    {
        $node = new class(NodeEnvironment::empty()) extends AbstractNode {};

        self::assertTrue(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
    }

    public function test_a_stable_phel_function_with_no_binding_arguments_is_read_only(): void
    {
        $env = NodeEnvironment::empty();
        $callee = new GlobalVarNode($env, 'phel.core', Symbol::create('println'), Phel::map('arglists', '[& xs]'));
        $node = new CallNode($env, $callee, [new LiteralNode($env, 'message')]);

        self::assertFalse(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
    }

    public function test_bindable_phel_callees_cannot_prove_the_binding_read_only(): void
    {
        $env = NodeEnvironment::empty();
        foreach (['dynamic', 'redef'] as $tag) {
            foreach ([$tag, Keyword::create($tag)] as $key) {
                $callee = new GlobalVarNode($env, 'phel.core', Symbol::create('f'), Phel::map('arglists', '[]', $key, true));
                $node = new CallNode($env, $callee, []);

                self::assertTrue(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
            }
        }
    }

    public function test_dynamic_callees_cannot_prove_the_binding_read_only(): void
    {
        $env = NodeEnvironment::empty();
        $node = new CallNode($env, new LocalVarNode($env, Symbol::create('f')), []);

        self::assertTrue(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
    }

    #[DataProvider('collectionsWithMetadata')]
    public function test_collection_metadata_cannot_prove_the_binding_read_only(AbstractNode $node): void
    {
        self::assertTrue(LocalVarReferences::mayBeMutated($node, Symbol::create('e_1')));
    }

    public static function collectionsWithMetadata(): iterable
    {
        $env = NodeEnvironment::empty();
        $meta = new MapNode($env, []);

        yield 'vector' => [new VectorNode($env, [], meta: $meta)];
        yield 'set' => [new SetNode($env, [], meta: $meta)];
        yield 'map' => [new MapNode($env, [], meta: $meta)];
    }

    public function test_local_inside_method_call_is_collected(): void
    {
        // Mirrors `(-> more (get i))` from the `aset` macro body: a
        // local nested in a `MethodCallNode` must still be reachable.
        $methodCall = new MethodCallNode(
            NodeEnvironment::empty(),
            Symbol::create('get'),
            [new LocalVarNode(NodeEnvironment::empty(), Symbol::create('i'))],
        );
        $objCall = new PhpObjectCallNode(
            NodeEnvironment::empty(),
            new LocalVarNode(NodeEnvironment::empty(), Symbol::create('more')),
            $methodCall,
            isStatic: false,
            isMethodCall: true,
        );

        self::assertSame(['more', 'i'], LocalVarReferences::collect($objCall));
    }

    public function test_collects_single_local_var(): void
    {
        $node = new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x'));

        self::assertSame(['x'], LocalVarReferences::collect($node));
    }

    public function test_collects_no_locals_from_a_literal(): void
    {
        $node = new LiteralNode(NodeEnvironment::empty(), 1);

        self::assertSame([], LocalVarReferences::collect($node));
    }

    public function test_collects_locals_in_call_arguments(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '+'),
            [
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x')),
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('y')),
            ],
        );

        self::assertSame(['x', 'y'], LocalVarReferences::collect($call));
    }

    public function test_collects_nested_references(): void
    {
        $inner = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '-'),
            [
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x')),
                new LiteralNode(NodeEnvironment::empty(), 1),
            ],
        );
        $outer = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '+'),
            [
                $inner,
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('y')),
            ],
        );

        self::assertSame(['x', 'y'], LocalVarReferences::collect($outer));
    }
}
