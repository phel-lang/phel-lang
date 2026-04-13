<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use InvalidArgumentException;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadPathResolver;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function get_debug_type;
use function is_string;

/**
 * (load path).
 *
 * Loads a Phel source file into the caller namespace at runtime.
 * Path resolution follows the spirit of Clojure's `clojure.core/load`:
 * a path beginning with a slash is classpath-absolute (searched
 * against `phel\repl/src-dirs`); otherwise it is resolved relative to
 * the caller file's compile-time location, so mutations to runtime
 * `*file*` cannot break resolution.
 */
final readonly class LoadSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private LoadPathResolver $pathResolver = new LoadPathResolver(),
    ) {}

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): LoadNode
    {
        $pathArg = $this->extractPathArg($list);
        $callerFile = $list->getStartLocation()?->getFile();

        try {
            $resolution = $this->pathResolver->resolve($callerFile, $pathArg);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw AnalyzerException::withLocation($invalidArgumentException->getMessage(), $list);
        }

        return new LoadNode(
            $resolution,
            $this->analyzer->getNamespace(),
            $list->getStartLocation(),
        );
    }

    private function extractPathArg(PersistentListInterface $list): string
    {
        $listCount = $list->count();

        if ($listCount < 2) {
            throw AnalyzerException::withLocation("'load requires exactly 1 argument (the file path)", $list);
        }

        if ($listCount > 2) {
            throw AnalyzerException::withLocation("'load requires exactly 1 argument, got " . ($listCount - 1), $list);
        }

        $pathArg = $list->get(1);

        if (!is_string($pathArg)) {
            throw AnalyzerException::withLocation("First argument of 'load must be a string, got: " . get_debug_type($pathArg), $list);
        }

        return $pathArg;
    }
}
