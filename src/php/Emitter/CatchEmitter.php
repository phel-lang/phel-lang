<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\CatchNode;
use Phel\Ast\Node;
use Phel\Emitter;

final class CatchEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof CatchNode);

        $this->emitter->emitStr(' catch (', $node->getStartSourceLocation());
        $this->emitter->emitStr($node->getType()->getName(), $node->getType()->getStartLocation());
        $this->emitter->emitStr(
            ' $' . $this->emitter->munge($node->getName()->getName()),
            $node->getName()->getStartLocation()
        );
        $this->emitter->emitLine(') {', $node->getStartSourceLocation());
        $this->emitter->indentLevel++;
        $this->emitter->emit($node->getBody());
        $this->emitter->indentLevel--;
        $this->emitter->emitLine();
        $this->emitter->emitStr('}', $node->getStartSourceLocation());
    }
}
