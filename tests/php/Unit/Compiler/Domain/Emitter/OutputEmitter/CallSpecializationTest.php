<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class CallSpecializationTest extends TestCase
{
    public function test_count_on_typed_vector_specialises_to_method_call(): void
    {
        $node = $this->coreCall('count', [$this->vectorLocal('v')]);

        $spec = CallSpecialization::typedVectorMethodCall($node);

        self::assertSame(['method' => 'count', 'args' => []], $spec);
    }

    public function test_nth_on_typed_vector_specialises_to_get(): void
    {
        $node = $this->coreCall('nth', [$this->vectorLocal('v'), new LiteralNode($this->env(), 0)]);

        $spec = CallSpecialization::typedVectorMethodCall($node);

        self::assertSame(['method' => 'get', 'args' => [1]], $spec);
    }

    public function test_nth_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('nth', [
            new LocalVarNode($env, Symbol::create('v')),
            new LiteralNode($env, 0),
        ]);

        self::assertNull(CallSpecialization::typedVectorMethodCall($node));
    }

    public function test_count_with_wrong_arity_skips(): void
    {
        $node = $this->coreCall('count', [$this->vectorLocal('v'), new LiteralNode($this->env(), 0)]);

        self::assertNull(CallSpecialization::typedVectorMethodCall($node));
    }

    public function test_other_core_fn_on_typed_vector_skips(): void
    {
        $node = $this->coreCall('inc', [$this->vectorLocal('v')]);

        self::assertNull(CallSpecialization::typedVectorMethodCall($node));
    }

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        return new CallNode(
            $this->env(),
            new GlobalVarNode(
                $this->env(),
                CompilerConstants::PHEL_CORE_NAMESPACE,
                Symbol::create($name),
                Phel::map(),
            ),
            $args,
        );
    }

    private function vectorLocal(string $name): LocalVarNode
    {
        $sym = Symbol::create($name);
        $tag = PersistentVectorInterface::class;
        $meta = Phel::map(Keyword::create('tag'), $tag);
        $locals = [$sym->withMeta($meta)];

        $env = NodeEnvironment::empty()
            ->withExpressionContext()
            ->withMergedLocals($locals);

        return new LocalVarNode($env, $sym);
    }
}
