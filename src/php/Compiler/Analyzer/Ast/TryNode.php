<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class TryNode extends AbstractNode
{
    private AbstractNode $body;

    /** @var CatchNode[] */
    private array $catches;

    private ?AbstractNode $finally;

    /**
     * @param CatchNode[] $catches
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $body,
        array $catches,
        ?AbstractNode $finally = null,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->body = $body;
        $this->catches = $catches;
        $this->finally = $finally;
    }

    public function getBody(): AbstractNode
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

    public function getFinally(): ?AbstractNode
    {
        return $this->finally;
    }
}
