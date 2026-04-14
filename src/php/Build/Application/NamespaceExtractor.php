<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceFileGrouper;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Symbol;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

final readonly class NamespaceExtractor implements NamespaceExtractorInterface
{
    private NamespaceFileGrouper $grouper;

    /** @var list<string> */
    private array $excludedPrefixes;

    /**
     * @param list<string> $excludedDirectories absolute paths whose subtree must be
     *                                          skipped during recursive namespace scanning
     * @param string       $destDirBasename     when non-empty, any `<scan_root>/<basename>/`
     *                                          subtree is skipped per walk; lets us prune
     *                                          the build output even when the scan root is
     *                                          a different project root than where the
     *                                          extractor was configured
     */
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        NamespaceSorterInterface $namespaceSorter,
        private FileIoInterface $fileIo,
        array $excludedDirectories = [],
        private string $destDirBasename = '',
    ) {
        $this->grouper = new NamespaceFileGrouper($namespaceSorter);
        $this->excludedPrefixes = $this->normalizeExcludedPrefixes($excludedDirectories);
    }

    /**
     * @throws ExtractorException
     * @throws LexerValueException
     */
    public function getNamespaceFromFile(string $path): NamespaceInformation
    {
        $content = $this->fileIo->getContents($path);

        try {
            $tokenStream = $this->compilerFacade->lexString($content);
            do {
                $parseTree = $this->compilerFacade->parseNext($tokenStream);
            } while ($parseTree instanceof TriviaNodeInterface);

            if (!$parseTree instanceof NodeInterface) {
                throw ExtractorException::cannotReadFile($path);
            }

            $readerResult = $this->compilerFacade->read($parseTree);
            $ast = $readerResult->getAst();
            $node = $this->compilerFacade->analyze($ast, NodeEnvironment::empty());

            if ($node instanceof NsNode) {
                $realFile = realpath($path);

                return new NamespaceInformation(
                    $realFile !== false ? $realFile : $path,
                    $node->getNamespace(),
                    array_unique(array_map(
                        static fn(Symbol $s): string => $s->getFullName(),
                        $node->getRequireNs(),
                    )),
                    isPrimaryDefinition: true,
                );
            }

            if ($node instanceof InNsNode) {
                $realFile = realpath($path);
                $namespace = $node->getNamespace();

                return new NamespaceInformation(
                    $realFile !== false ? $realFile : $path,
                    $namespace,
                    ($namespace === 'phel\\core') ? [] : ['phel\\core'],
                    isPrimaryDefinition: false,
                );
            }

            throw ExtractorException::cannotExtractNamespaceFromPath($path);
        } catch (AbstractParserException|ReaderException) {
            throw ExtractorException::cannotParseFile($path);
        }
    }

    /**
     * @param list<string> $directories
     *
     * @throws ExtractorException
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories): array
    {
        $allInfos = [];
        foreach ($directories as $directory) {
            foreach ($this->findAllNs($directory) as $info) {
                $allInfos[] = $info;
            }
        }

        return $this->grouper->groupAndSort($allInfos);
    }

    /**
     * @throws ExtractorException
     */
    private function findAllNs(string $directory): array
    {
        $realpath = $this->resolvePath($directory);
        if ($realpath === null) {
            return [];
        }

        if (!is_dir($realpath)) {
            return [];
        }

        $walkExcludedPrefixes = $this->excludedPrefixesForWalk($realpath);

        try {
            $directoryIterator = new RecursiveDirectoryIterator($realpath);
            $iterator = new RecursiveIteratorIterator($directoryIterator);
            $phelIterator = new RegexIterator($iterator, '/^.+\.(phel|cljc)$/i', RegexIterator::GET_MATCH);

            $result = [];
            foreach ($phelIterator as $file) {
                if ($this->isExcluded($file[0], $walkExcludedPrefixes)) {
                    continue;
                }

                $result[] = $this->getNamespaceFromFile($file[0]);
            }
        } catch (UnexpectedValueException) {
            // Skip directories that cannot be read (e.g., permission denied)
            // This can happen with system-protected directories in temp paths
            return [];
        }

        return $result;
    }

    private function resolvePath(string $path): ?string
    {
        // Support PHAR paths
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }

        // Normal file system
        $real = realpath($path);
        return $real !== false ? $real : null;
    }

    /**
     * @param list<string> $walkPrefixes
     */
    private function isExcluded(string $path, array $walkPrefixes): bool
    {
        foreach ($walkPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function excludedPrefixesForWalk(string $scanRoot): array
    {
        $prefixes = $this->excludedPrefixes;

        if ($this->destDirBasename !== '') {
            $prefixes[] = rtrim($scanRoot, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $this->destDirBasename
                . DIRECTORY_SEPARATOR;
        }

        return $prefixes;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function normalizeExcludedPrefixes(array $directories): array
    {
        $prefixes = [];
        foreach ($directories as $dir) {
            if ($dir === '') {
                continue;
            }

            $real = realpath($dir);
            $resolved = $real !== false ? $real : $dir;
            $prefixes[] = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return $prefixes;
    }
}
