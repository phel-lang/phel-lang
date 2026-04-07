<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

use function in_array;
use function is_callable;
use function ltrim;
use function str_contains;

final class PhpVarNode extends AbstractNode
{
    public const array INFIX_OPERATORS = [
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

    public const array CALLABLE_KEYWORDS = [
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

    /**
     * Returns the name suitable for emitting as a PHP function reference.
     *
     * Namespaced PHP functions (containing a backslash) are returned as
     * fully qualified names with a single leading backslash so they resolve
     * against the global namespace, regardless of any active `namespace ...;`
     * declaration in the emitted PHP file.
     */
    public function getAbsoluteName(): string
    {
        if (!str_contains($this->name, '\\')) {
            return $this->name;
        }

        return '\\' . ltrim($this->name, '\\');
    }

    public function isInfix(): bool
    {
        return in_array($this->name, self::INFIX_OPERATORS, true);
    }

    public function isCallable(): bool
    {
        return is_callable($this->name) || in_array($this->name, self::CALLABLE_KEYWORDS, true);
    }
}
