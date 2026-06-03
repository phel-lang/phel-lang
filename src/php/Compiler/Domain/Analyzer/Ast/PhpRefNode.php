<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

/**
 * (php/ref local) — marks a local variable as passed by reference in a PHP
 * interop call, so the wrapping closure captures it with `use(&$local)` and a
 * by-ref PHP parameter can write back into the Phel binding.
 */
final class PhpRefNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly LocalVarNode $local,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getLocal(): LocalVarNode
    {
        return $this->local;
    }

    public function getName(): Symbol
    {
        return $this->local->getName();
    }
}
