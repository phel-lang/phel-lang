<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ConstructorSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

/**
 * Specialisation gated by {@see ConstructorSpecialization}: a literal call to
 * one of the `phel.core` collection constructors is emitted as the `Phel`
 * static factory the runtime function would have reached, with the arguments
 * spliced into a PHP array literal.
 *
 *     (list 1 2 3)  ->  \Phel::list([1, 2, 3])
 *
 * Arguments are emitted in source order into the literal, so evaluation order
 * is unchanged from the generic path, which also evaluates them left to right
 * before invoking.
 *
 * @internal
 */
final readonly class ConstructorCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        $factory = ConstructorSpecialization::factoryMethod($node);
        if ($factory === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('\\' . Phel::class . '::' . $factory . '([', $loc);

        foreach ($node->getArguments() as $i => $argument) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(', ', $loc);
            }

            $this->outputEmitter->emitNode($argument);
        }

        $this->outputEmitter->emitStr('])', $loc);
        return true;
    }
}
