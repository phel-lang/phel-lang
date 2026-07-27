<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;

use function is_string;

/**
 * Picks the member-access operator for a `PhpObjectCallNode`.
 *
 * A class name can be a value in Phel, so `(.cases c)` has to reach
 * `$c::cases()` when `c` holds `"\\App\\Status"` and `$c->cases()` when it holds
 * an object. The written form cannot decide this, and neither can the analyzer:
 * `LocalVarNode::getInferredType()` only reports a tag once the inferrers have
 * grafted it, which is why every other consumer of it also lives here.
 *
 * @internal
 */
final readonly class PhpObjectCallDispatchResolver
{
    private const string STRING_TAG = 'string';

    public function resolve(PhpObjectCallNode $node): PhpObjectCallDispatch
    {
        $target = $node->getTargetExpr();

        // `Foo->BAR` is never valid PHP, so a class-name target is static
        // whether it was written as `php/::` or `php/->`.
        if ($node->isStatic() || $target instanceof PhpClassNameNode) {
            return PhpObjectCallDispatch::StaticClass;
        }

        // `$t::x` reads a class constant while `$t->x` reads a property, so
        // deferring this one to runtime would pick between two different
        // members rather than two spellings of the same one.
        if (!$node->isMethodCall()) {
            return PhpObjectCallDispatch::Instance;
        }

        return match (true) {
            $this->isClassNameString($target) => PhpObjectCallDispatch::StaticClass,
            $this->isNeverClassNameString($target) => PhpObjectCallDispatch::Instance,
            default => PhpObjectCallDispatch::Runtime,
        };
    }

    private function isClassNameString(AbstractNode $target): bool
    {
        if ($target instanceof LiteralNode) {
            return is_string($target->getValue());
        }

        return $target instanceof LocalVarNode
            && TagNormalizer::normalise($target->getInferredType()) === self::STRING_TAG;
    }

    /**
     * A target the emitter can prove is not a class-name string, so it keeps the
     * plain `->` with no runtime check. A chained segment counts: its target is
     * the previous call's return value, and threading `(-> o (.a) (.b))` must not
     * pay for a test on every link.
     */
    private function isNeverClassNameString(AbstractNode $target): bool
    {
        if ($target instanceof PhpNewNode || $target instanceof PhpObjectCallNode) {
            return true;
        }

        return $target instanceof LocalVarNode
            && $target->getInferredType() !== null;
    }
}
