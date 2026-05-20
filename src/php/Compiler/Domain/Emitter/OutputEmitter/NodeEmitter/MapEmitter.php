<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;
use function count;

final class MapEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof MapNode);
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $loc);
        $cached = $this->outputEmitter->emitConstantSlotPrefix($node, $loc);
        $this->outputEmitter->emitStr('\Phel::map(', $loc);
        $this->emitEntries($node);
        $this->outputEmitter->emitStr(')', $loc);
        if ($cached) {
            $this->outputEmitter->emitConstantSlotSuffix($loc);
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $loc);
    }

    private function emitEntries(MapNode $node): void
    {
        $keyValues = $node->getKeyValues();
        $countKeyValues = count($keyValues);

        if ($countKeyValues > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        for ($i = 0; $i < $countKeyValues; $i += 2) {
            $key = $keyValues[$i];
            $value = $keyValues[$i + 1];

            $this->outputEmitter->emitNode($key);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($value);
            if ($i < $countKeyValues - 2) {
                $this->outputEmitter->emitStr(',', $node->getStartSourceLocation());
            }

            $this->outputEmitter->emitLine();
        }

        if ($countKeyValues > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }
    }
}
