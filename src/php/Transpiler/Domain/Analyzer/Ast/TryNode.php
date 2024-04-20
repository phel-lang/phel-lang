<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class TryNode extends AbstractNode
{
    /**
     * @param list<CatchNode> $catches
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $body,
        private readonly array $catches,
        private readonly ?AbstractNode $finally = null,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getBody(): AbstractNode
    {
        return $this->body;
    }

    /**
     * @return list<CatchNode>
     */
    public function getCatches(): array
    {
        return $this->catches;
    }

    public function getFinally(): ?AbstractNode
    {
        return $this->finally;
    }
}
