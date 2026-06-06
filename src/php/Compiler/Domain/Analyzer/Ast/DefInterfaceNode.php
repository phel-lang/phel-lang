<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefInterfaceNode extends AbstractNode
{
    /**
     * @param list<DefInterfaceMethod> $methods
     * @param list<PhpClassConst>      $consts
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $namespace,
        private readonly Symbol $name,
        private readonly array $methods,
        private readonly array $consts = [],
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
     * @return list<PhpClassConst>
     */
    public function getConsts(): array
    {
        return $this->consts;
    }

    /**
     * @return list<DefInterfaceMethod>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
