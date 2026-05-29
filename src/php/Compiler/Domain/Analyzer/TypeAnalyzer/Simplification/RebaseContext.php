<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * Immutable walk state threaded through {@see CallInliner}'s body
 * rebasing. Bundles the call-site environment, the context the node
 * being built should emit under, the source location to stamp, the
 * parameter-to-argument substitution map, and whether the walk is
 * currently inside the callee body (`true`) or an argument subtree
 * (`false`).
 */
final readonly class RebaseContext
{
    /**
     * @param array<string, AbstractNode> $paramMap
     */
    public function __construct(
        public NodeEnvironmentInterface $env,
        public string $context,
        public ?SourceLocation $loc,
        public array $paramMap,
        public bool $inBody,
    ) {}

    public function withContext(string $context): self
    {
        return new self($this->env, $context, $this->loc, $this->paramMap, $this->inBody);
    }

    public function asArgument(): self
    {
        return new self($this->env, $this->context, $this->loc, $this->paramMap, false);
    }

    public function targetEnv(): NodeEnvironmentInterface
    {
        return $this->env->withContext($this->context);
    }
}
