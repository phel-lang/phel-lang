<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use ArgumentCountError;
use Phel\Lang\FnInterface;
use Phel\Lang\Registry;
use Phel\Shared\MungeInterface;
use ReflectionClass;
use Throwable;

use function class_exists;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strrpos;
use function substr;

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
        private MungeInterface $munge,
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

        $reflection = new ReflectionClass($class);
        $boundTo = $reflection->hasConstant('BOUND_TO') ? $reflection->getConstant('BOUND_TO') : null;

        if (!is_string($boundTo) || $boundTo === '') {
            return self::ANONYMOUS_FN;
        }

        $decoded = $this->munge->decodeNs($boundTo);
        $lastSeparator = strrpos($decoded, '\\');

        if ($lastSeparator === false) {
            return $decoded;
        }

        $namespace = str_replace('\\', '.', substr($decoded, 0, $lastSeparator));

        return $namespace . '/' . ($this->definedName($boundTo, $class) ?? substr($decoded, $lastSeparator + 1));
    }

    /**
     * The name the fn was defined under. `BOUND_TO` writes `-` as `_`, so
     * `add-it` and `my_fn` both end in an underscore there, and only the
     * registry still knows which one the source spelled.
     */
    private function definedName(string $boundTo, string $class): ?string
    {
        $lastSeparator = strrpos($boundTo, '\\');
        if ($lastSeparator === false) {
            return null;
        }

        $registryNs = str_replace('\\', '.', substr($boundTo, 0, $lastSeparator));
        foreach (Registry::getInstance()->getDefinitionInNamespace($registryNs) as $name => $value) {
            if ($value instanceof $class) {
                return $name;
            }
        }

        return null;
    }
}
