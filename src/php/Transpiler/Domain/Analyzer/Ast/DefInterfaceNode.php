<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class DefInterfaceNode extends AbstractNode
{
    /**
     * @param list<DefInterfaceMethod> $methods
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $namespace,
        private readonly Symbol $name,
        private readonly array $methods,
        ?SourceLocation $startSourceLocation = null,
    ) {
        parent::__construct($env, $startSourceLocation);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    /**
     * @return list<DefInterfaceMethod>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
