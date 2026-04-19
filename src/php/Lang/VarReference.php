<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * A reference to a global definition created by `def`.
 *
 * Evaluating `(def my-var 123)` returns a `VarReference` which prints as
 * `#'ns/my-var`, giving REPL users a non-nil confirmation that mirrors
 * the Var reference used in other Lisp dialects.
 */
final readonly class VarReference
{
    public function __construct(
        private string $namespace,
        private string $name,
    ) {}

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFullName(): string
    {
        return $this->namespace . '/' . $this->name;
    }
}
