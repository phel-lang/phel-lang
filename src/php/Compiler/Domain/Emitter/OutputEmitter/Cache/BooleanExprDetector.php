<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;

use function in_array;
use function is_bool;
use function str_starts_with;
use function substr;

/**
 * Recognises AST nodes whose runtime value is guaranteed to be a PHP
 * `bool`. The `IfEmitter` uses this to skip the `Truthy::isTruthy()`
 * dance (the `($__truthy = …) !== null && $__truthy !== false` wrap) and
 * emit a direct ternary / `if` test, which saves an assignment plus two
 * comparisons per check.
 *
 * Conservative on purpose: only forms with a hard PHP-level bool
 * guarantee are recognised. Anything else routes through the legacy
 * truthy wrap to preserve `nil`/`false`/anything-else semantics.
 */
final class BooleanExprDetector
{
    /**
     * PHP infix comparison/identity operators that always yield bool.
     * Subset of {@see PhpVarNode::INFIX_OPERATORS}; arithmetic / bitwise
     * operators are excluded.
     */
    private const array BOOL_INFIX = [
        '===',
        '!==',
        '==',
        '!=',
        '<',
        '>',
        '<=',
        '>=',
        'instanceof',
    ];

    /**
     * PHP built-in callables that always return bool. Restricted to the
     * common type predicates to keep the recogniser self-evidently safe.
     */
    private const array BOOL_PHP_FUNCTIONS = [
        'array_is_list',
        'array_key_exists',
        'in_array',
        'is_a',
        'is_array',
        'is_bool',
        'is_callable',
        'is_countable',
        'is_float',
        'is_int',
        'is_iterable',
        'is_null',
        'is_numeric',
        'is_object',
        'is_resource',
        'is_string',
        'is_subclass_of',
        'method_exists',
        'property_exists',
    ];

    public static function isBool(AbstractNode $node): bool
    {
        if ($node instanceof LiteralNode) {
            return is_bool($node->getValue());
        }

        if (!$node instanceof CallNode) {
            return false;
        }

        $fn = $node->getFn();
        if ($fn instanceof PhpVarNode) {
            $name = $fn->getName();

            if ($fn->isInfix() && in_array($name, self::BOOL_INFIX, true)) {
                return true;
            }

            // Match both bare (`is_int`) and namespaced (`\is_int`) forms.
            $bare = str_starts_with($name, '\\') ? substr($name, 1) : $name;
            return in_array($bare, self::BOOL_PHP_FUNCTIONS, true);
        }

        // A `CallNode` that the `CallSpecialization` layer lowers to a
        // bool-typed PHP expression is also a hard bool — `IfEmitter`
        // can splice it into the test slot without the truthy adapter.
        return CallSpecialization::isBoolReturningSpecialisation($node);
    }
}
