<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Lang\Symbol;

final class RecurFrame
{
    private bool $isActive = false;

    /** @var Symbol[] */
    private array $params;

    /**
     * @param Symbol[] $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return Symbol[]
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
