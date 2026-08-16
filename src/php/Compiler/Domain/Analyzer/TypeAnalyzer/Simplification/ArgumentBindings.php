<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

/**
 * How {@see CallInliner} matched a call's arguments to the callee's
 * parameters: the `let` bindings the arguments that could not be substituted
 * need (empty when every argument went straight into the body), the
 * parameter-name to node substitution map the body rebase walks with, and the
 * call-site environment extended with each binding's shadow.
 *
 * @internal
 */
final readonly class ArgumentBindings
{
    /**
     * @param list<BindingNode>           $bindings
     * @param array<string, AbstractNode> $paramMap
     */
    public function __construct(
        public array $bindings,
        public array $paramMap,
        public NodeEnvironmentInterface $scopeEnv,
    ) {}
}
