<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

use Phel\Compiler\Analyzer\Ast;
use Phel\Compiler\Emitter\Exceptions\NotSupportedAstException;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter\MethodEmitter;
use Phel\Compiler\Emitter\OutputEmitterInterface;

final class NodeEmitterFactory
{
    public function createNodeEmitter(
        OutputEmitterInterface $outputEmitter,
        string $astNodeClassName
    ): NodeEmitterInterface {
        switch ($astNodeClassName) {
            case Ast\NsNode::class:
                return new NodeEmitter\NsEmitter($outputEmitter);
            case Ast\DefNode::class:
                return new NodeEmitter\DefEmitter($outputEmitter);
            case Ast\LiteralNode::class:
                return new NodeEmitter\LiteralEmitter($outputEmitter);
            case Ast\QuoteNode::class:
                return new NodeEmitter\QuoteEmitter($outputEmitter);
            case Ast\FnNode::class:
                return new NodeEmitter\FnAsClassEmitter($outputEmitter, new MethodEmitter($outputEmitter));
            case Ast\DoNode::class:
                return new NodeEmitter\DoEmitter($outputEmitter);
            case Ast\LetNode::class:
                return new NodeEmitter\LetEmitter($outputEmitter);
            case Ast\LocalVarNode::class:
                return new NodeEmitter\LocalVarEmitter($outputEmitter);
            case Ast\GlobalVarNode::class:
                return new NodeEmitter\GlobalVarEmitter($outputEmitter);
            case Ast\CallNode::class:
                return new NodeEmitter\CallEmitter($outputEmitter);
            case Ast\IfNode::class:
                return new NodeEmitter\IfEmitter($outputEmitter);
            case Ast\ApplyNode::class:
                return new NodeEmitter\ApplyEmitter($outputEmitter);
            case Ast\VectorNode::class:
                return new NodeEmitter\VectorEmitter($outputEmitter);
            case Ast\PhpNewNode::class:
                return new NodeEmitter\PhpNewEmitter($outputEmitter);
            case Ast\PhpVarNode::class:
                return new NodeEmitter\PhpVarEmitter($outputEmitter);
            case Ast\PhpObjectCallNode::class:
                return new NodeEmitter\PhpObjectCallEmitter($outputEmitter);
            case Ast\RecurNode::class:
                return new NodeEmitter\RecurEmitter($outputEmitter);
            case Ast\ThrowNode::class:
                return new NodeEmitter\ThrowEmitter($outputEmitter);
            case Ast\TryNode::class:
                return new NodeEmitter\TryEmitter($outputEmitter);
            case Ast\CatchNode::class:
                return new NodeEmitter\CatchEmitter($outputEmitter);
            case Ast\PhpArrayGetNode::class:
                return new NodeEmitter\PhpArrayGetEmitter($outputEmitter);
            case Ast\PhpArraySetNode::class:
                return new NodeEmitter\PhpArraySetEmitter($outputEmitter);
            case Ast\PhpArrayUnsetNode::class:
                return new NodeEmitter\PhpArrayUnsetEmitter($outputEmitter);
            case Ast\PhpClassNameNode::class:
                return new NodeEmitter\PhpClassNameEmitter($outputEmitter);
            case Ast\PhpArrayPushNode::class:
                return new NodeEmitter\PhpArrayPushEmitter($outputEmitter);
            case Ast\ForeachNode::class:
                return new NodeEmitter\ForeachEmitter($outputEmitter);
            case Ast\DefStructNode::class:
                return new NodeEmitter\DefStructEmitter($outputEmitter, new MethodEmitter($outputEmitter));
            case Ast\PhpObjectSetNode::class:
                return new NodeEmitter\PhpObjectSetEmitter($outputEmitter);
            case Ast\MapNode::class:
                return new NodeEmitter\MapEmitter($outputEmitter);
            case Ast\SetVarNode::class:
                return new NodeEmitter\SetVarEmitter($outputEmitter);
            case Ast\DefInterfaceNode::class:
                return new NodeEmitter\DefInterfaceEmitter($outputEmitter);
            default:
                throw NotSupportedAstException::withClassName($astNodeClassName);
        }
    }
}
