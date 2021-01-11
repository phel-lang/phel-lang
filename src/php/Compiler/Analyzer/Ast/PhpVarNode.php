<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

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

    private string $name;

    public static function withReturnContext(string $name, ?SourceLocation $sourceLocation = null): self
    {
        $returnEnv = NodeEnvironment::empty()
            ->withContext(NodeEnvironmentInterface::CONTEXT_RETURN);

        return new self($returnEnv, $name, $sourceLocation);
    }

    public function __construct(NodeEnvironmentInterface $env, string $name, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->name = $name;
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
        return \is_callable($this->name) || in_array($this->name, self::CALLABLE_KEYWORDS);
    }
}
