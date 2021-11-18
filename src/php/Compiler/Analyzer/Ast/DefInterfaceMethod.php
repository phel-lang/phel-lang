<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Lang\Symbol;

final class DefInterfaceMethod
{
    private Symbol $name;
    /** @var list<string> */
    private array $arguments;
    private ?string $comment;

    public function __construct(Symbol $name, array $arguments, ?string $comment = null)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->comment = $comment;
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
        return count($this->getArguments());
    }

    public function getArgumentsWithoutFirst(): array
    {
        return array_slice($this->getArguments(), 1);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
