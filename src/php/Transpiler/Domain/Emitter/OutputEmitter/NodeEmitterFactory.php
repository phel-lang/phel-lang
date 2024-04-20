<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Transpiler\Domain\Analyzer\Ast\CallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DefNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DoNode;
use Phel\Transpiler\Domain\Analyzer\Ast\FnNode;
use Phel\Transpiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Transpiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\IfNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\MapNode;
use Phel\Transpiler\Domain\Analyzer\Ast\NsNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Transpiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Transpiler\Domain\Analyzer\Ast\TryNode;
use Phel\Transpiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Transpiler\Domain\Emitter\Exceptions\NotSupportedAstException;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\CallEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\CatchEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefInterfaceEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefStructEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\DoEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\FnAsClassEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\ForeachEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\GlobalVarEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\LetEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\LiteralEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\LocalVarEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\MapEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\MethodEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\NsEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayGetEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayPushEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArraySetEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayUnsetEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpClassNameEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpNewEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpObjectCallEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpObjectSetEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpVarEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\QuoteEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\RecurEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\SetVarEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\ThrowEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\TryEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\VectorEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitterInterface;

final class NodeEmitterFactory
{
    public function createNodeEmitter(
        OutputEmitterInterface $outputEmitter,
        string $astNodeClassName,
    ): NodeEmitterInterface {
        return match ($astNodeClassName) {
            NsNode::class => new NsEmitter($outputEmitter),
            DefNode::class => new DefEmitter($outputEmitter),
            LiteralNode::class => new LiteralEmitter($outputEmitter),
            QuoteNode::class => new QuoteEmitter($outputEmitter),
            FnNode::class => new FnAsClassEmitter($outputEmitter, new MethodEmitter($outputEmitter)),
            DoNode::class => new DoEmitter($outputEmitter),
            LetNode::class => new LetEmitter($outputEmitter),
            LocalVarNode::class => new LocalVarEmitter($outputEmitter),
            GlobalVarNode::class => new GlobalVarEmitter($outputEmitter),
            CallNode::class => new CallEmitter($outputEmitter),
            IfNode::class => new IfEmitter($outputEmitter),
            ApplyNode::class => new ApplyEmitter($outputEmitter),
            VectorNode::class => new VectorEmitter($outputEmitter),
            PhpNewNode::class => new PhpNewEmitter($outputEmitter),
            PhpVarNode::class => new PhpVarEmitter($outputEmitter),
            PhpObjectCallNode::class => new PhpObjectCallEmitter($outputEmitter),
            RecurNode::class => new RecurEmitter($outputEmitter),
            ThrowNode::class => new ThrowEmitter($outputEmitter),
            TryNode::class => new TryEmitter($outputEmitter),
            CatchNode::class => new CatchEmitter($outputEmitter),
            PhpArrayGetNode::class => new PhpArrayGetEmitter($outputEmitter),
            PhpArraySetNode::class => new PhpArraySetEmitter($outputEmitter),
            PhpArrayUnsetNode::class => new PhpArrayUnsetEmitter($outputEmitter),
            PhpClassNameNode::class => new PhpClassNameEmitter($outputEmitter),
            PhpArrayPushNode::class => new PhpArrayPushEmitter($outputEmitter),
            ForeachNode::class => new ForeachEmitter($outputEmitter),
            DefStructNode::class => new DefStructEmitter($outputEmitter, new MethodEmitter($outputEmitter)),
            PhpObjectSetNode::class => new PhpObjectSetEmitter($outputEmitter),
            MapNode::class => new MapEmitter($outputEmitter),
            SetVarNode::class => new SetVarEmitter($outputEmitter),
            DefInterfaceNode::class => new DefInterfaceEmitter($outputEmitter),
            default => throw NotSupportedAstException::withClassName($astNodeClassName),
        };
    }
}
