<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

use function count;
use function str_starts_with;

/**
 * (break).
 *
 * Pauses execution and opens an interactive debugger sub-REPL with access to
 * the lexical locals in scope. It desugars to a call to the runtime helper
 * `\Phel::breakpoint`, passing a map of local name => local value so the
 * sub-REPL can inspect the surrounding bindings.
 */
final class BreakSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $sym = $list->first();
        if (!$sym instanceof Symbol || $sym->getName() !== Symbol::NAME_BREAK) {
            throw AnalyzerException::withLocation("This is not a 'break.", $list);
        }

        if (count($list) !== 1) {
            throw AnalyzerException::withLocation("'break takes no arguments", $list);
        }

        return $this->analyzer->analyze($this->synthesizeBreakpointCall($list, $env), $env);
    }

    /**
     * Builds `(php/:: \Phel (breakpoint {"<name>" <name-symbol> ...}))` for the
     * lexical locals in scope, then hands it back to the analyzer. Re-analysis
     * resolves shadowed locals through the regular symbol path, so no manual
     * shadow handling is needed here.
     *
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentListInterface<mixed>
     */
    private function synthesizeBreakpointCall(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
    ): PersistentListInterface {
        $localsMap = TypeFactory::getInstance()->persistentMapFromArray($this->localsKeyValues($list, $env));

        $breakpointCall = Phel::list([
            Symbol::create('breakpoint')->copyLocationFrom($list),
            $localsMap,
        ])->copyLocationFrom($list);

        return Phel::list([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL)->copyLocationFrom($list),
            Symbol::create('\\Phel')->copyLocationFrom($list),
            $breakpointCall,
        ])->copyLocationFrom($list);
    }

    /**
     * Flat `name, symbol, name, symbol, ...` list for the locals map. Locals are
     * deduped by name (first occurrence wins) and internal gensym locals
     * (`__phel_*`) are skipped so only user-visible bindings are captured.
     *
     * @param PersistentListInterface<mixed> $list
     *
     * @return list<mixed>
     */
    private function localsKeyValues(PersistentListInterface $list, NodeEnvironmentInterface $env): array
    {
        $kvs = [];
        $seen = [];
        foreach ($env->getLocals() as $local) {
            $name = $local->getName();
            if (isset($seen[$name])) {
                continue;
            }

            if (str_starts_with($name, '__phel_')) {
                continue;
            }

            $seen[$name] = true;
            $kvs[] = $name;
            $kvs[] = Symbol::create($name)->copyLocationFrom($list);
        }

        return $kvs;
    }
}
