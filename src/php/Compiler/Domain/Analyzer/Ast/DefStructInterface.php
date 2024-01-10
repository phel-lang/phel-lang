<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

final readonly class DefStructInterface
{
    /**
     * @param list<DefStructMethod> $methods
     */
    public function __construct(
        private string $absoluteInterfaceName,
        private array $methods,
    ) {
    }

    public function getAbsoluteInterfaceName(): string
    {
        return $this->absoluteInterfaceName;
    }

    /**
     * @return list<DefStructMethod>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
