<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

use function count;
use function is_callable;

/**
 * Expands macro forms without going through the full analyze+emit pipeline.
 * Returns Phel forms (not PHP code), suitable for nREPL macroexpand support.
 */
final readonly class MacroExpander
{
    public function __construct(
        private GlobalEnvironmentInterface $globalEnvironment,
    ) {}

    /**
     * Expands the given form once if it is a macro call.
     * If the form is not a macro call, returns it unchanged.
     */
    public function macroexpand1(TypeInterface|string|float|int|bool|null $form): TypeInterface|string|float|int|bool|null
    {
        if (!$form instanceof PersistentListInterface || $form->count() === 0) {
            return $form;
        }

        $first = $form->first();
        if (!$first instanceof Symbol) {
            return $form;
        }

        $node = $this->globalEnvironment->resolve($first, NodeEnvironment::empty());
        if (!$node instanceof GlobalVarNode) {
            return $form;
        }

        $meta = $node->getMeta();
        $args = $form->rest()->toArray();
        $arity = count($args);

        // Check for inline expansion first
        $inlineFn = $meta[Keyword::create('inline')];
        if ($inlineFn !== null) {
            $arityFn = $meta[Keyword::create('inline-arity')];
            $shouldInline = $arityFn === null || $arityFn($arity);

            if ($shouldInline && is_callable($inlineFn)) {
                return $inlineFn(...$args);
            }
        }

        // Check for macro expansion
        if (!$node->isMacro()) {
            return $form;
        }

        $ns = str_replace('-', '_', $node->getNamespace());
        $name = $node->getName()->getName();
        $fn = Phel::getDefinition($ns, $name);

        if (!is_callable($fn)) {
            return $form;
        }

        return $fn(...$args);
    }

    /**
     * Repeatedly expands the given form until it is no longer a macro call.
     */
    public function macroexpand(TypeInterface|string|float|int|bool|null $form): TypeInterface|string|float|int|bool|null
    {
        $current = $form;

        while (true) {
            $expanded = $this->macroexpand1($current);

            if ($expanded === $current) {
                return $expanded;
            }

            // For TypeInterface, use equals() for proper structural comparison
            if ($expanded instanceof TypeInterface && $current instanceof TypeInterface && $expanded->equals($current)) {
                return $expanded;
            }

            $current = $expanded;
        }
    }
}
