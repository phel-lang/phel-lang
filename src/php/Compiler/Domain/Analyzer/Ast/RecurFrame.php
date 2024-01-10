<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Lang\Symbol;

final class RecurFrame
{
    private bool $isActive = false;

    /**
     * @param list<Symbol> $params
     */
    public function __construct(
        private readonly array $params,
    ) {
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
     * @return list<Symbol>
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
