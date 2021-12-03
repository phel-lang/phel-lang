<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefInterfaceNode extends AbstractNode
{
    private string $namespace;
    private Symbol $name;
    /** @var list<DefInterfaceMethod> */
    private array $methods;

    public function __construct(
        NodeEnvironmentInterface $env,
        string $namespace,
        Symbol $name,
        array $methods,
        ?SourceLocation $startSourceLocation = null
    ) {
        parent::__construct($env, $startSourceLocation);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->methods = $methods;
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
