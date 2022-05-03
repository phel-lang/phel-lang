<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Emitter\Exceptions\NotSupportedAstException;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\MethodEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

final class NodeEmitterFactory
{
    public function createNodeEmitter(
        OutputEmitterInterface $outputEmitter,
        string $astNodeClassName
    ): NodeEmitterInterface {
        switch ($astNodeClassName) {
            case \Phel\Compiler\Domain\Analyzer\Ast\NsNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\NsEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\DefNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\LiteralNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LiteralEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\QuoteNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\QuoteEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\FnNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\FnAsClassEmitter($outputEmitter, new MethodEmitter($outputEmitter));
            case \Phel\Compiler\Domain\Analyzer\Ast\DoNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DoEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\LetNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LetEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LocalVarEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\GlobalVarEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\CallNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CallEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\IfNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\ApplyNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\VectorNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\VectorEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpNewEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpVarEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpObjectCallEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\RecurNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\RecurEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\ThrowNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ThrowEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\TryNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\TryEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\CatchNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CatchEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayGetEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArraySetEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayUnsetEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpClassNameEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayPushEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\ForeachNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ForeachEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\DefStructNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefStructEmitter($outputEmitter, new MethodEmitter($outputEmitter));
            case \Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpObjectSetEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\MapNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\MapEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\SetVarNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\SetVarEmitter($outputEmitter);
            case \Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode::class:
                return new \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefInterfaceEmitter($outputEmitter);
            default:
                throw NotSupportedAstException::withClassName($astNodeClassName);
        }
    }
}
