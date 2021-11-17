<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

final class DefStructInterface
{
    private string $absoluteInterfaceName;
    /** @var list<DefStructMethod> */
    private array $methods;

    /**
     * @param list<DefStructMethod> $methods;
     */
    public function __construct(string $absoluteInterfaceName, array $methods)
    {
        $this->absoluteInterfaceName = $absoluteInterfaceName;
        $this->methods = $methods;
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
