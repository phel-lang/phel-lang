<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast;
use Phel\Emitter\NodeEmitter\WithOutputEmitter;
use RuntimeException;

final class NodeEmitterFactory
{
    use WithOutputEmitter;

    public function createNodeEmitter(string $astNodeClassName): NodeEmitter
    {
        switch ($astNodeClassName) {
            case Ast\NsNode::class:
                return new NodeEmitter\NsEmitter($this->outputEmitter);
            case Ast\DefNode::class:
                return new NodeEmitter\DefEmitter($this->outputEmitter);
            case Ast\LiteralNode::class:
                return new NodeEmitter\LiteralEmitter($this->outputEmitter);
            case Ast\QuoteNode::class:
                return new NodeEmitter\QuoteEmitter($this->outputEmitter);
            case Ast\FnNode::class:
                return new NodeEmitter\FnAsClassEmitter($this->outputEmitter);
            case Ast\DoNode::class:
                return new NodeEmitter\DoEmitter($this->outputEmitter);
            case Ast\LetNode::class:
                return new NodeEmitter\LetEmitter($this->outputEmitter);
            case Ast\LocalVarNode::class:
                return new NodeEmitter\LocalVarEmitter($this->outputEmitter);
            case Ast\GlobalVarNode::class:
                return new NodeEmitter\GlobalVarEmitter($this->outputEmitter);
            case Ast\CallNode::class:
                return new NodeEmitter\CallEmitter($this->outputEmitter);
            case Ast\IfNode::class:
                return new NodeEmitter\IfEmitter($this->outputEmitter);
            case Ast\ApplyNode::class:
                return new NodeEmitter\ApplyEmitter($this->outputEmitter);
            case Ast\TupleNode::class:
                return new NodeEmitter\TupleEmitter($this->outputEmitter);
            case Ast\PhpNewNode::class:
                return new NodeEmitter\PhpNewEmitter($this->outputEmitter);
            case Ast\PhpVarNode::class:
                return new NodeEmitter\PhpVarEmitter($this->outputEmitter);
            case Ast\PhpObjectCallNode::class:
                return new NodeEmitter\PhpObjectCallEmitter($this->outputEmitter);
            case Ast\RecurNode::class:
                return new NodeEmitter\RecurEmitter($this->outputEmitter);
            case Ast\ThrowNode::class:
                return new NodeEmitter\ThrowEmitter($this->outputEmitter);
            case Ast\TryNode::class:
                return new NodeEmitter\TryEmitter($this->outputEmitter);
            case Ast\CatchNode::class:
                return new NodeEmitter\CatchEmitter($this->outputEmitter);
            case Ast\PhpArrayGetNode::class:
                return new NodeEmitter\PhpArrayGetEmitter($this->outputEmitter);
            case Ast\PhpArraySetNode::class:
                return new NodeEmitter\PhpArraySetEmitter($this->outputEmitter);
            case Ast\PhpArrayUnsetNode::class:
                return new NodeEmitter\PhpArrayUnsetEmitter($this->outputEmitter);
            case Ast\PhpClassNameNode::class:
                return new NodeEmitter\PhpClassNameEmitter($this->outputEmitter);
            case Ast\PhpArrayPushNode::class:
                return new NodeEmitter\PhpArrayPushEmitter($this->outputEmitter);
            case Ast\ForeachNode::class:
                return new NodeEmitter\ForeachEmitter($this->outputEmitter);
            case Ast\ArrayNode::class:
                return new NodeEmitter\ArrayEmitter($this->outputEmitter);
            case Ast\TableNode::class:
                return new NodeEmitter\TableEmitter($this->outputEmitter);
            case Ast\DefStructNode::class:
                return new NodeEmitter\DefStructEmitter($this->outputEmitter);
            default:
                throw new RuntimeException("Not supported AstClassName: '$astNodeClassName'");
        }
    }
}
