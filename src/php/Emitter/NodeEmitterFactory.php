<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast;
use Phel\Emitter;
use RuntimeException;

final class NodeEmitterFactory
{
    private const DEFAULT_MAPPING = [
        Ast\NsNode::class => NodeEmitter\NsEmitter::class,
        Ast\DefNode::class => NodeEmitter\DefEmitter::class,
        Ast\LiteralNode::class => NodeEmitter\LiteralEmitter::class,
        Ast\QuoteNode::class => NodeEmitter\QuoteEmitter::class,
        Ast\FnNode::class => NodeEmitter\FnAsClassEmitter::class,
        Ast\DoNode::class => NodeEmitter\DoEmitter::class,
        Ast\LetNode::class => NodeEmitter\LetEmitter::class,
        Ast\LocalVarNode::class => NodeEmitter\LocalVarEmitter::class,
        Ast\GlobalVarNode::class => NodeEmitter\GlobalVarEmitter::class,
        Ast\CallNode::class => NodeEmitter\CallEmitter::class,
        Ast\IfNode::class => NodeEmitter\IfEmitter::class,
        Ast\ApplyNode::class => NodeEmitter\ApplyEmitter::class,
        Ast\TupleNode::class => NodeEmitter\TupleEmitter::class,
        Ast\PhpNewNode::class => NodeEmitter\PhpNewEmitter::class,
        Ast\PhpVarNode::class => NodeEmitter\PhpVarEmitter::class,
        Ast\PhpObjectCallNode::class => NodeEmitter\PhpObjectCallEmitter::class,
        Ast\RecurNode::class => NodeEmitter\RecurEmitter::class,
        Ast\ThrowNode::class => NodeEmitter\ThrowEmitter::class,
        Ast\TryNode::class => NodeEmitter\TryEmitter::class,
        Ast\CatchNode::class => NodeEmitter\CatchEmitter::class,
        Ast\PhpArrayGetNode::class => NodeEmitter\PhpArrayGetEmitter::class,
        Ast\PhpArraySetNode::class => NodeEmitter\PhpArraySetEmitter::class,
        Ast\PhpArrayUnsetNode::class => NodeEmitter\PhpArrayUnsetEmitter::class,
        Ast\PhpClassNameNode::class => NodeEmitter\PhpClassNameEmitter::class,
        Ast\PhpArrayPushNode::class => NodeEmitter\PhpArrayPushEmitter::class,
        Ast\ForeachNode::class => NodeEmitter\ForeachEmitter::class,
        Ast\ArrayNode::class => NodeEmitter\ArrayEmitter::class,
        Ast\TableNode::class => NodeEmitter\TableEmitter::class,
        Ast\DefStructNode::class => NodeEmitter\DefStructEmitter::class,
    ];

    /** @psalm-var array<string,string> */
    private array $mapper;

    public function __construct(array $mapper = self::DEFAULT_MAPPING)
    {
        $this->mapper = $mapper;
    }

    public function createNodeEmitter(Emitter $emitter, string $className): NodeEmitter
    {
        if (!isset($this->mapper[$className])) {
            throw new RuntimeException('Unexpected node: ' . $className);
        }

        $nodeEmitter = new $this->mapper[$className]($emitter);
        assert($nodeEmitter instanceof NodeEmitter);

        return $nodeEmitter;
    }
}
