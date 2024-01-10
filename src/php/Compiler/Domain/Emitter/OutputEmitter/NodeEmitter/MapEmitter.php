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

        $keyValues = $node->getKeyValues();
        $countKeyValues = count($keyValues);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('\Phel\Lang\TypeFactory::getInstance()->persistentMapFromKVs(', $node->getStartSourceLocation());
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

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
