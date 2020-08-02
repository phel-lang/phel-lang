<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast;
use Phel\Emitter\NodeEmitter\WithEmitter;
use RuntimeException;

final class NodeEmitterFactory
{
    use WithEmitter;

    public function createNodeEmitter(string $astNodeClassName): NodeEmitter
    {
        switch ($astNodeClassName) {
            case Ast\NsNode::class:
                return new NodeEmitter\NsEmitter($this->emitter);
            case Ast\DefNode::class:
                return new NodeEmitter\DefEmitter($this->emitter);
            case Ast\LiteralNode::class:
                return new NodeEmitter\LiteralEmitter($this->emitter);
            case Ast\QuoteNode::class:
                return new NodeEmitter\QuoteEmitter($this->emitter);
            case Ast\FnNode::class:
                return new NodeEmitter\FnAsClassEmitter($this->emitter);
            case Ast\DoNode::class:
                return new NodeEmitter\DoEmitter($this->emitter);
            case Ast\LetNode::class:
                return new NodeEmitter\LetEmitter($this->emitter);
            case Ast\LocalVarNode::class:
                return new NodeEmitter\LocalVarEmitter($this->emitter);
            case Ast\GlobalVarNode::class:
                return new NodeEmitter\GlobalVarEmitter($this->emitter);
            case Ast\CallNode::class:
                return new NodeEmitter\CallEmitter($this->emitter);
            case Ast\IfNode::class:
                return new NodeEmitter\IfEmitter($this->emitter);
            case Ast\ApplyNode::class:
                return new NodeEmitter\ApplyEmitter($this->emitter);
            case Ast\TupleNode::class:
                return new NodeEmitter\TupleEmitter($this->emitter);
            case Ast\PhpNewNode::class:
                return new NodeEmitter\PhpNewEmitter($this->emitter);
            case Ast\PhpVarNode::class:
                return new NodeEmitter\PhpVarEmitter($this->emitter);
            case Ast\PhpObjectCallNode::class:
                return new NodeEmitter\PhpObjectCallEmitter($this->emitter);
            case Ast\RecurNode::class:
                return new NodeEmitter\RecurEmitter($this->emitter);
            case Ast\ThrowNode::class:
                return new NodeEmitter\ThrowEmitter($this->emitter);
            case Ast\TryNode::class:
                return new NodeEmitter\TryEmitter($this->emitter);
            case Ast\CatchNode::class:
                return new NodeEmitter\CatchEmitter($this->emitter);
            case Ast\PhpArrayGetNode::class:
                return new NodeEmitter\PhpArrayGetEmitter($this->emitter);
            case Ast\PhpArraySetNode::class:
                return new NodeEmitter\PhpArraySetEmitter($this->emitter);
            case Ast\PhpArrayUnsetNode::class:
                return new NodeEmitter\PhpArrayUnsetEmitter($this->emitter);
            case Ast\PhpClassNameNode::class:
                return new NodeEmitter\PhpClassNameEmitter($this->emitter);
            case Ast\PhpArrayPushNode::class:
                return new NodeEmitter\PhpArrayPushEmitter($this->emitter);
            case Ast\ForeachNode::class:
                return new NodeEmitter\ForeachEmitter($this->emitter);
            case Ast\ArrayNode::class:
                return new NodeEmitter\ArrayEmitter($this->emitter);
            case Ast\TableNode::class:
                return new NodeEmitter\TableEmitter($this->emitter);
            case Ast\DefStructNode::class:
                return new NodeEmitter\DefStructEmitter($this->emitter);
            default:
                throw new RuntimeException("Not supported AstClassName: '$astNodeClassName'");
        }
    }
}
