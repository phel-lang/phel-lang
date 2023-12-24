<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Lang\Symbol;

use function array_slice;
use function count;

final readonly class DefInterfaceMethod
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        private Symbol $name,
        private array $arguments,
        private ?string $comment = null,
    ) {
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgumentCount(): int
    {
        return count($this->arguments);
    }

    public function getArgumentsWithoutFirst(): array
    {
        return array_slice($this->arguments, 1);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
