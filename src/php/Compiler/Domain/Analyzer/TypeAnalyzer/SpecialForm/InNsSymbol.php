<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function is_string;
use function str_replace;

/**
 * (in-ns namespace)
 *
 * Switches to an existing namespace without creating it. Intended for
 * REPL use — e.g. navigating into a namespace to inspect or test private
 * functions interactively. Avoid using in source files: the build system
 * assumes one namespace per file, and in-ns causes namespace collisions
 * in the dependency resolver.
 */
final class InNsSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): InNsNode
    {
        $listCount = $list->count();

        if ($listCount < 2) {
            throw AnalyzerException::withLocation("'in-ns requires exactly 1 argument (the namespace)", $list);
        }

        if ($listCount > 2) {
            throw AnalyzerException::withLocation("'in-ns requires exactly 1 argument, got " . ($listCount - 1), $list);
        }

        $nsArg = $list->get(1);

        if (!($nsArg instanceof Symbol) && !is_string($nsArg)) {
            throw AnalyzerException::withLocation("First argument of 'in-ns must be a Symbol or String, got: " . get_debug_type($nsArg), $list);
        }

        $rawNs = $nsArg instanceof Symbol ? $nsArg->getName() : $nsArg;

        if (trim($rawNs) === '') {
            throw AnalyzerException::withLocation('Namespace cannot be empty', $list);
        }

        // Accept `.` as an alternate namespace separator (Clojure / `.cljc`
        // style) and rewrite it to Phel's canonical `\`.
        $ns = str_replace('.', '\\', $rawNs);

        $this->analyzer->setNamespace($ns);

        ReplReferInjector::injectIfReplMode($this->analyzer, $ns);

        return new InNsNode($ns, $list->getStartLocation());
    }
}
