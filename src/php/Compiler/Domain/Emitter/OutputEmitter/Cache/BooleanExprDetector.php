<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\PhpFunctionReturnTypes;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Shared\TagResolver;

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
 *
 * @internal
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
            // The set of bool-returning built-ins is not repeated here: it is
            // whatever {@see PhpFunctionReturnTypes} already vouches for, and
            // that table's membership rule (exactly one primitive on every
            // non-throwing input) is precisely the guarantee needed to skip
            // the adapter. A hand-written copy drifted behind it.
            $bare = str_starts_with($name, '\\') ? substr($name, 1) : $name;
            return PhpFunctionReturnTypes::strictReturnTypeOf($bare) === 'bool';
        }

        // A call to a global whose return `:tag` is `bool`. `FnSymbol` emits
        // that tag as a `: bool` return type on every arity's closure, so PHP
        // enforces it on the way out and the value cannot be nil or anything
        // else. A `defn` whose arities disagree carries no tag at all, so a
        // partially bool-returning function is never mistaken for a total one.
        if ($fn instanceof GlobalVarNode && TagResolver::fromMeta($fn->getMeta()) === 'bool') {
            return true;
        }

        // A `CallNode` that the `CallSpecialization` layer lowers to a
        // bool-typed PHP expression is also a hard bool — `IfEmitter`
        // can splice it into the test slot without the truthy adapter.
        return CallSpecialization::isBoolReturningSpecialisation($node);
    }
}
