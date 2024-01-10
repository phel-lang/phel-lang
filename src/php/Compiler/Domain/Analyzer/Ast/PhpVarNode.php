<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

use function in_array;
use function is_callable;

final class PhpVarNode extends AbstractNode
{
    public const INFIX_OPERATORS = [
        '+',
        '-',
        '*',
        '.',
        '/',
        '%',
        '=',
        '=&',
        '<',
        '>',
        '<=',
        '>=',
        '<=>',
        '===',
        '==',
        '!=',
        '!==',
        'instanceof',
        '|',
        '&',
        '**',
        '^',
        '<<',
        '>>',
    ];

    public const CALLABLE_KEYWORDS = [
        'array',
        'die',
        'empty',
        'echo',
        'print',
        'isset',
    ];

    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $name,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public static function withReturnContext(string $name, ?SourceLocation $sourceLocation = null): self
    {
        $returnEnv = NodeEnvironment::empty()
            ->withReturnContext();

        return new self($returnEnv, $name, $sourceLocation);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isInfix(): bool
    {
        return in_array($this->name, self::INFIX_OPERATORS);
    }

    public function isCallable(): bool
    {
        return is_callable($this->name) || in_array($this->name, self::CALLABLE_KEYWORDS);
    }
}
