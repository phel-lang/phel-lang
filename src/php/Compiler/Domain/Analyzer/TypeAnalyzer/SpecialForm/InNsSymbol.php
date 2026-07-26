<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function is_string;
use function str_replace;

/**
 * (in-ns namespace)
 *
 * Switches to an existing namespace without creating it. Two uses: at the
 * REPL, navigating into a namespace to inspect or test private functions;
 * and as the first form of a file pulled in with (load ...), which must
 * join its caller's namespace this way - LoadEmitter enforces it at runtime.
 *
 * Do not use it to switch namespace part-way through a file: the build
 * system assumes one namespace per file, and only the first declaration
 * reaches the emitted PHP.
 *
 * @internal
 */
final class InNsSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentListInterface<mixed> $list
     */
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

        if ($nsArg instanceof Symbol) {
            BackslashSeparatorDeprecator::getInstance()->maybeWarn($nsArg);
        } else {
            $location = $list->getStartLocation();
            if ($location instanceof SourceLocation) {
                BackslashSeparatorDeprecator::getInstance()->maybeWarnString($nsArg, $location);
            }
        }

        $rawNs = $nsArg instanceof Symbol ? $nsArg->getName() : $nsArg;

        if (trim($rawNs) === '') {
            throw AnalyzerException::withLocation('Namespace cannot be empty', $list);
        }

        // Accept `\` as an alternate namespace separator (legacy Phel form)
        // and rewrite it to the canonical `.`.
        $ns = str_replace('\\', '.', $rawNs);

        $this->analyzer->setNamespace($ns);
        DefaultLangAliasesRegistrar::register($this->analyzer, $ns);

        ReplReferInjector::injectIfReplMode($this->analyzer, $ns);

        return new InNsNode($ns, $list->getStartLocation());
    }
}
