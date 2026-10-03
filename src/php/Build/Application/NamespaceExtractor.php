<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceFileGrouper;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\SourcePathResolver;
use Phel\Build\Domain\IO\FileContentsIoInterface;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\MissingNsFormException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;
use UnexpectedValueException;

use function array_values;
use function in_array;
use function is_array;

/**
 * @internal
 */
final readonly class NamespaceExtractor implements NamespaceExtractorInterface
{
    private NamespaceFileGrouper $grouper;

    private ExcludedScanPaths $excludedPaths;

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        NamespaceSorterInterface $namespaceSorter,
        private FileContentsIoInterface $fileIo,
        ?ExcludedScanPaths $excludedPaths = null,
    ) {
        $this->grouper = new NamespaceFileGrouper($namespaceSorter);
        $this->excludedPaths = $excludedPaths ?? ExcludedScanPaths::none();
    }

    /**
     * @throws ExtractorException
     * @throws CompilerException      when the file's ns form does not analyze
     * @throws MissingNsFormException when the file does not start with an ns form
     * @throws AnalyzerException      when its first form is not ns and fails otherwise
     */
    public function getNamespaceFromFile(string $path): NamespaceInformation
    {
        try {
            $content = $this->fileIo->getContents($path);
        } catch (RuntimeException $runtimeException) {
            // A file listed by a directory scan can be gone by the time it is
            // read - another process writing a temp `.phel` and removing it
            // again is enough. Raise the module's own exception so the scan in
            // findAllNs() skips it, exactly as it already skips a file it
            // cannot parse. A caller asking for one specific file still gets a
            // thrown exception; only the scan swallows it.
            throw ExtractorException::cannotReadFile($path, $runtimeException);
        }

        // Indexing a file is not compiling it: its deprecations are reported
        // by the compile that loads it, never by a scan that only read its
        // `ns` form while some other source was being compiled (#3381).
        return $this->compilerFacade->withoutDeprecations(
            fn(): NamespaceInformation => $this->extractFromContent($content, $path),
        );
    }

    /**
     * @param list<string> $directories
     *
     * @throws ExtractorException
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories, bool $failOnInvalidNsForm = false): array
    {
        $allInfos = [];
        foreach ($directories as $directory) {
            foreach ($this->findAllNs($directory, $failOnInvalidNsForm) as $info) {
                $allInfos[] = $info;
            }
        }

        return $this->grouper->groupAndSort($allInfos);
    }

    /**
     * @throws ExtractorException
     * @throws CompilerException
     */
    private function extractFromContent(string $content, string $path): NamespaceInformation
    {
        try {
            // Named, not lexed as an anonymous string, so an error in the `ns`
            // form reports names a place the user can open (#3262).
            $tokenStream = $this->compilerFacade->lexString($content, $path);
            do {
                $parseTree = $this->compilerFacade->parseNext($tokenStream);
            } while ($parseTree instanceof TriviaNodeInterface);

            if (!$parseTree instanceof NodeInterface) {
                throw ExtractorException::cannotReadFile($path);
            }

            $readerResult = $this->compilerFacade->read($parseTree);
            /** @var bool|float|int|string|TypeInterface|null $ast */
            $ast = $readerResult->getAst();
            try {
                $node = $this->compilerFacade->analyze($ast, $this->compilerFacade->emptyNodeEnvironment());
            } catch (AnalyzerException $analyzerException) {
                if (!$this->isNsForm($ast)) {
                    if ($analyzerException->getErrorCode() === ErrorCode::UNDEFINED_SYMBOL) {
                        throw MissingNsFormException::inFile($path, $analyzerException);
                    }

                    throw $analyzerException;
                }

                // With its snippet, so it prints with the file and line of the
                // `ns` form wherever it surfaces, and a scan can tell it apart.
                throw new CompilerException($analyzerException, $readerResult->getCodeSnippet());
            }

            if ($node instanceof NsNode) {
                $realFile = realpath($path);

                return new NamespaceInformation(
                    $realFile !== false ? $realFile : $path,
                    $node->getNamespace(),
                    array_values(array_unique(array_map(
                        static fn(Symbol $s): string => $s->getFullName(),
                        $node->getRequireNs(),
                    ))),
                    isPrimaryDefinition: true,
                );
            }

            if ($node instanceof InNsNode) {
                $realFile = realpath($path);
                $namespace = $node->getNamespace();

                return new NamespaceInformation(
                    $realFile !== false ? $realFile : $path,
                    $namespace,
                    ($namespace === 'phel.core') ? [] : ['phel.core'],
                    isPrimaryDefinition: false,
                );
            }

            throw ExtractorException::cannotExtractNamespaceFromPath($path);
        } catch (AbstractParserException|ReaderException|LexerValueException $e) {
            throw ExtractorException::cannotParseFile($path, $e);
        }
    }

    private function isNsForm(mixed $ast): bool
    {
        if (!$ast instanceof PersistentListInterface) {
            return false;
        }

        $head = $ast->first();

        return $head instanceof Symbol && in_array($head->getName(), [Symbol::NAME_NS, Symbol::NAME_IN_NS], true);
    }

    /**
     * @throws ExtractorException
     *
     * @return list<NamespaceInformation>
     */
    private function findAllNs(string $directory, bool $failOnInvalidNsForm): array
    {
        $realpath = SourcePathResolver::resolve($directory);
        if ($realpath === null) {
            return [];
        }

        if (!is_dir($realpath)) {
            return [];
        }

        try {
            $directoryIterator = new RecursiveDirectoryIterator($realpath);
            $iterator = new RecursiveIteratorIterator($directoryIterator);
            $phelIterator = new RegexIterator($iterator, '/^.+\.(phel|cljc)$/i', RegexIterator::GET_MATCH);

            $result = [];
            foreach ($phelIterator as $file) {
                if (!is_array($file)) {
                    continue;
                }

                /** @var array<int, string> $file */
                if ($this->excludedPaths->contains($file[0], $realpath)) {
                    continue;
                }

                try {
                    $result[] = $this->getNamespaceFromFile($file[0]);
                } catch (AnalyzerException|ExtractorException|MissingNsFormException) {
                    // Skip files that cannot be parsed/lexed, or whose first
                    // form is not ns and does not analyse, so one stray file
                    // in a scanned directory does not abort the whole scan
                    // (e.g. `phel eval` in a cwd holding unrelated Clojure
                    // checkouts, #3484). Asking for one specific file still
                    // throws.
                    continue;
                } catch (CompilerException $compilerException) {
                    if ($failOnInvalidNsForm) {
                        throw $compilerException;
                    }

                    continue;
                }
            }
        } catch (UnexpectedValueException) {
            // Skip directories that cannot be read (e.g., permission denied)
            // This can happen with system-protected directories in temp paths
            return [];
        }

        return $result;
    }
}
