<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use ArgumentCountError;
use Phel\Lang\FnInterface;
use Throwable;

use function class_exists;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * PHP words a Phel fn called with too few arguments as a failed call to an
 * anonymous class's `__invoke()`, naming whichever PHP file made the call. The
 * report reads it as the Phel fn instead, in the shape the compile-time arity
 * error uses.
 *
 * @internal
 */
final readonly class RuntimeArityMessage
{
    private const string PHP_ARITY_MESSAGE = '/^Too few arguments to function .+?, (\d+) passed .*?and (exactly|at least) (\d+) expected$/s';

    private const string ANONYMOUS_FN = 'fn';

    public function __construct(
        private CompiledFnName $fnName,
    ) {}

    public function rewrite(Throwable $e): ?string
    {
        if (!$e instanceof ArgumentCountError) {
            return null;
        }

        if (preg_match(self::PHP_ARITY_MESSAGE, $e->getMessage(), $m) !== 1) {
            return null;
        }

        $fnName = $this->calledFnName($e);
        if ($fnName === null) {
            return null;
        }

        return sprintf(
            'Wrong number of args (%s) passed to %s, expected %s%s',
            $m[1],
            $fnName,
            $m[2] === 'at least' ? 'at least ' : '',
            $m[3],
        );
    }

    /**
     * The innermost frame is the call that failed. A frame of a compiled fn
     * class names the fn by its `BOUND_TO` constant when the call is its
     * `__invoke`. A `fn` that captures nothing compiles to a plain closure,
     * which has no name of its own.
     */
    private function calledFnName(Throwable $e): ?string
    {
        $frame = $e->getTrace()[0] ?? null;
        $function = $frame['function'] ?? '';
        $class = $frame['class'] ?? null;

        if (str_starts_with($function, '{closure')) {
            return self::ANONYMOUS_FN;
        }

        if ($function !== '__invoke' || !is_string($class) || !class_exists($class) || !is_subclass_of($class, FnInterface::class)) {
            return null;
        }

        $namespace = $this->fnName->namespaceOf($class);
        if ($namespace === null) {
            return self::ANONYMOUS_FN;
        }

        $definedName = $this->fnName->definedName($class);
        if ($definedName !== null) {
            return $namespace . '/' . $definedName;
        }

        // Without the registry an underscore could be `-` or `_` in the
        // source, so name the namespace rather than guess. That happens under
        // `phel profile`, whose registry holds a wrapper around the fn.
        $compiledName = $this->fnName->compiledName($class);

        return str_contains($compiledName, '_')
            ? 'a fn in ' . $namespace
            : $namespace . '/' . $compiledName;
    }
}
