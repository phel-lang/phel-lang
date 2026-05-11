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
use function str_replace;

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
     * Fully qualified PHP name for emission. Backslash, dot, and forward
     * slash are interchangeable namespace separators in the source name; a
     * leading `\` ensures resolution against the root namespace regardless
     * of the surrounding `namespace ...;` declaration in emitted PHP.
     */
    public function getAbsoluteName(): string
    {
        if (!$this->isNamespaced()) {
            return $this->name;
        }

        $normalized = str_replace(['/', '.'], '\\', $this->name);

        return '\\' . ltrim($normalized, '\\');
    }

    public function isInfix(): bool
    {
        return in_array($this->name, self::INFIX_OPERATORS, true);
    }

    public function isCallable(): bool
    {
        return is_callable($this->getAbsoluteName())
            || in_array($this->name, self::CALLABLE_KEYWORDS, true);
    }

    private function isNamespaced(): bool
    {
        if (str_contains($this->name, '\\')) {
            return true;
        }

        // `.` and `/` are also infix PHP operators (string concat / division);
        // a single-token infix is never a namespaced reference.
        if ($this->isInfix()) {
            return false;
        }

        return str_contains($this->name, '.') || str_contains($this->name, '/');
    }
}
