<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ReadModel;

use Phel\Shared\Parser\ReadModel\CodeSnippet;

/**
 * @internal
 */
final readonly class ReaderResult
{
    public function __construct(
        private mixed $ast,
        private CodeSnippet $codeSnippet,
    ) {}

    /**
     * The read form. `mixed` is deliberate and cannot be narrowed to
     * `bool|float|int|string|TypeInterface|null`: a tagged literal is dispatched
     * through `TagRegistry`, whose handlers are `callable(mixed): mixed` and can
     * be registered from Phel at runtime via `(register-tag ...)`, so a handler
     * may hand back any PHP value interop can produce. Callers that only ever
     * see untagged forms narrow with a local `@var` instead.
     */
    public function getAst(): mixed
    {
        return $this->ast;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
