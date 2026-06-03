<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * A single PHP 8 named argument in a `php/new`/`php/->`/`php/::` call,
 * introduced after the `:&` marker. Emits as `name: <value>` so the value
 * binds to the PHP parameter by name rather than by position.
 */
final class PhpNamedArgNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $name,
        private readonly AbstractNode $valueExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValueExpr(): AbstractNode
    {
        return $this->valueExpr;
    }
}
