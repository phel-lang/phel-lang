<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class ReifyNode extends AbstractNode
{
    /**
     * @param list<DefStructMethod> $methods
     * @param list<Symbol>          $uses
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $methods,
        private readonly array $uses,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return list<DefStructMethod>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return list<Symbol>
     */
    public function getUses(): array
    {
        return $this->uses;
    }
}
