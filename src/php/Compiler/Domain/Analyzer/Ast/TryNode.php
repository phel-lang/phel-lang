<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class TryNode extends AbstractNode
{
    /**
     * @param list<CatchNode> $catches
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private AbstractNode $body,
        private array $catches,
        private ?AbstractNode $finally = null,
        ?SourceLocation $sourceLocation = null
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
