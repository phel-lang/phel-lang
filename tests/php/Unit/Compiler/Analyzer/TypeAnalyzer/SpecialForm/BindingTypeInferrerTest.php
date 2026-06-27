<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\BindingTypeInferrer;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

use function is_string;

/**
 * Pins the graft contract directly — what integration fixtures can only
 * observe transitively through emitted PHP. A grafted tag must land on BOTH
 * the binding symbol (for the emitter's doctag) and its shadow (the instance
 * a reference resolves to), an explicit user tag must always win, and a
 * non-primitive init must leave the binding untouched.
 */
final class BindingTypeInferrerTest extends TestCase
{
    private NodeEnvironmentInterface $env;

    protected function setUp(): void
    {
        $this->env = NodeEnvironment::empty();
    }

    public function test_int_literal_grafts_int_on_symbol_and_shadow(): void
    {
        $binding = $this->letBinding('n', new LiteralNode($this->env, 7));

        new BindingTypeInferrer()->graftLetBindings([$binding]);

        self::assertSame('int', $this->tagOf($binding->getSymbol()));
        self::assertSame(
            'int',
            $this->tagOf($binding->getShadow()),
            'tag must be mirrored onto the shadow the reference resolves to',
        );
    }

    public function test_literal_primitive_tags(): void
    {
        $float = $this->letBinding('f', new LiteralNode($this->env, 1.5));
        $bool = $this->letBinding('b', new LiteralNode($this->env, true));
        $string = $this->letBinding('s', new LiteralNode($this->env, 'x'));

        new BindingTypeInferrer()->graftLetBindings([$float, $bool, $string]);

        self::assertSame('float', $this->tagOf($float->getSymbol()));
        self::assertSame('bool', $this->tagOf($bool->getSymbol()));
        self::assertSame('string', $this->tagOf($string->getSymbol()));
    }

    public function test_user_written_tag_wins_over_inferred(): void
    {
        // `^float` whose init infers int must stay float — on both instances.
        $symbol = Symbol::create('n')
            ->withMeta(Phel::map(Keyword::create('tag'), Symbol::create('float')));
        $binding = new BindingNode($this->env, $symbol, Symbol::gen('n_'), new LiteralNode($this->env, 1));

        new BindingTypeInferrer()->graftLetBindings([$binding]);

        self::assertSame('float', $this->tagOf($binding->getSymbol()));
        self::assertSame('float', $this->tagOf($binding->getShadow()));
    }

    public function test_php_count_infers_int_and_floor_infers_float(): void
    {
        $count = $this->letBinding('c', $this->phpCall('count', [new LiteralNode($this->env, null)]));
        // `php/floor` returns float, not int — guards the KnownPhpFunctionReturnTypes fix.
        $floor = $this->letBinding('f', $this->phpCall('floor', [new LiteralNode($this->env, 1.5)]));

        new BindingTypeInferrer()->graftLetBindings([$count, $floor]);

        self::assertSame('int', $this->tagOf($count->getSymbol()));
        self::assertSame('float', $this->tagOf($floor->getSymbol()));
    }

    public function test_untypeable_init_leaves_binding_untagged(): void
    {
        // `php/aget` is not in the fixed-return table (element type is unknown).
        $binding = $this->letBinding('x', $this->phpCall('aget', [
            new LiteralNode($this->env, null),
            new LiteralNode($this->env, 0),
        ]));

        new BindingTypeInferrer()->graftLetBindings([$binding]);

        self::assertNull($this->tagOf($binding->getSymbol()));
        self::assertNull($this->tagOf($binding->getShadow()));
    }

    public function test_core_arithmetic_and_inc_infer_int(): void
    {
        $sum = $this->letBinding('s', $this->coreCall('+', [
            new LiteralNode($this->env, 1),
            new LiteralNode($this->env, 2),
        ]));
        $inc = $this->letBinding('i', $this->coreCall('inc', [new LiteralNode($this->env, 1)]));

        new BindingTypeInferrer()->graftLetBindings([$sum, $inc]);

        self::assertSame('int', $this->tagOf($sum->getSymbol()));
        self::assertSame('int', $this->tagOf($inc->getSymbol()));
    }

    public function test_float_operand_promotes_arithmetic_to_float(): void
    {
        $product = $this->letBinding('m', $this->coreCall('*', [
            new LiteralNode($this->env, 2),
            new LiteralNode($this->env, 1.5),
        ]));

        new BindingTypeInferrer()->graftLetBindings([$product]);

        self::assertSame('float', $this->tagOf($product->getSymbol()));
    }

    private function letBinding(string $name, AbstractNode $init): BindingNode
    {
        return new BindingNode($this->env, Symbol::create($name), Symbol::gen($name . '_'), $init);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function phpCall(string $fn, array $args): CallNode
    {
        return new CallNode($this->env, new PhpVarNode($this->env, $fn), $args);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $fn, array $args): CallNode
    {
        $globalVar = new GlobalVarNode(
            $this->env,
            CompilerConstants::PHEL_CORE_NAMESPACE,
            Symbol::create($fn),
            Phel::map(),
        );

        return new CallNode($this->env, $globalVar, $args);
    }

    private function tagOf(Symbol $symbol): ?string
    {
        $meta = $symbol->getMeta();
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            return $tag->getName();
        }

        return is_string($tag) ? $tag : null;
    }
}
