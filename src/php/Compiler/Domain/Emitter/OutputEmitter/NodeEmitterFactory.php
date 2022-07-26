<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast;
use Phel\Compiler\Domain\Emitter\Exceptions\NotSupportedAstException;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

final class NodeEmitterFactory
{
    public function createNodeEmitter(
        OutputEmitterInterface $outputEmitter,
        string $astNodeClassName,
    ): NodeEmitterInterface {
        return match ($astNodeClassName) {
            Ast\NsNode::class => new NodeEmitter\NsEmitter($outputEmitter),
            Ast\DefNode::class => new NodeEmitter\DefEmitter($outputEmitter),
            Ast\LiteralNode::class => new NodeEmitter\LiteralEmitter($outputEmitter),
            Ast\QuoteNode::class => new NodeEmitter\QuoteEmitter($outputEmitter),
            Ast\FnNode::class => new NodeEmitter\FnAsClassEmitter($outputEmitter, new NodeEmitter\MethodEmitter($outputEmitter)),
            Ast\DoNode::class => new NodeEmitter\DoEmitter($outputEmitter),
            Ast\LetNode::class => new NodeEmitter\LetEmitter($outputEmitter),
            Ast\LocalVarNode::class => new NodeEmitter\LocalVarEmitter($outputEmitter),
            Ast\GlobalVarNode::class => new NodeEmitter\GlobalVarEmitter($outputEmitter),
            Ast\CallNode::class => new NodeEmitter\CallEmitter($outputEmitter),
            Ast\IfNode::class => new NodeEmitter\IfEmitter($outputEmitter),
            Ast\ApplyNode::class => new NodeEmitter\ApplyEmitter($outputEmitter),
            Ast\VectorNode::class => new NodeEmitter\VectorEmitter($outputEmitter),
            Ast\PhpNewNode::class => new NodeEmitter\PhpNewEmitter($outputEmitter),
            Ast\PhpVarNode::class => new NodeEmitter\PhpVarEmitter($outputEmitter),
            Ast\PhpObjectCallNode::class => new NodeEmitter\PhpObjectCallEmitter($outputEmitter),
            Ast\RecurNode::class => new NodeEmitter\RecurEmitter($outputEmitter),
            Ast\ThrowNode::class => new NodeEmitter\ThrowEmitter($outputEmitter),
            Ast\TryNode::class => new NodeEmitter\TryEmitter($outputEmitter),
            Ast\CatchNode::class => new NodeEmitter\CatchEmitter($outputEmitter),
            Ast\PhpArrayGetNode::class => new NodeEmitter\PhpArrayGetEmitter($outputEmitter),
            Ast\PhpArraySetNode::class => new NodeEmitter\PhpArraySetEmitter($outputEmitter),
            Ast\PhpArrayUnsetNode::class => new NodeEmitter\PhpArrayUnsetEmitter($outputEmitter),
            Ast\PhpClassNameNode::class => new NodeEmitter\PhpClassNameEmitter($outputEmitter),
            Ast\PhpArrayPushNode::class => new NodeEmitter\PhpArrayPushEmitter($outputEmitter),
            Ast\ForeachNode::class => new NodeEmitter\ForeachEmitter($outputEmitter),
            Ast\DefStructNode::class => new NodeEmitter\DefStructEmitter($outputEmitter, new NodeEmitter\MethodEmitter($outputEmitter)),
            Ast\PhpObjectSetNode::class => new NodeEmitter\PhpObjectSetEmitter($outputEmitter),
            Ast\MapNode::class => new NodeEmitter\MapEmitter($outputEmitter),
            Ast\SetVarNode::class => new NodeEmitter\SetVarEmitter($outputEmitter),
            Ast\DefInterfaceNode::class => new NodeEmitter\DefInterfaceEmitter($outputEmitter),
            default => throw NotSupportedAstException::withClassName($astNodeClassName),
        };
    }
}
