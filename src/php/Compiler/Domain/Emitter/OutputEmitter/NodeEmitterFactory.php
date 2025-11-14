<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Emitter\Exceptions\NotSupportedAstException;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CatchEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefExceptionEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefInterfaceEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DefStructEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\DoEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\FnAsClassEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ForeachEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\GlobalVarEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\InNsEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LetEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LiteralEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LoadEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LocalVarEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\MapEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\MethodEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\MultiFnAsClassEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\NsEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayGetEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayPushEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArraySetEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpArrayUnsetEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpClassNameEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpNewEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpObjectCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpObjectSetEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\PhpVarEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\QuoteEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\RecurEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\SetEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\SetVarEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ThrowEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\TryEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\VectorEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

final class NodeEmitterFactory
{
    /** @var array<string, NodeEmitterInterface> */
    private array $emitterCache = [];

    /** @var array<int, MethodEmitter> */
    private array $methodEmitterCache = [];

    public function createNodeEmitter(
        OutputEmitterInterface $outputEmitter,
        string $astNodeClassName,
    ): NodeEmitterInterface {
        $cacheKey = spl_object_id($outputEmitter) . ':' . $astNodeClassName;

        if (!isset($this->emitterCache[$cacheKey])) {
            $this->emitterCache[$cacheKey] = $this->instantiateEmitter($outputEmitter, $astNodeClassName);
        }

        return $this->emitterCache[$cacheKey];
    }

    private function instantiateEmitter(
        OutputEmitterInterface $outputEmitter,
        string $astNodeClassName,
    ): NodeEmitterInterface {
        $methodEmitter = $this->getMethodEmitter($outputEmitter);

        return match ($astNodeClassName) {
            NsNode::class => new NsEmitter($outputEmitter),
            InNsNode::class => new InNsEmitter($outputEmitter),
            LoadNode::class => new LoadEmitter($outputEmitter),
            DefNode::class => new DefEmitter($outputEmitter),
            LiteralNode::class => new LiteralEmitter($outputEmitter),
            QuoteNode::class => new QuoteEmitter($outputEmitter),
            FnNode::class => new FnAsClassEmitter($outputEmitter, $methodEmitter),
            DoNode::class => new DoEmitter($outputEmitter),
            LetNode::class => new LetEmitter($outputEmitter),
            LocalVarNode::class => new LocalVarEmitter($outputEmitter),
            GlobalVarNode::class => new GlobalVarEmitter($outputEmitter),
            CallNode::class => new CallEmitter($outputEmitter),
            IfNode::class => new IfEmitter($outputEmitter),
            ApplyNode::class => new ApplyEmitter($outputEmitter),
            VectorNode::class => new VectorEmitter($outputEmitter),
            SetNode::class => new SetEmitter($outputEmitter),
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
            DefStructNode::class => new DefStructEmitter($outputEmitter, $methodEmitter),
            DefExceptionNode::class => new DefExceptionEmitter($outputEmitter),
            PhpObjectSetNode::class => new PhpObjectSetEmitter($outputEmitter),
            MapNode::class => new MapEmitter($outputEmitter),
            SetVarNode::class => new SetVarEmitter($outputEmitter),
            DefInterfaceNode::class => new DefInterfaceEmitter($outputEmitter),
            MultiFnNode::class => new MultiFnAsClassEmitter($outputEmitter),
            default => throw NotSupportedAstException::withClassName($astNodeClassName),
        };
    }

    private function getMethodEmitter(OutputEmitterInterface $outputEmitter): MethodEmitter
    {
        $key = spl_object_id($outputEmitter);

        if (!isset($this->methodEmitterCache[$key])) {
            $this->methodEmitterCache[$key] = new MethodEmitter($outputEmitter);
        }

        return $this->methodEmitterCache[$key];
    }
}
