<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironment;
use Phel\Lang\SourceLocation;

final class TryNode extends Node
{
    private Node $body;

    /** @var CatchNode[] */
    private array $catches;

    private ?Node $finally;

    public function __construct(
        NodeEnvironment $env,
        Node $body,
        array $catches,
        ?Node $finally = null,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->body = $body;
        $this->catches = $catches;
        $this->finally = $finally;
    }

    public function getBody(): Node
    {
        return $this->body;
    }

    /**
     * @return CatchNode[]
     */
    public function getCatches(): array
    {
        return $this->catches;
    }

    public function getFinally(): ?Node
    {
        return $this->finally;
    }
}
