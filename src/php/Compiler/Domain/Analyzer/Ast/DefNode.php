<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class DefNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $namespace,
        private readonly Symbol $name,
        private readonly MapNode $meta,
        private readonly AbstractNode $init,
        ?SourceLocation $sourceLocation = null,
        private readonly bool $defonce = false,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getMeta(): MapNode
    {
        return $this->meta;
    }

    public function getInit(): AbstractNode
    {
        return $this->init;
    }

    /**
     * `true` when the def came from a `defonce*` special form. The
     * emitter wraps the `addDefinition` call so the binding is left
     * untouched if it already exists in the registry — a no-op on
     * subsequent file evaluations / REPL reloads.
     */
    public function isDefonce(): bool
    {
        return $this->defonce;
    }
}
