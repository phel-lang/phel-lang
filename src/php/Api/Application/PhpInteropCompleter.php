<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\PhpInteropContext;

/**
 * Produces PHP-interop completions at a cursor position by combining the
 * {@see PhpInteropContextResolver} (what is being typed) with the
 * {@see PhpInteropReflector} (what the resolved type offers). Returns null when
 * the cursor is not in a PHP-interop position, so callers fall back to the
 * normal Phel completion path.
 */
final readonly class PhpInteropCompleter
{
    public function __construct(
        private PhpInteropContextResolver $contextResolver = new PhpInteropContextResolver(),
        private PhpInteropReflector $reflector = new PhpInteropReflector(),
    ) {}

    /**
     * @return list<Completion>|null
     */
    public function completeAtPoint(string $source, int $line, int $col): ?array
    {
        $context = $this->contextResolver->resolve($source, $line, $col);

        return match ($context->kind) {
            PhpInteropContext::KIND_INSTANCE_MEMBER => $this->reflector->instanceMembers($context->class, $context->prefix),
            PhpInteropContext::KIND_STATIC_MEMBER => $this->reflector->staticMembers($context->class, $context->prefix),
            PhpInteropContext::KIND_CLASS_NAME => $this->reflector->classNames($context->prefix),
            PhpInteropContext::KIND_GLOBAL_FUNCTION => $this->reflector->globalFunctions($context->prefix),
            default => null,
        };
    }
}
