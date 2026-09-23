<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\AssocInSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhelCoreCall;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\AssocIn;

use function array_slice;

/**
 * Specialisation gated by {@see AssocInSpecialization}: `(assoc-in ds [k1 k2 …] v)`
 * becomes one {@see AssocIn::path()} call and `(update-in ds [k1 k2 …] f & args)`
 * one {@see AssocIn::update()} call, with the keys as a PHP array (#3328).
 *
 * @internal
 */
final readonly class AssocInCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    /**
     * Every argument is written once and in source order, the target, then
     * the keys, then the value or `f` and its arguments, so they evaluate as
     * the call's arguments did.
     */
    public function tryEmit(CallNode $node): bool
    {
        $keys = AssocInSpecialization::literalPathKeys($node);
        if ($keys === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $method = PhelCoreCall::is($node, 'assoc-in') ? 'path' : 'update';

        $this->outputEmitter->emitStr('\\' . AssocIn::class . '::' . $method . '(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr(', ', $loc);

        // A path of literals is hoisted whole, as the vector it replaces was,
        // so the key array is built once per fn rather than once per call.
        $cached = $this->outputEmitter->emitConstantSlotPrefix($args[1], $loc);
        $this->outputEmitter->emitStr('[', $loc);
        $this->outputEmitter->emitArgList($keys, $loc);
        $this->outputEmitter->emitStr(']', $loc);
        if ($cached) {
            $this->outputEmitter->emitConstantSlotSuffix($loc);
        }

        $this->outputEmitter->emitStr(', ', $loc);
        $this->outputEmitter->emitArgList(array_slice($args, 2), $loc);
        $this->outputEmitter->emitStr(')', $loc);

        return true;
    }
}
